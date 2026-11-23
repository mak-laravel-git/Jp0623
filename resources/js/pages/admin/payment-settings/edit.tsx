import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, KeyRound, PlugZap, Save } from 'lucide-react';
import type { ReactNode } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import paymentSettings from '@/routes/admin/payment-settings';
import type {
    AdminPaymentSettings,
    PaymentSettingSource,
} from '@/types/admin';

type PaymentSettingsEditProps = {
    settings: AdminPaymentSettings;
    environments: string[];
};

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

function SourceBadge({ source }: { source: PaymentSettingSource }) {
    return (
        <Badge variant="outline" className={SOURCE_STYLES[source]}>
            {SOURCE_LABELS[source]}
        </Badge>
    );
}

function Field({
    label,
    source,
    hint,
    error,
    children,
}: {
    label: string;
    source: PaymentSettingSource;
    hint?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <Label>{label}</Label>
                <SourceBadge source={source} />
            </div>
            {children}
            {hint ? (
                <p className="text-xs text-muted-foreground">{hint}</p>
            ) : null}
            <InputError message={error} />
        </div>
    );
}

export default function PaymentSettingsEdit({
    settings,
    environments,
}: PaymentSettingsEditProps) {
    const form = useForm(paymentSettings.update(), {
        environment: settings.fields.environment.value ?? 'sandbox',
        // Only values saved here are prefilled, so saving never copies .env values into the database.
        application_id:
            settings.fields.application_id.source === 'admin'
                ? (settings.fields.application_id.value ?? '')
                : '',
        location_id:
            settings.fields.location_id.source === 'admin'
                ? (settings.fields.location_id.value ?? '')
                : '',
        webhook_url:
            settings.fields.webhook_url.source === 'admin'
                ? (settings.fields.webhook_url.value ?? '')
                : '',
        access_token: '',
        webhook_signature_key: '',
        clear_access_token: false,
        clear_webhook_signature_key: false,
    });

    const { secrets, fields } = settings;
    const goingLive = form.data.environment === 'production';

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        form.submit({
            preserveScroll: true,
            onSuccess: () => {
                form.setData({
                    ...form.data,
                    access_token: '',
                    webhook_signature_key: '',
                    clear_access_token: false,
                    clear_webhook_signature_key: false,
                });
            },
        });
    }

    return (
        <>
            <Head title="Payment settings" />

            <div className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <AdminPageHeader
                    title="Payment settings"
                    description="Square credentials used for lead purchases, memberships and refunds. Values saved here take priority over .env."
                    icon={KeyRound}
                />

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
                            ? 'Payments are configured'
                            : 'Payments are not configured'}
                    </Badge>
                    {settings.updated_at ? (
                        <span className="text-muted-foreground">
                            Last saved{' '}
                            {new Date(settings.updated_at).toLocaleString(
                                undefined,
                                { dateStyle: 'medium', timeStyle: 'short' },
                            )}
                            {settings.updated_by
                                ? ` by ${settings.updated_by}`
                                : ''}
                        </span>
                    ) : (
                        <span className="text-muted-foreground">
                            Nothing saved here yet. Values come from .env.
                        </span>
                    )}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="sm:ml-auto"
                        onClick={() =>
                            router.post(
                                paymentSettings.test().url,
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
                    <Field
                        label="Environment"
                        source={fields.environment.source}
                        error={form.errors.environment}
                    >
                        <Select
                            value={form.data.environment}
                            onValueChange={(value) =>
                                form.setData('environment', value)
                            }
                        >
                            <SelectTrigger className="sm:max-w-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {environments.map((environment) => (
                                    <SelectItem
                                        key={environment}
                                        value={environment}
                                    >
                                        {environment === 'production'
                                            ? 'Production (real payments)'
                                            : 'Sandbox (test payments)'}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>

                    {goingLive ? (
                        <p className="flex items-start gap-2 rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-200">
                            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                            Production charges real cards. Use the production
                            application ID, access token and location ID from
                            your Square dashboard, then press Test connection.
                        </p>
                    ) : null}

                    <div className="grid gap-6 lg:grid-cols-2">
                        <Field
                            label="Application ID"
                            source={fields.application_id.source}
                            error={form.errors.application_id}
                        >
                            <Input
                                value={form.data.application_id}
                                onChange={(event) =>
                                    form.setData(
                                        'application_id',
                                        event.target.value,
                                    )
                                }
                                placeholder={fields.application_id.value ?? ''}
                                autoComplete="off"
                            />
                        </Field>

                        <Field
                            label="Location ID"
                            source={fields.location_id.source}
                            error={form.errors.location_id}
                        >
                            <Input
                                value={form.data.location_id}
                                onChange={(event) =>
                                    form.setData(
                                        'location_id',
                                        event.target.value,
                                    )
                                }
                                placeholder={fields.location_id.value ?? ''}
                                autoComplete="off"
                            />
                        </Field>

                        <Field
                            label="Access token"
                            source={secrets.access_token.source}
                            hint={
                                secrets.access_token.configured
                                    ? `Saved (ends with ${secrets.access_token.last4}). Leave empty to keep it.`
                                    : 'Not set.'
                            }
                            error={form.errors.access_token}
                        >
                            <Input
                                type="password"
                                value={form.data.access_token}
                                onChange={(event) =>
                                    form.setData(
                                        'access_token',
                                        event.target.value,
                                    )
                                }
                                placeholder="Enter a new access token"
                                autoComplete="new-password"
                            />
                            {secrets.access_token.source === 'admin' ? (
                                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <input
                                        type="checkbox"
                                        checked={form.data.clear_access_token}
                                        onChange={(event) =>
                                            form.setData(
                                                'clear_access_token',
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    Remove the saved value (use .env)
                                </label>
                            ) : null}
                        </Field>

                        <Field
                            label="Webhook signature key"
                            source={secrets.webhook_signature_key.source}
                            hint={
                                secrets.webhook_signature_key.configured
                                    ? `Saved (ends with ${secrets.webhook_signature_key.last4}). Leave empty to keep it.`
                                    : 'Not set.'
                            }
                            error={form.errors.webhook_signature_key}
                        >
                            <Input
                                type="password"
                                value={form.data.webhook_signature_key}
                                onChange={(event) =>
                                    form.setData(
                                        'webhook_signature_key',
                                        event.target.value,
                                    )
                                }
                                placeholder="Enter a new signature key"
                                autoComplete="new-password"
                            />
                            {secrets.webhook_signature_key.source ===
                            'admin' ? (
                                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <input
                                        type="checkbox"
                                        checked={
                                            form.data
                                                .clear_webhook_signature_key
                                        }
                                        onChange={(event) =>
                                            form.setData(
                                                'clear_webhook_signature_key',
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    Remove the saved value (use .env)
                                </label>
                            ) : null}
                        </Field>
                    </div>

                    <Field
                        label="Webhook URL"
                        source={fields.webhook_url.source}
                        hint={`Must match the notification URL of your Square webhook subscription. Leave empty to use ${settings.default_webhook_url}. Subscribe to payment.created, payment.updated, refund.created and refund.updated.`}
                        error={form.errors.webhook_url}
                    >
                        <Input
                            value={form.data.webhook_url}
                            onChange={(event) =>
                                form.setData('webhook_url', event.target.value)
                            }
                            placeholder={settings.default_webhook_url}
                            autoComplete="off"
                        />
                    </Field>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button type="submit" disabled={form.processing}>
                            <Save className="h-4 w-4" />
                            {form.processing ? 'Saving…' : 'Save settings'}
                        </Button>
                        <p className="text-xs text-muted-foreground">
                            Save first, then press Test connection.
                        </p>
                    </div>
                </form>
            </div>
        </>
    );
}

PaymentSettingsEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Payment settings', href: paymentSettings.edit() },
    ],
};
