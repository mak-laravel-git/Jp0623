import { Head, router, useForm } from '@inertiajs/react';
import { PlugZap, Save, Settings2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { OperatorSmsTabs } from '@/components/admin/operator-sms-tabs';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import twilioSettings from '@/routes/admin/twilio-settings';
import type { AdminTwilioSettings, PaymentSettingSource } from '@/types/admin';

type TwilioSettingsEditProps = {
    settings: AdminTwilioSettings;
};

type TextField = keyof AdminTwilioSettings['fields'];

const SOURCE_LABELS: Record<PaymentSettingSource, string> = {
    admin: 'Saved here',
    env: 'From .env',
    missing: 'Missing',
};

const SOURCE_STYLES: Record<PaymentSettingSource, string> = {
    admin: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200',
    env: 'border-sky-500/40 bg-sky-500/10 text-sky-800 dark:text-sky-200',
    missing: 'border-destructive/40 bg-destructive/10 text-destructive',
};

const TEXT_FIELDS: { name: TextField; label: string; hint: string }[] = [
    {
        name: 'sid',
        label: 'Account SID',
        hint: 'Starts with AC. Shown on the Twilio console home page.',
    },
    {
        name: 'messaging_service_sid',
        label: 'Messaging Service SID',
        hint: 'Starts with MG. Use the service linked to your approved A2P campaign.',
    },
    {
        name: 'from_number',
        label: 'Sender number',
        hint: 'Only used when no Messaging Service SID is set, for example +18325550100.',
    },
    {
        name: 'lead_radius_miles',
        label: 'Lead radius (miles)',
        hint: 'Operators within this distance get the automatic lead SMS.',
    },
];

function Field({
    id,
    label,
    source,
    hint,
    error,
    children,
}: {
    id: string;
    label: string;
    source: PaymentSettingSource;
    hint: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid content-start gap-2">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <Label htmlFor={id}>{label}</Label>
                <Badge variant="outline" className={SOURCE_STYLES[source]}>
                    {SOURCE_LABELS[source]}
                </Badge>
            </div>
            {children}
            <p className="text-xs text-muted-foreground">{hint}</p>
            <InputError message={error} />
        </div>
    );
}

export default function TwilioSettingsEdit({
    settings,
}: TwilioSettingsEditProps) {
    const { fields, secrets, enabled } = settings;

    // Only values saved here are prefilled, so saving never copies .env values into the database.
    const savedValue = (name: TextField): string =>
        fields[name].source === 'admin' ? (fields[name].value ?? '') : '';

    const form = useForm(twilioSettings.update(), {
        enabled: enabled.value,
        sid: savedValue('sid'),
        messaging_service_sid: savedValue('messaging_service_sid'),
        from_number: savedValue('from_number'),
        lead_radius_miles: savedValue('lead_radius_miles'),
        auth_token: '',
        clear_auth_token: false,
    });

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        form.submit({
            preserveScroll: true,
            onSuccess: () => {
                form.setData((data) => ({
                    ...data,
                    auth_token: '',
                    clear_auth_token: false,
                }));
            },
        });
    }

    return (
        <>
            <Head title="Twilio settings" />

            <div className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <AdminPageHeader
                    title="Twilio settings"
                    description="Credentials used to text operators. Values saved here take priority over .env."
                    icon={Settings2}
                />

                <OperatorSmsTabs active="settings" />

                <div className="flex flex-wrap items-center gap-3 rounded-xl border bg-card p-4 text-sm shadow-sm">
                    <Badge
                        variant="outline"
                        className={
                            settings.is_configured
                                ? SOURCE_STYLES.admin
                                : SOURCE_STYLES.missing
                        }
                    >
                        {settings.is_configured
                            ? 'SMS sending is ready'
                            : 'SMS will not send'}
                    </Badge>
                    <span className="text-muted-foreground">
                        {settings.updated_at
                            ? `Last saved ${new Date(
                                  settings.updated_at,
                              ).toLocaleString(undefined, {
                                  dateStyle: 'medium',
                                  timeStyle: 'short',
                              })}${
                                  settings.updated_by
                                      ? ` by ${settings.updated_by}`
                                      : ''
                              }`
                            : 'Nothing saved here yet. Values come from .env.'}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="sm:ml-auto"
                        onClick={() =>
                            router.post(
                                twilioSettings.test().url,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <PlugZap className="h-4 w-4" /> Test connection
                    </Button>
                </div>

                <form
                    onSubmit={handleSubmit}
                    className="space-y-6 rounded-xl border bg-card p-5 shadow-sm"
                >
                    <div className="grid gap-2">
                        <div className="flex items-center gap-3">
                            <Checkbox
                                id="enabled"
                                checked={form.data.enabled}
                                onCheckedChange={(checked) =>
                                    form.setData('enabled', checked === true)
                                }
                            />
                            <Label htmlFor="enabled">Send SMS messages</Label>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            When this is off, no automatic or manual SMS is
                            sent.
                        </p>
                        <InputError message={form.errors.enabled} />
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        {TEXT_FIELDS.map(({ name, label, hint }) => (
                            <Field
                                key={name}
                                id={name}
                                label={label}
                                source={fields[name].source}
                                hint={hint}
                                error={form.errors[name]}
                            >
                                <Input
                                    id={name}
                                    inputMode={
                                        name === 'lead_radius_miles'
                                            ? 'numeric'
                                            : undefined
                                    }
                                    value={form.data[name]}
                                    onChange={(event) =>
                                        form.setData(name, event.target.value)
                                    }
                                    placeholder={fields[name].value ?? ''}
                                    autoComplete="off"
                                />
                            </Field>
                        ))}

                        <Field
                            id="auth_token"
                            label="Auth token"
                            source={secrets.auth_token.source}
                            hint={
                                secrets.auth_token.configured
                                    ? `Saved (ends with ${secrets.auth_token.last4}). Leave empty to keep it.`
                                    : 'Not set. Stored encrypted and never shown again.'
                            }
                            error={form.errors.auth_token}
                        >
                            <Input
                                id="auth_token"
                                type="password"
                                value={form.data.auth_token}
                                onChange={(event) =>
                                    form.setData(
                                        'auth_token',
                                        event.target.value,
                                    )
                                }
                                placeholder="Enter a new auth token"
                                autoComplete="new-password"
                            />
                            {secrets.auth_token.source === 'admin' ? (
                                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <input
                                        type="checkbox"
                                        checked={form.data.clear_auth_token}
                                        onChange={(event) =>
                                            form.setData(
                                                'clear_auth_token',
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    Remove the saved value (use .env)
                                </label>
                            ) : null}
                        </Field>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button type="submit" disabled={form.processing}>
                            <Save className="h-4 w-4" />
                            {form.processing ? 'Saving…' : 'Save settings'}
                        </Button>
                        <p className="text-xs text-muted-foreground">
                            Save first, then press Test connection. The test
                            does not send an SMS.
                        </p>
                    </div>
                </form>
            </div>
        </>
    );
}

TwilioSettingsEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Twilio settings', href: twilioSettings.edit() },
    ],
};
