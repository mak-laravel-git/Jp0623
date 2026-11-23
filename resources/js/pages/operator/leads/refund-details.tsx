import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { PaymentDetailRows } from '@/components/payment-detail-rows';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { refunds as leadsRefunds } from '@/routes/leads';
import type { ReturnRequest } from '@/types/operator-dashboard';

const formatDate = (value: string | null) =>
    value
        ? new Date(value).toLocaleDateString(undefined, { dateStyle: 'medium' })
        : '—';

const STATUS_STYLES: Record<ReturnRequest['status'], string> = {
    open: 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200',
    approved:
        'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200',
    rejected: 'border-destructive/40 bg-destructive/10 text-destructive',
};

function Card({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="rounded-xl border bg-card p-4 shadow-sm sm:p-5">
            <h2 className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {title}
            </h2>
            {children}
        </section>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-4">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium break-words sm:text-right">
                {value}
            </dd>
        </div>
    );
}

/**
 * One return request: the member's reason, the admin decision and the refund.
 * Basic lead info only, never contact details.
 */
export default function RefundDetails({
    returnRequest: request,
}: {
    returnRequest: ReturnRequest;
}) {
    return (
        <>
            <Head title={`Return request ${request.ticket}`} />

            <div className="w-full space-y-4 px-4 py-6 sm:space-y-6 sm:px-6 lg:px-8">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0">
                        <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">
                            {request.ticket} · {request.lead_reference}
                        </h1>
                        <div className="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <Badge
                                variant="outline"
                                className={STATUS_STYLES[request.status]}
                            >
                                {request.status_label}
                            </Badge>
                            <span>Sent {formatDate(request.created_at)}</span>
                        </div>
                    </div>
                    <Button variant="outline" asChild className="w-full sm:w-auto">
                        <Link href={leadsRefunds().url}>
                            <ArrowLeft className="h-4 w-4" /> Back to Refunds
                            &amp; Credits
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 sm:gap-6 lg:grid-cols-2">
                    <Card title="Lead">
                        <dl className="space-y-3">
                            <Row label="Lead" value={request.lead_reference} />
                            <Row label="Type" value={request.lead_type ?? '—'} />
                            <Row label="City" value={request.lead_city ?? '—'} />
                            <Row label="Price" value={`$${request.price}`} />
                            <Row
                                label="You paid with"
                                value={`${request.credits_paid} Credits + $${request.card_paid} card`}
                            />
                            <Row
                                label="Bought"
                                value={formatDate(request.purchased_at)}
                            />
                        </dl>
                    </Card>

                    <Card title="Payment details">
                        <PaymentDetailRows rows={request.payment_details} />
                        <a
                            href={request.receipt_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="mt-3 inline-block text-sm text-brass underline-offset-2 hover:underline"
                        >
                            View receipt
                        </a>
                    </Card>

                    <Card title="Why you sent it back">
                        <p className="text-sm font-medium">
                            {request.category_label}
                        </p>
                        <p className="mt-2 text-sm whitespace-pre-wrap text-muted-foreground">
                            {request.reason}
                        </p>
                    </Card>

                    <Card title="What the admin did">
                        {request.status === 'open' ? (
                            <p className="text-sm text-muted-foreground">
                                Waiting for the admin to review your request.
                            </p>
                        ) : (
                            <>
                                <p className="text-sm font-medium">
                                    {request.status_label} ·{' '}
                                    {formatDate(request.resolved_at)}
                                </p>
                                <p className="mt-2 text-sm whitespace-pre-wrap text-muted-foreground">
                                    {request.admin_note ?? '—'}
                                </p>
                            </>
                        )}
                    </Card>

                    {request.refund ? (
                        <Card title="Refund">
                            <dl className="mb-3 space-y-3">
                                <Row
                                    label="Refunded"
                                    value={`$${request.refund.total_amount}`}
                                />
                            </dl>
                            <PaymentDetailRows rows={request.refund.details} />
                            <a
                                href={request.refund.receipt_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="mt-3 inline-block text-sm text-brass underline-offset-2 hover:underline"
                            >
                                View refund receipt
                            </a>
                            {request.refund.card_amount > 0 ? (
                                <p className="mt-3 text-xs text-muted-foreground">
                                    Card refunds can take 5-10 business days to
                                    show up.
                                </p>
                            ) : null}
                        </Card>
                    ) : null}
                </div>
            </div>
        </>
    );
}

RefundDetails.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Refunds & Credits', href: leadsRefunds() },
        { title: 'Details' },
    ],
};
