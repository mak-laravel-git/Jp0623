import { Head, router } from '@inertiajs/react';
import { History, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { DataPagination } from '@/components/admin/data-pagination';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
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
import membershipPriceHistory from '@/routes/admin/membership-price-history';
import type { AdminMembershipPriceHistory, Paginated } from '@/types/admin';

type MembershipPriceHistoryProps = {
    history: Paginated<AdminMembershipPriceHistory>;
    filters: {
        search: string;
        action: string | null;
    };
};

const ACTION_LABELS: Record<AdminMembershipPriceHistory['action'], string> = {
    created: 'Price set',
    updated: 'Price changed',
    removed: 'Price removed',
};

function price(value: number | null): string {
    return value === null ? '—' : `$${value}/mo`;
}

function actionBadge(action: AdminMembershipPriceHistory['action']) {
    const styles: Record<AdminMembershipPriceHistory['action'], string> = {
        created:
            'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200',
        updated:
            'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200',
        removed:
            'border-destructive/40 bg-destructive/10 text-destructive',
    };

    return (
        <Badge variant="outline" className={styles[action]}>
            {ACTION_LABELS[action]}
        </Badge>
    );
}

export default function MembershipPriceHistoryIndex({
    history,
    filters,
}: MembershipPriceHistoryProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [action, setAction] = useState(filters.action ?? 'all');
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                membershipPriceHistory.index().url,
                {
                    search: search || undefined,
                    action: action === 'all' ? undefined : action,
                },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 350);

        return () => clearTimeout(timeout);
    }, [search, action]);

    return (
        <>
            <Head title="Membership price history" />

            <div className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <AdminPageHeader
                    title="Membership price history"
                    description="Every time a custom member price is set, changed or removed."
                    icon={History}
                />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative w-full sm:max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search by email…"
                            className="pl-9"
                        />
                    </div>

                    <Select value={action} onValueChange={setAction}>
                        <SelectTrigger className="w-full sm:w-44">
                            <SelectValue placeholder="All changes" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All changes</SelectItem>
                            <SelectItem value="created">Price set</SelectItem>
                            <SelectItem value="updated">
                                Price changed
                            </SelectItem>
                            <SelectItem value="removed">
                                Price removed
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-muted/50 hover:bg-muted/50">
                                    <TableHead>Date</TableHead>
                                    <TableHead>Member</TableHead>
                                    <TableHead>Change</TableHead>
                                    <TableHead className="text-right">
                                        Old price
                                    </TableHead>
                                    <TableHead className="text-right">
                                        New price
                                    </TableHead>
                                    <TableHead>Changed by</TableHead>
                                    <TableHead>Emailed</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {history.data.map((entry) => (
                                    <TableRow key={entry.id}>
                                        <TableCell className="whitespace-nowrap text-sm">
                                            {new Date(
                                                entry.created_at,
                                            ).toLocaleString(undefined, {
                                                dateStyle: 'medium',
                                                timeStyle: 'short',
                                            })}
                                        </TableCell>
                                        <TableCell>
                                            <p className="text-sm font-medium">
                                                {entry.registered
                                                    ? entry.member_name
                                                    : 'Not registered'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {entry.email}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            {actionBadge(entry.action)}
                                            {entry.action === 'removed' &&
                                            entry.new_price !== null ? (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Now pays the default price
                                                </p>
                                            ) : null}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {price(entry.old_price)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {price(entry.new_price)}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {entry.changed_by ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-sm text-muted-foreground">
                                            {entry.email_sent ? 'Yes' : 'No'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {history.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="py-10 text-center text-muted-foreground"
                                        >
                                            No price changes yet.
                                        </TableCell>
                                    </TableRow>
                                ) : null}
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <DataPagination meta={history} />
            </div>
        </>
    );
}

MembershipPriceHistoryIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Membership price history',
            href: membershipPriceHistory.index(),
        },
    ],
};
