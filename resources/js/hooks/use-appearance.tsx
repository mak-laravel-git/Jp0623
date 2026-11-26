import { useSyncExternalStore } from 'react';

export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
};

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = (): MediaQueryList | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const prefersDark = (): boolean => mediaQuery()?.matches ?? false;

const applyTheme = (): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const isDark = prefersDark();

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
};

const subscribe = (callback: () => void) => {
    const query = mediaQuery();

    query?.addEventListener('change', callback);

    return () => query?.removeEventListener('change', callback);
};

/**
 * There is no theme picker, so the theme always follows the device. Any light
 * or dark choice saved earlier is replaced.
 */
export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    localStorage.setItem('appearance', 'system');
    setCookie('appearance', 'system');
    applyTheme();

    // Set up system theme change listener
    mediaQuery()?.addEventListener('change', applyTheme);
}

export function useAppearance(): UseAppearanceReturn {
    const isDark = useSyncExternalStore(subscribe, prefersDark, () => false);

    return {
        appearance: 'system',
        resolvedAppearance: isDark ? 'dark' : 'light',
    } as const;
}
