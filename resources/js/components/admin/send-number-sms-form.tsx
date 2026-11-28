import { useForm } from '@inertiajs/react';
import { Send } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import operatorSms from '@/routes/admin/operator-sms';

export function SendNumberSmsForm() {
    const form = useForm({
        phone_number: '',
        message: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post(operatorSms.sendNumber().url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form
            onSubmit={submit}
            className="space-y-4 rounded-xl border bg-card p-4 shadow-sm"
        >
            <div>
                <h2 className="font-medium">Send to a phone number</h2>
                <p className="text-sm text-muted-foreground">
                    Text one number that is not in the list. Only use your own
                    test number or someone who agreed to receive SMS.
                </p>
            </div>

            <div className="space-y-2 sm:max-w-xs">
                <Label htmlFor="sms-number">Phone number</Label>
                <Input
                    id="sms-number"
                    type="tel"
                    value={form.data.phone_number}
                    onChange={(event) =>
                        form.setData('phone_number', event.target.value)
                    }
                    placeholder="+18325550100"
                    autoComplete="off"
                />
            </div>
            <InputError message={form.errors.phone_number} />

            <div className="space-y-2">
                <Label htmlFor="sms-number-message">Message</Label>
                <Textarea
                    id="sms-number-message"
                    value={form.data.message}
                    onChange={(event) =>
                        form.setData('message', event.target.value)
                    }
                    rows={3}
                    maxLength={1600}
                    placeholder="Vending Machine Location Services: … Reply STOP to opt out."
                />
                <InputError message={form.errors.message} />
            </div>

            <Button
                type="submit"
                disabled={
                    form.processing ||
                    form.data.phone_number.trim() === '' ||
                    form.data.message.trim() === ''
                }
            >
                <Send className="h-4 w-4" />
                {form.processing ? 'Sending…' : 'Send SMS'}
            </Button>
        </form>
    );
}
