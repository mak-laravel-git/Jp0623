import { Form } from '@inertiajs/react';
import { update } from '@/actions/App/Http/Controllers/Operator/AccountController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { OperatorAccount } from '@/types/operator-dashboard';

export default function AccountProfileForm({
    account,
}: {
    account: OperatorAccount;
}) {
    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Profile"
                description="Your name and company"
            />

            <Form
                {...update.form()}
                options={{ preserveScroll: true }}
                className="grid gap-4 sm:grid-cols-2"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="first_name">First name</Label>
                            <Input
                                id="first_name"
                                name="first_name"
                                defaultValue={account.firstName}
                                autoComplete="given-name"
                                required
                            />
                            <InputError message={errors.first_name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="last_name">Last name</Label>
                            <Input
                                id="last_name"
                                name="last_name"
                                defaultValue={account.lastName}
                                autoComplete="family-name"
                                required
                            />
                            <InputError message={errors.last_name} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="company_name">Company name</Label>
                            <Input
                                id="company_name"
                                name="company_name"
                                defaultValue={account.company}
                                autoComplete="organization"
                                required={account.hasProfile}
                                disabled={!account.hasProfile}
                            />
                            {account.hasProfile ? null : (
                                <p className="text-xs text-muted-foreground">
                                    Your account has no company profile yet.
                                    Contact support to add your company and phone.
                                </p>
                            )}
                            <InputError message={errors.company_name} />
                        </div>
                        <div className="sm:col-span-2">
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Saving…' : 'Save profile'}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}
