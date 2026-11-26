import { useForm } from '@inertiajs/react';
import { useState  } from 'react';
import type {FormEvent} from 'react';
import {
    sendPhoneCode,
    updatePhone,
} from '@/actions/App/Http/Controllers/Operator/AccountController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    currentPhone: string;
    smsVerification: boolean;
};

export default function AccountPhoneForm({
    currentPhone,
    smsVerification,
}: Props) {
    const [codeSent, setCodeSent] = useState(false);
    const form = useForm({ phone: '', current_password: '', code: '' });
    const needsCodeFirst = smsVerification && !codeSent;

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

        if (needsCodeFirst) {
            form.submit(sendPhoneCode(), {
                preserveScroll: true,
                onSuccess: () => setCodeSent(true),
            });

            return;
        }

        form.submit(updatePhone(), {
            preserveScroll: true,
            onSuccess: () => startAgain(),
        });
    }

    let submitLabel = 'Save phone';

    if (smsVerification) {
        submitLabel = codeSent ? 'Verify and change phone' : 'Text me a code';
    }

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Change phone"
                description={`Current phone: ${currentPhone || 'Not set'}`}
            />

            <form onSubmit={handleSubmit} className="space-y-4">
                <div className="grid gap-2">
                    <Label htmlFor="new_phone">New phone</Label>
                    <Input
                        id="new_phone"
                        type="tel"
                        value={form.data.phone}
                        onChange={(event) =>
                            form.setData('phone', event.target.value)
                        }
                        autoComplete="tel"
                        readOnly={codeSent}
                        required
                    />
                    <InputError message={form.errors.phone} />
                </div>

                {smsVerification ? null : (
                    <div className="grid gap-2">
                        <Label htmlFor="phone_current_password">
                            Current password
                        </Label>
                        <PasswordInput
                            id="phone_current_password"
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

                {codeSent ? (
                    <div className="grid gap-2">
                        <Label htmlFor="phone_code">6-digit code</Label>
                        <Input
                            id="phone_code"
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
                            Texted to your new phone. It expires in 10 minutes.
                        </p>
                        <InputError message={form.errors.code} />
                    </div>
                ) : null}

                <div className="flex flex-wrap gap-3">
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={form.processing}
                    >
                        {submitLabel}
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
