import { Head, Link } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import Heading from '@/components/heading';
import AccountEmailForm from '@/components/operator/account-email-form';
import AccountPhoneForm from '@/components/operator/account-phone-form';
import AccountProfileForm from '@/components/operator/account-profile-form';
import PasswordForm from '@/components/password-form';
import TwoFactorSection from '@/components/two-factor-section';
import { Button } from '@/components/ui/button';
import { dashboard, logout } from '@/routes';
import { index as accountIndex, twoFactor } from '@/routes/account';
import type { OperatorAccount } from '@/types/operator-dashboard';

type AccountProps = {
    account: OperatorAccount;
    smsVerification: boolean;
    passwordRules: string;
    canManageTwoFactor: boolean;
    twoFactorEnabled?: boolean;
    requiresConfirmation?: boolean;
    twoFactorLocked?: boolean;
};

const CARD = 'rounded-xl border bg-card p-6 shadow-sm';

export default function AccountIndex({
    account,
    smsVerification,
    passwordRules,
    canManageTwoFactor,
    twoFactorEnabled = false,
    requiresConfirmation = false,
    twoFactorLocked = false,
}: AccountProps) {
    return (
        <>
            <Head title="Account info" />

            <div className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h1 className="text-3xl font-extrabold tracking-tight">
                        Account info
                    </h1>
                    <p className="mt-1 max-w-3xl text-muted-foreground">
                        Update your profile, sign-in details and security.
                        Changing your email needs a one-time code.
                    </p>
                </div>

                <div className={CARD}>
                    <AccountProfileForm account={account} />
                </div>

                <div className="grid items-start gap-4 lg:grid-cols-2">
                    <div className={CARD}>
                        <AccountEmailForm currentEmail={account.email} />
                    </div>

                    {account.hasProfile ? (
                        <div className={CARD}>
                            <AccountPhoneForm
                                currentPhone={account.phone}
                                smsVerification={smsVerification}
                            />
                        </div>
                    ) : null}
                </div>

                <div className="grid items-start gap-4 lg:grid-cols-2">
                    <div className={CARD}>
                        <PasswordForm passwordRules={passwordRules} />
                    </div>

                    {canManageTwoFactor ? (
                        <div className={CARD}>
                            {twoFactorLocked ? (
                                <div className="space-y-6">
                                    <Heading
                                        variant="small"
                                        title="Two-factor authentication"
                                        description={
                                            twoFactorEnabled
                                                ? 'Two-factor authentication is on.'
                                                : 'Add a second step when you sign in.'
                                        }
                                    />
                                    <Button variant="outline" asChild>
                                        <Link href={twoFactor()}>
                                            <ShieldCheck />
                                            Confirm password to manage
                                        </Link>
                                    </Button>
                                </div>
                            ) : (
                                <TwoFactorSection
                                    requiresConfirmation={requiresConfirmation}
                                    twoFactorEnabled={twoFactorEnabled}
                                />
                            )}
                        </div>
                    ) : null}
                </div>

                <Button variant="outline" asChild>
                    <Link href={logout()} method="post" as="button">
                        Sign out
                    </Link>
                </Button>
            </div>
        </>
    );
}

AccountIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Account info', href: accountIndex() },
    ],
};
