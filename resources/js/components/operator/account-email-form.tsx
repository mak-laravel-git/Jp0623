import { useForm } from '@inertiajs/react';
import { useState  } from 'react';
import type {FormEvent} from 'react';
import {
    sendEmailCode,
    updateEmail,
} from '@/actions/App/Http/Controllers/Operator/AccountController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SPAM_NOTE } from '@/lib/verification-copy';

export default function AccountEmailForm({
    currentEmail,
}: {
    currentEmail: string;
}) {
    const [codeSent, setCodeSent] = useState(false);
    const form = useForm({ email: '', current_password: '', code: '' });

    function sendCode() {
        form.submit(sendEmailCode(), {
            preserveScroll: true,
            onSuccess: () => {
                setCodeSent(true);
                form.setData((data) => ({ ...data, current_password: '' }));
            },
        });
    }

    function confirmCode() {
        form.submit(updateEmail(), {
            preserveScroll: true,
            onSuccess: () => startAgain(),
        });
    }

    function startAgain() {
        setCodeSent(false);
        form.reset();
        form.clearErrors();
    }

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        if (codeSent) {
            confirmCode();
        } else {
            sendCode();
        }
    }

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Change email"
                description={`Current email: ${currentEmail}`}
            />

            <form onSubmit={handleSubmit} className="space-y-4">
                <div className="grid gap-2">
                    <Label htmlFor="new_email">New email</Label>
                    <Input
                        id="new_email"
                        type="email"
                        value={form.data.email}
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
                        autoComplete="email"
                        readOnly={codeSent}
                        required
                    />
                    <InputError message={form.errors.email} />
                </div>

                {codeSent ? (
                    <div className="grid gap-2">
                        <Label htmlFor="email_code">6-digit code</Label>
                        <Input
                            id="email_code"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            maxLength={6}
                            value={form.data.code}
                            onChange={(event) =>
                                form.setData(
                                    'code',
                                    event.target.value.replace(/\D/g, ''),
                                )
                            }
                            required
                        />
                        <p className="text-xs text-muted-foreground">
                            Sent to your new email. It expires in 10 minutes.{' '}
                            {SPAM_NOTE}
                        </p>
                        <InputError message={form.errors.code} />
                    </div>
                ) : (
                    <div className="grid gap-2">
                        <Label htmlFor="email_current_password">
                            Current password
                        </Label>
                        <PasswordInput
                            id="email_current_password"
                            value={form.data.current_password}
                            onChange={(event) =>
                                form.setData(
                                    'current_password',
                                    event.target.value,
                                )
                            }
                            autoComplete="current-password"
                            required
                        />
                        <InputError message={form.errors.current_password} />
                    </div>
                )}

                <div className="flex flex-wrap gap-3">
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={form.processing}
                    >
                        {codeSent ? 'Verify and change email' : 'Send code'}
                    </Button>
                    {codeSent ? (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={startAgain}
                            disabled={form.processing}
                        >
                            Start again
                        </Button>
                    ) : null}
                </div>
            </form>
        </div>
    );
}
