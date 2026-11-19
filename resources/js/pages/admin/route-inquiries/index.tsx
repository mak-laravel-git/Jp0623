import { Head, Link, router } from '@inertiajs/react';
import { Eye, MessageSquare, Search } from 'lucide-react';
import { AnimatePresence, motion } from 'motion/react';
import { useEffect, useRef, useState } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { DataPagination } from '@/components/admin/data-pagination';
import {
    ROUTE_INQUIRY_STATUS_LABELS,
    RouteInquiryStatusBadge,
} from '@/components/admin/route-inquiry-status-badge';
import { Button } from '@/components/ui/button';
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
import { usePermission } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import routeInquiries from '@/routes/admin/route-inquiries';
import type {
    AdminRouteInquiry,
    Paginated,
    RouteInquiryStatus,
} from '@/types/admin';
import { PERMISSIONS } from '@/types/permissions';

interface RouteInquiriesIndexProps {
    inquiries: Paginated<AdminRouteInquiry>;
    filters: { search: string; status: string | null };
}

export default function RouteInquiriesIndex({
    inquiries: paginated,
    filters,
}: RouteInquiriesIndexProps) {
    const { can } = usePermission();
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? 'all');
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                routeInquiries.index().url,
                {
                    search: search || undefined,
                    status: status === 'all' ? undefined : status,
                },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 350);

        return () => clearTimeout(timeout);
    }, [search, status]);

    return (
        <>
            <Head title="Route inquiries" />

            <div className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <AdminPageHeader
                    title="Route inquiries"
                    description="Inquiries members sent about established locations & routes for sale."
                    icon={MessageSquare}
                />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative w-full sm:max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search inquiries…"
                            className="pl-9"
                        />
                    </div>

                    <Select value={status} onValueChange={setStatus}>
                        <SelectTrigger className="w-full sm:w-44">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {(
                                Object.keys(
                                    ROUTE_INQUIRY_STATUS_LABELS,
                                ) as RouteInquiryStatus[]
                            ).map((value) => (
                                <SelectItem key={value} value={value}>
                                    {ROUTE_INQUIRY_STATUS_LABELS[value]}
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
                                    <TableHead>Member</TableHead>
                                    <TableHead className="hidden sm:table-cell">
                                        Listing
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Submitted
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <AnimatePresence mode="popLayout">
                                    {paginated.data.map((inquiry) => (
                                        <motion.tr
                                            key={inquiry.id}
                                            layout
                                            initial={{ opacity: 0, y: 6 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            exit={{ opacity: 0 }}
                                            transition={{
                                                type: 'spring',
                                                stiffness: 350,
                                                damping: 28,
                                            }}
                                            className="border-b transition-colors hover:bg-muted/30"
                                        >
                                            <TableCell>
                                                <p className="font-medium">
                                                    {inquiry.contact_name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {inquiry.contact_email}
                                                </p>
                                            </TableCell>
                                            <TableCell className="hidden sm:table-cell">
                                                <p className="text-sm">
                                                    {inquiry.listing_reference}{' '}
                                                    · {inquiry.listing_city}
                                                </p>
                                                <p className="mt-1 line-clamp-1 text-xs text-muted-foreground">
                                                    {inquiry.message}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <RouteInquiryStatusBadge
                                                    status={inquiry.status}
                                                />
                                            </TableCell>
                                            <TableCell className="hidden text-sm text-muted-foreground lg:table-cell">
                                                {new Date(
                                                    inquiry.created_at,
                                                ).toLocaleString()}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {can(
                                                    PERMISSIONS.ROUTE_INQUIRIES
                                                        .VIEW,
                                                ) && (
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label="View inquiry"
                                                    >
                                                        <Link
                                                            href={
                                                                routeInquiries.show(
                                                                    inquiry.id,
                                                                ).url
                                                            }
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                )}
                                            </TableCell>
                                        </motion.tr>
                                    ))}
                                </AnimatePresence>
                            </TableBody>
                        </Table>
                    </div>

                    {paginated.data.length === 0 && (
                        <div className="flex flex-col items-center justify-center gap-2 px-6 py-16 text-center">
                            <MessageSquare className="h-10 w-10 text-muted-foreground/50" />
                            <h3 className="text-sm font-medium text-muted-foreground">
                                No route inquiries yet
                            </h3>
                        </div>
                    )}

                    <DataPagination meta={paginated} />
                </div>
            </div>
        </>
    );
}

RouteInquiriesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Route inquiries', href: routeInquiries.index() },
    ],
};
