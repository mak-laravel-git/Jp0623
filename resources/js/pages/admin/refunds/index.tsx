import { Head, Link, router } from '@inertiajs/react';
import { Wallet } from 'lucide-react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { DataPagination } from '@/components/admin/data-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import leadIssues from '@/routes/admin/lead-issues';
import refundRoutes from '@/routes/admin/refunds';
import type { AdminRefundRow, Paginated } from '@/types/admin';

type Option = { value: string; label: string };

type RefundsIndexProps = {
    refunds: Paginated<AdminRefundRow>;
    filters: { type: string | null; status: string | null };
    types: Option[];
    statuses: Option[];
};

const STATUS_STYLES: Record<AdminRefundRow['status'], string> = {
    pending:
        'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200',
    completed:
        'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200',
    failed: 'border-destructive/40 bg-destructive/10 text-destructive',
};

export default function RefundsIndex({
    refunds,
    filters,
    types,
    statuses,
}: RefundsIndexProps) {
    function applyFilters(next: { type?: string; status?: string }) {
        const type = next.type ?? filters.type ?? 'all';
        const status = next.status ?? filters.status ?? 'all';

        router.get(
            refundRoutes.index().url,
            {
                type: type === 'all' ? undefined : type,
                status: status === 'all' ? undefined : status,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Refunds" />

            <div className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <AdminPageHeader
                    title="Refunds"
                    description="Refunds given for returned leads, as dollars to the card or as Credits."
                    icon={Wallet}
                />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <Select
                        value={filters.type ?? 'all'}
                        onValueChange={(type) => applyFilters({ type })}
                    >
                        <SelectTrigger className="w-full sm:w-48">
                            <SelectValue placeholder="All types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All types</SelectItem>
                            {types.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.status ?? 'all'}
                        onValueChange={(status) => applyFilters({ status })}
                    >
                        <SelectTrigger className="w-full sm:w-48">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {statuses.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-muted/50 hover:bg-muted/50">
                                    <TableHead>Lead</TableHead>
                                    <TableHead>Operator</TableHead>
                                    <TableHead>Refund</TableHead>
                                    <TableHead>Split</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Lead</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {refunds.data.map((refund) => (
                                    <TableRow key={refund.id}>
                                        <TableCell>
                                            <p className="text-sm font-medium">
                                                {refund.lead_reference}
                                                {refund.lead_name
                                                    ? ` · ${refund.lead_name}`
                                                    : ''}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {refund.lead_city ?? '—'}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <p className="text-sm font-medium">
                                                {refund.operator_name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {refund.operator_email}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <p className="text-sm font-medium">
                                                ${refund.total_amount}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {refund.type_label}
                                            </p>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {refund.credit_amount} Credits · $
                                            {refund.card_amount} card
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={
                                                    STATUS_STYLES[refund.status]
                                                }
                                            >
                                                {refund.status_label}
                                            </Badge>
                                            {refund.failure_message ? (
                                                <p className="mt-1 max-w-48 text-xs text-destructive">
                                                    {refund.failure_message}
                                                </p>
                                            ) : null}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {refund.lead_action_label}
                                        </TableCell>
                                        <TableCell className="text-sm whitespace-nowrap text-muted-foreground">
                                            {new Date(
                                                refund.created_at,
                                            ).toLocaleString(undefined, {
                                                dateStyle: 'medium',
                                                timeStyle: 'short',
                                            })}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link
                                                    href={
                                                        leadIssues.show(
                                                            refund.report_id,
                                                        ).url
                                                    }
                                                >
                                                    View
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {refunds.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={8}
                                            className="py-10 text-center text-muted-foreground"
                                        >
                                            No refunds yet.
                                        </TableCell>
                                    </TableRow>
                                ) : null}
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <DataPagination meta={refunds} />
            </div>
        </>
    );
}

RefundsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Refunds', href: refundRoutes.index() },
    ],
};
