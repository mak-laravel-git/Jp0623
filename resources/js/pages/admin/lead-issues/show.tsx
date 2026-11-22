import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Undo2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { LeadIssueStatusBadge } from '@/components/admin/lead-issue-status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { usePermission } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import leadIssues from '@/routes/admin/lead-issues';
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
    const form = useForm(leadIssues.update(report.id), {
        status: 'approved' as 'approved' | 'rejected',
        admin_note: report.admin_note ?? '',
    });

    function decide(status: 'approved' | 'rejected') {
        if (form.processing) {
            return;
        }

        form.transform((data) => ({ ...data, status }));
        form.submit({ preserveScroll: true });
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
                        <Detail label="Paid" value={`$${report.price}`} />
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

                {report.status === 'open' &&
                can(PERMISSIONS.LEAD_ISSUES.EDIT) ? (
                    <section className="rounded-xl border bg-card p-5 shadow-sm">
                        <h2 className="mb-3 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                            Your decision
                        </h2>
                        <div className="grid max-w-2xl gap-2">
                            <Label htmlFor="admin-note">
                                Note for the operator
                            </Label>
                            <Textarea
                                id="admin-note"
                                rows={4}
                                value={form.data.admin_note}
                                onChange={(event) =>
                                    form.setData('admin_note', event.target.value)
                                }
                                placeholder="Explain the decision. The operator can read this on their tickets page."
                                aria-invalid={!!form.errors.admin_note}
                            />
                            <InputError message={form.errors.admin_note} />
                            <InputError message={form.errors.status} />
                        </div>
                        <div className="mt-4 flex flex-wrap gap-2">
                            <Button
                                type="button"
                                disabled={form.processing}
                                onClick={() => decide('approved')}
                            >
                                Approve return
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={() => decide('rejected')}
                            >
                                Reject
                            </Button>
                        </div>
                        <p className="mt-3 text-xs text-muted-foreground">
                            Approving only records the decision. Refunds are
                            handled separately.
                        </p>
                    </section>
                ) : null}
            </div>
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
