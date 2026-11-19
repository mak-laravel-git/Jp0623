import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Route, Search, Trash2 } from 'lucide-react';
import { AnimatePresence, motion } from 'motion/react';
import { useEffect, useRef, useState } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { ConfirmDeleteDialog } from '@/components/admin/confirm-delete-dialog';
import { DataPagination } from '@/components/admin/data-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import routeListings from '@/routes/admin/route-listings';
import type { AdminRouteListing, Paginated } from '@/types/admin';
import { PERMISSIONS } from '@/types/permissions';

interface RouteListingsIndexProps {
    routeListings: Paginated<AdminRouteListing>;
    filters: { search: string };
}

const money = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0,
});

export default function RouteListingsIndex({
    routeListings: paginated,
    filters,
}: RouteListingsIndexProps) {
    const { can } = usePermission();
    const [search, setSearch] = useState(filters.search ?? '');
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                routeListings.index().url,
                { search: search || undefined },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 350);

        return () => clearTimeout(timeout);
    }, [search]);

    return (
        <>
            <Head title="Route listings" />

            <div className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <AdminPageHeader
                    title="Established locations & routes for sale"
                    description="Manage the listings members see under Established locations & routes for sale."
                    icon={Route}
                >
                    {can(PERMISSIONS.ROUTE_LISTINGS.CREATE) && (
                        <Button asChild>
                            <Link href={routeListings.create().url}>
                                <Plus className="h-4 w-4" /> Add listing
                            </Link>
                        </Button>
                    )}
                </AdminPageHeader>

                <div className="relative w-full sm:max-w-xs">
                    <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search listings…"
                        className="pl-9"
                    />
                </div>

                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-muted/50 hover:bg-muted/50">
                                    <TableHead>Listing</TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Locs / machines
                                    </TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Annual revenue
                                    </TableHead>
                                    <TableHead>Asking price</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <AnimatePresence mode="popLayout">
                                    {paginated.data.map((listing) => (
                                        <motion.tr
                                            key={listing.id}
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
                                                    {listing.city}
                                                </p>
                                                <p className="text-xs text-foreground/70">
                                                    R-{listing.id} ·{' '}
                                                    {listing.about}
                                                </p>
                                            </TableCell>
                                            <TableCell className="hidden md:table-cell">
                                                {listing.locations_count} /{' '}
                                                {listing.machines_count}
                                            </TableCell>
                                            <TableCell className="hidden md:table-cell">
                                                {money.format(
                                                    listing.annual_revenue,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {money.format(
                                                    listing.asking_price,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        listing.is_active
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {listing.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    {can(
                                                        PERMISSIONS
                                                            .ROUTE_LISTINGS
                                                            .EDIT,
                                                    ) && (
                                                        <Button
                                                            asChild
                                                            variant="ghost"
                                                            size="icon"
                                                        >
                                                            <Link
                                                                href={
                                                                    routeListings.edit(
                                                                        listing.id,
                                                                    ).url
                                                                }
                                                                title="Edit"
                                                            >
                                                                <Pencil className="h-4 w-4" />
                                                            </Link>
                                                        </Button>
                                                    )}
                                                    {can(
                                                        PERMISSIONS
                                                            .ROUTE_LISTINGS
                                                            .DELETE,
                                                    ) && (
                                                        <ConfirmDeleteDialog
                                                            description={
                                                                <>
                                                                    Delete
                                                                    listing{' '}
                                                                    <strong>
                                                                        R-
                                                                        {
                                                                            listing.id
                                                                        }{' '}
                                                                        (
                                                                        {
                                                                            listing.city
                                                                        }
                                                                        )
                                                                    </strong>
                                                                    ?
                                                                </>
                                                            }
                                                            onConfirm={() =>
                                                                router.delete(
                                                                    routeListings.destroy(
                                                                        listing.id,
                                                                    ).url,
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="text-muted-foreground hover:text-destructive"
                                                                title="Delete"
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        </ConfirmDeleteDialog>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </motion.tr>
                                    ))}
                                </AnimatePresence>
                            </TableBody>
                        </Table>

                        {paginated.data.length === 0 && (
                            <div className="px-4 py-16 text-center">
                                <Route className="mx-auto h-10 w-10 text-muted-foreground/40" />
                                <h3 className="mt-4 text-sm font-semibold">
                                    No listings found
                                </h3>
                            </div>
                        )}
                    </div>

                    <DataPagination meta={paginated} />
                </div>
            </div>
        </>
    );
}

RouteListingsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Route listings', href: routeListings.index() },
    ],
};
