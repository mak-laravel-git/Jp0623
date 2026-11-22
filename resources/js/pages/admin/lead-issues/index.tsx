import { Head, Link, router, useForm } from '@inertiajs/react';
import { Search, Undo2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { DataPagination } from '@/components/admin/data-pagination';
import { LeadIssueStatusBadge } from '@/components/admin/lead-issue-status-badge';
import InputError from '@/components/input-error';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermission } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import leadIssues from '@/routes/admin/lead-issues';
import { update as updateSettings } from '@/routes/admin/lead-issues/settings';
import type { AdminLeadIssueRow, Paginated } from '@/types/admin';
import { PERMISSIONS } from '@/types/permissions';

type Option = { value: string; label: string };

type LeadIssuesIndexProps = {
    reports: Paginated<AdminLeadIssueRow>;
    filters: {
        search: string;
        status: string | null;
        category: string | null;
    };
    settings: {
        report_window_days: number | null;
    };
    statuses: Option[];
    categories: Option[];
};

export default function LeadIssuesIndex({
    reports,
    filters,
    settings,
    statuses,
    categories,
}: LeadIssuesIndexProps) {
    const { can } = usePermission();
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [category, setCategory] = useState(filters.category ?? 'all');
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                leadIssues.index().url,
                {
                    search: search || undefined,
                    status: status === 'all' ? undefined : status,
                    category: category === 'all' ? undefined : category,
                },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 350);

        return () => clearTimeout(timeout);
    }, [search, status, category]);

    return (
        <>
            <Head title="Lead issues" />

            <div className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <AdminPageHeader
                    title="Lead issues"
                    description="Leads that operators sent back, with the reason they wrote."
                    icon={Undo2}
                />

                <ReturnTimeLimit
                    days={settings.report_window_days}
                    canEdit={can(PERMISSIONS.LEAD_ISSUES.EDIT)}
                />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative w-full sm:max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search lead, operator, email…"
                            className="pl-9"
                        />
                    </div>

                    <Select value={status} onValueChange={setStatus}>
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

                    <Select value={category} onValueChange={setCategory}>
                        <SelectTrigger className="w-full sm:w-60">
                            <SelectValue placeholder="All reasons" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All reasons</SelectItem>
                            {categories.map((option) => (
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
                                    <TableHead>Ticket</TableHead>
                                    <TableHead>Lead</TableHead>
                                    <TableHead>Operator</TableHead>
                                    <TableHead>Reason</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Sent</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {reports.data.map((report) => (
                                    <TableRow key={report.id}>
                                        <TableCell className="font-mono text-xs">
                                            {report.ticket}
                                        </TableCell>
                                        <TableCell>
                                            <p className="text-sm font-medium">
                                                {report.lead_reference}
                                                {report.lead_name
                                                    ? ` · ${report.lead_name}`
                                                    : ''}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {report.lead_city ?? '—'} · $
                                                {report.price}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <p className="text-sm font-medium">
                                                {report.operator_name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {report.operator_email}
                                            </p>
                                        </TableCell>
                                        <TableCell className="max-w-xs">
                                            <p className="text-xs font-medium text-muted-foreground">
                                                {report.category_label}
                                            </p>
                                            <p className="line-clamp-2 text-sm">
                                                {report.reason}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <LeadIssueStatusBadge
                                                status={report.status}
                                            />
                                        </TableCell>
                                        <TableCell className="text-sm whitespace-nowrap text-muted-foreground">
                                            {new Date(
                                                report.created_at,
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
                                                            report.id,
                                                        ).url
                                                    }
                                                >
                                                    View
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {reports.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="py-10 text-center text-muted-foreground"
                                        >
                                            No leads have been sent back.
                                        </TableCell>
                                    </TableRow>
                                ) : null}
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <DataPagination meta={reports} />
            </div>
        </>
    );
}

/**
 * How long after buying a lead the operator can still send it back. Empty means no limit.
 */
function ReturnTimeLimit({
    days,
    canEdit,
}: {
    days: number | null;
    canEdit: boolean;
}) {
    const form = useForm(updateSettings(), {
        report_window_days: days === null ? '' : String(days),
    });

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        form.submit({ preserveScroll: true });
    }

    return (
        <form
            onSubmit={handleSubmit}
            className="rounded-xl border bg-card p-5 shadow-sm"
        >
            <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                Return time limit
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
                {days === null
                    ? 'Operators can send a lead back at any time.'
                    : `Operators can send a lead back within ${days} days of buying it.`}
            </p>

            {canEdit ? (
                <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="grid gap-2">
                        <Label htmlFor="report-window-days">
                            Days after purchase
                        </Label>
                        <Input
                            id="report-window-days"
                            type="number"
                            min={1}
                            max={365}
                            placeholder="No limit"
                            value={form.data.report_window_days}
                            onChange={(event) =>
                                form.setData(
                                    'report_window_days',
                                    event.target.value,
                                )
                            }
                            aria-invalid={!!form.errors.report_window_days}
                            className="sm:w-40"
                        />
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Saving…' : 'Save'}
                    </Button>
                    {form.data.report_window_days !== '' ? (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => {
                                form.setData('report_window_days', '');
                                router.put(
                                    updateSettings().url,
                                    { report_window_days: '' },
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            Remove limit
                        </Button>
                    ) : null}
                </div>
            ) : null}
            <InputError
                className="mt-2"
                message={form.errors.report_window_days}
            />
            {canEdit ? (
                <p className="mt-2 text-xs text-muted-foreground">
                    Leave the field empty for no limit.
                </p>
            ) : null}
        </form>
    );
}

LeadIssuesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Lead issues', href: leadIssues.index() },
    ],
};
