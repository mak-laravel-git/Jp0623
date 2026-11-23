import { Head, Link, usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import { refunds as leadsRefunds } from '@/routes/leads';
import { show as showRefund } from '@/routes/leads/refunds';
import type { ReturnRequest } from '@/types/operator-dashboard';

interface CreditRow {
    id: number;
    label: string;
    note: string | null;
    amount: number;
    balance_after: number;
    created_at: string;
}

const formatDate = (value: string) =>
    new Date(value).toLocaleDateString(undefined, { dateStyle: 'medium' });

function refundSummary(request: ReturnRequest): string {
    if (!request.refund) {
        return '—';
    }

    const parts: string[] = [];

    if (request.refund.credit_amount > 0) {
        parts.push(`${request.refund.credit_amount} Credits`);
    }

    if (request.refund.card_amount > 0) {
        parts.push(`$${request.refund.card_amount} to card`);
    }

    return parts.join(' + ');
}

function statusBadge(request: ReturnRequest) {
    const styles: Record<ReturnRequest['status'], string> = {
        open: 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200',
        approved:
            'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200',
        rejected: 'border-destructive/40 bg-destructive/10 text-destructive',
    };

    return (
        <Badge variant="outline" className={styles[request.status]}>
            {request.status_label}
        </Badge>
    );
}

export default function LeadRefunds({
    requests,
    creditHistory,
}: {
    requests: ReturnRequest[];
    creditHistory: CreditRow[];
}) {
    const { credits } = usePage().props;

    return (
        <>
            <Head title="Refunds & Credits" />

            <div className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h1 className="text-3xl font-extrabold tracking-tight">
                        Refunds &amp; Credits
                    </h1>
                    <p className="mt-1 max-w-3xl text-muted-foreground">
                        The leads you sent back, what the admin decided and any
                        refund you received. 1 Credit = $1 and you can use
                        Credits when you buy a lead.
                    </p>
                </div>

                <div className="rounded-xl border bg-card p-5 shadow-sm">
                    <p className="text-sm text-muted-foreground">
                        Your Credits
                    </p>
                    <p className="text-3xl font-extrabold">{credits}</p>
                </div>

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Return requests</h2>

                    <div className="space-y-3 md:hidden">
                        {requests.length === 0 ? (
                            <p className="rounded-xl border bg-card p-6 text-center text-sm text-muted-foreground">
                                No return requests yet.
                            </p>
                        ) : null}
                        {requests.map((request) => (
                            <Link
                                key={request.id}
                                href={showRefund(request.id).url}
                                className="block space-y-2 rounded-xl border bg-card p-4 shadow-sm"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="font-medium">
                                            {request.ticket} ·{' '}
                                            {request.lead_reference}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {[
                                                request.lead_type,
                                                request.lead_city,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                    </div>
                                    {statusBadge(request)}
                                </div>
                                <p className="line-clamp-2 text-sm">
                                    {request.reason}
                                </p>
                                <div className="flex items-center justify-between text-xs text-muted-foreground">
                                    <span>
                                        Refund: {refundSummary(request)}
                                    </span>
                                    <span>{formatDate(request.created_at)}</span>
                                </div>
                            </Link>
                        ))}
                    </div>

                    <div className="hidden overflow-hidden rounded-xl border bg-card shadow-sm md:block">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-muted/50 hover:bg-muted/50">
                                        <TableHead>Ticket</TableHead>
                                        <TableHead>Lead</TableHead>
                                        <TableHead>Reason</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Refund</TableHead>
                                        <TableHead>Sent</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {requests.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={7}
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                No return requests yet. Use
                                                “Return this lead” on a
                                                purchased lead to send one to
                                                the admin.
                                            </TableCell>
                                        </TableRow>
                                    ) : null}
                                    {requests.map((request) => (
                                        <TableRow key={request.id}>
                                            <TableCell className="font-mono text-xs">
                                                {request.ticket}
                                            </TableCell>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {request.lead_reference}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {[
                                                        request.lead_type,
                                                        request.lead_city,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </p>
                                            </TableCell>
                                            <TableCell className="max-w-xs">
                                                <p className="text-xs font-medium text-muted-foreground">
                                                    {request.category_label}
                                                </p>
                                                <p className="line-clamp-2 text-sm">
                                                    {request.reason}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                {statusBadge(request)}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {refundSummary(request)}
                                                {request.refund &&
                                                request.refund.status !==
                                                    'completed' ? (
                                                    <p className="text-xs text-muted-foreground">
                                                        {
                                                            request.refund
                                                                .status_label
                                                        }
                                                    </p>
                                                ) : null}
                                            </TableCell>
                                            <TableCell className="text-sm whitespace-nowrap">
                                                {formatDate(request.created_at)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link
                                                        href={
                                                            showRefund(
                                                                request.id,
                                                            ).url
                                                        }
                                                    >
                                                        Details
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </section>

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Credits history</h2>
                    <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-muted/50 hover:bg-muted/50">
                                        <TableHead>Date</TableHead>
                                        <TableHead>What</TableHead>
                                        <TableHead className="text-right">
                                            Credits
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Balance
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {creditHistory.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={4}
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                No Credits yet.
                                            </TableCell>
                                        </TableRow>
                                    ) : null}
                                    {creditHistory.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell className="text-sm whitespace-nowrap">
                                                {formatDate(row.created_at)}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {row.label}
                                                {row.note ? (
                                                    <p className="text-xs text-muted-foreground">
                                                        {row.note}
                                                    </p>
                                                ) : null}
                                            </TableCell>
                                            <TableCell
                                                className={`text-right font-medium ${
                                                    row.amount > 0
                                                        ? 'text-emerald-700 dark:text-emerald-300'
                                                        : ''
                                                }`}
                                            >
                                                {row.amount > 0 ? '+' : ''}
                                                {row.amount}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {row.balance_after}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </section>
            </div>

        </>
    );
}

LeadRefunds.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Refunds & Credits', href: leadsRefunds() },
    ],
};
