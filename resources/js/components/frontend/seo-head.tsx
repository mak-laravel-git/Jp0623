import { Head, usePage } from '@inertiajs/react';
import type { SeoData } from '@/types/frontend';

type SeoHeadProps = {
    /** Used when the page has no entry in config/seo.php. */
    title: string;
    /** Used when the page has no entry in config/seo.php. */
    description?: string;
};

/**
 * Sets the page title, meta description, canonical link and JSON-LD schema.
 * Values from the server (config/seo.php) win over the page's own fallbacks.
 */
export function SeoHead({ title, description }: SeoHeadProps) {
    const { seo } = usePage<{ seo: SeoData | null }>().props;
    const metaDescription = seo?.description ?? description;

    return (
        <Head title={seo?.title ?? title}>
            {metaDescription && (
                <meta
                    head-key="description"
                    name="description"
                    content={metaDescription}
                />
            )}
            {seo?.canonical && (
                <link
                    head-key="canonical"
                    rel="canonical"
                    href={seo.canonical}
                />
            )}
            {seo?.schema && (
                <script
                    head-key="schema"
                    type="application/ld+json"
                    dangerouslySetInnerHTML={{
                        __html: JSON.stringify(seo.schema).replace(
                            /</g,
                            '\\u003c',
                        ),
                    }}
                />
            )}
        </Head>
    );
}
