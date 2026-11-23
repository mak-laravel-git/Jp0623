import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Undo2 } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { LeadIssueStatusBadge } from '@/components/admin/lead-issue-status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { usePermission } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import leadIssues from '@/routes/admin/lead-issues';
import { retry as retryRefund } from '@/routes/admin/refunds';
import type { AdminLeadIssueDetail } from '@/types/admin';
import { PERMISSIONS } from '@/types/permissions';

const formatDateTime = (value: string | null) =>
    value
        ? new Date(value).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';

export default function ShowLeadIssue({
    report,
}: {
    report: AdminLeadIssueDetail;
}) {
    const { can } = usePermission();
    const [note, setNote] = useState('');
    const [refundOpen, setRefundOpen] = useState(false);
    const rejectForm = useForm(leadIssues.update(report.id), {
        status: 'rejected',
        admin_note: '',
    });
    const refundForm = useForm(leadIssues.refund(report.id), {
        type: 'credit' as 'credit' | 'dollar',
        lead_action: 'resell' as 'resell' | 'keep_off_sale',
        admin_note: '',
    });
    const payment = report.payment;
    const refund = report.refund;
    const noteTooShort = note.trim().length < 3;

    function reject() {
        if (rejectForm.processing) {
            return;
        }

        rejectForm.transform((data) => ({ ...data, admin_note: note }));
        rejectForm.submit({ preserveScroll: true });
    }

    function submitRefund() {
        if (refundForm.processing) {
            return;
        }

        refundForm.transform((data) => ({ ...data, admin_note: note }));
        refundForm.submit({
            preserveScroll: true,
            onSuccess: () => setRefundOpen(false),
        });
    }

    return (
        <>
            <Head title={`Lead issue ${report.ticket}`} />

            <div className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <AdminPageHeader
                    title={`Lead issue ${report.ticket}`}
                    description={`${report.lead_reference} · ${report.operator.name}`}
                    icon={Undo2}
                >
                    <Button variant="outline" asChild>
                        <Link href={leadIssues.index().url}>
                            <ArrowLeft className="h-4 w-4" /> Back
                        </Link>
                    </Button>
                </AdminPageHeader>

                <div className="flex flex-wrap items-center gap-3">
                    <LeadIssueStatusBadge status={report.status} />
                    <span className="text-sm text-muted-foreground">
                        Sent {formatDateTime(report.created_at)}
                    </span>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Section title="Operator">
                        <Detail label="Name" value={report.operator.name} />
                        <Detail label="Email" value={report.operator.email} />
                        <Detail
                            label="Phone"
                            value={report.operator.phone ?? '—'}
                        />
                        <Detail
                            label="Company"
                            value={report.operator.company ?? '—'}
                        />
                    </Section>

                    <Section title="Lead and purchase">
                        <Detail label="Lead" value={report.lead_reference} />
                        <Detail label="Name" value={report.lead_name ?? '—'} />
                        <Detail label="Type" value={report.lead_type ?? '—'} />
                        <Detail label="City" value={report.lead_city ?? '—'} />
                        <Detail label="Price" value={`$${report.price}`} />
                        <Detail
                            label="Paid with"
                            value={`${payment.credits} Credits + $${payment.card} card`}
                        />
                        <Detail
                            label="Purchased"
                            value={formatDateTime(report.purchased_at)}
                        />
                        {report.receipt_url ? (
                            <div className="grid gap-1 sm:grid-cols-[8rem_1fr] sm:gap-3">
                                <dt className="text-sm text-muted-foreground">
                                    Receipt
                                </dt>
                                <dd className="text-sm font-medium">
                                    <a
                                        href={report.receipt_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="underline underline-offset-4"
                                    >
                                        View receipt
                                    </a>
                                </dd>
                            </div>
                        ) : null}
                    </Section>
                </div>

                <section className="rounded-xl border bg-card p-5 shadow-sm">
                    <h2 className="mb-1 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                        Reason written by the operator
                    </h2>
                    <p className="mb-3 text-sm font-medium">
                        {report.category_label}
                    </p>
                    <p className="text-sm leading-relaxed whitespace-pre-wrap">
                        {report.reason}
                    </p>
                </section>

                {report.status !== 'open' ? (
                    <section className="rounded-xl border bg-card p-5 shadow-sm">
                        <h2 className="mb-3 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                            Decision
                        </h2>
                        <p className="text-sm">
                            {report.status_label}
                            {report.resolved_by
                                ? ` by ${report.resolved_by}`
                                : ''}{' '}
                            · {formatDateTime(report.resolved_at)}
                        </p>
                        <p className="mt-2 text-sm whitespace-pre-wrap text-muted-foreground">
                            {report.admin_note}
                        </p>
                    </section>
                ) : null}

                {refund ? (
                    <Section title="Refund">
                        <Detail label="Type" value={refund.type_label} />
                        <Detail
                            label="Total refunded"
                            value={`$${refund.total_amount}`}
                        />
                        <Detail
                            label="Back as Credits"
                            value={`${refund.credit_amount} Credits`}
                        />
                        <Detail
                            label="Back to card"
                            value={`$${refund.card_amount}`}
                        />
                        <Detail label="Status" value={refund.status_label} />
                        <Detail label="Lead" value={refund.lead_action_label} />
                        <Detail
                            label="Refunded by"
                            value={`${refund.refunded_by ?? '—'} · ${formatDateTime(refund.created_at)}`}
                        />
                        {refund.square_refund_id ? (
                            <Detail
                                label="Square refund"
                                value={refund.square_refund_id}
                            />
                        ) : null}
                        {refund.failure_message ? (
                            <p className="text-sm text-destructive">
                                {refund.failure_message}
                            </p>
                        ) : null}
                        {refund.status === 'failed' &&
                        can(PERMISSIONS.LEAD_ISSUES.REFUND) ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        retryRefund(refund.id).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Retry card refund
                            </Button>
                        ) : null}
                    </Section>
                ) : null}

                {report.status === 'open' &&
                (can(PERMISSIONS.LEAD_ISSUES.EDIT) ||
                    can(PERMISSIONS.LEAD_ISSUES.REFUND)) ? (
                    <section className="rounded-xl border bg-card p-5 shadow-sm">
                        <h2 className="mb-3 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                            Your decision
                        </h2>
                        <div className="mb-4 rounded-lg bg-muted/50 p-3 text-sm">
                            <p className="font-medium">
                                The member paid ${payment.total} for this lead
                            </p>
                            <p className="text-muted-foreground">
                                Credits used: {payment.credits} · Card paid: $
                                {payment.card}
                            </p>
                        </div>
                        <div className="grid max-w-2xl gap-2">
                            <Label htmlFor="admin-note">
                                Note for the operator
                            </Label>
                            <Textarea
                                id="admin-note"
                                rows={4}
                                value={note}
                                onChange={(event) =>
                                    setNote(event.target.value)
                                }
                                placeholder="Explain the decision. The operator can read this on their tickets page."
                                aria-invalid={
                                    !!(
                                        rejectForm.errors.admin_note ||
                                        refundForm.errors.admin_note
                                    )
                                }
                            />
                            <InputError
                                message={
                                    rejectForm.errors.admin_note ??
                                    refundForm.errors.admin_note
                                }
                            />
                            <InputError message={rejectForm.errors.status} />
                        </div>
                        <div className="mt-4 flex flex-wrap gap-2">
                            {can(PERMISSIONS.LEAD_ISSUES.REFUND) &&
                            payment.can_refund ? (
                                <Button
                                    type="button"
                                    disabled={noteTooShort}
                                    onClick={() => setRefundOpen(true)}
                                >
                                    Refund
                                </Button>
                            ) : null}
                            {can(PERMISSIONS.LEAD_ISSUES.EDIT) ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={
                                        rejectForm.processing || noteTooShort
                                    }
                                    onClick={reject}
                                >
                                    Reject
                                </Button>
                            ) : null}
                        </div>
                        {noteTooShort ? (
                            <p className="mt-3 text-xs text-muted-foreground">
                                Write a note first. It is required for both
                                decisions.
                            </p>
                        ) : null}
                    </section>
                ) : null}
            </div>

            <Dialog open={refundOpen} onOpenChange={setRefundOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Refund ${payment.total}</DialogTitle>
                        <DialogDescription>
                            The full amount is refunded. This lead was paid with{' '}
                            {payment.credits} Credits and ${payment.card} by
                            card.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label>Refund as</Label>
                            <Select
                                value={refundForm.data.type}
                                onValueChange={(value) =>
                                    refundForm.setData(
                                        'type',
                                        value as 'credit' | 'dollar',
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="credit">
                                        {`Credits (${payment.total} Credits)`}
                                    </SelectItem>
                                    <SelectItem
                                        value="dollar"
                                        disabled={!payment.can_refund_dollars}
                                    >
                                        {`Dollars to the card ($${payment.card})${
                                            payment.credits > 0
                                                ? ` + ${payment.credits} Credits back`
                                                : ''
                                        }`}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={refundForm.errors.type} />
                        </div>

                        <div className="grid gap-2">
                            <Label>What happens to the lead</Label>
                            <Select
                                value={refundForm.data.lead_action}
                                onValueChange={(value) =>
                                    refundForm.setData(
                                        'lead_action',
                                        value as 'resell' | 'keep_off_sale',
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="resell">
                                        Sell it again to other members
                                    </SelectItem>
                                    <SelectItem value="keep_off_sale">
                                        Do not sell it again
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                message={refundForm.errors.lead_action}
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setRefundOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            disabled={refundForm.processing || noteTooShort}
                            onClick={submitRefund}
                        >
                            {refundForm.processing
                                ? 'Refunding…'
                                : 'Confirm refund'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="rounded-xl border bg-card p-5 shadow-sm">
            <h2 className="mb-4 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                {title}
            </h2>
            <dl className="space-y-3">{children}</dl>
        </section>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid gap-1 sm:grid-cols-[8rem_1fr] sm:gap-3">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium break-words">{value}</dd>
        </div>
    );
}

ShowLeadIssue.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Lead issues', href: leadIssues.index() },
        { title: 'Details' },
    ],
};
