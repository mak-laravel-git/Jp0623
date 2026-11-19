import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, MessageSquare } from 'lucide-react';
import { motion } from 'motion/react';
import type { ReactNode } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import {
    ROUTE_INQUIRY_STATUS_LABELS,
    RouteInquiryStatusBadge,
} from '@/components/admin/route-inquiry-status-badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePermission } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import routeInquiries from '@/routes/admin/route-inquiries';
import routeListings from '@/routes/admin/route-listings';
import type { AdminRouteInquiry, RouteInquiryStatus } from '@/types/admin';
import { PERMISSIONS } from '@/types/permissions';

const money = (value: number) => `$${value.toLocaleString('en-US')}`;

export default function ShowRouteInquiry({
    inquiry,
}: {
    inquiry: AdminRouteInquiry;
}) {
    const { can } = usePermission();
    const listing = inquiry.route_listing;

    return (
        <>
            <Head title={`Inquiry ${inquiry.listing_reference}`} />

            <div className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <AdminPageHeader
                    title="Inquiry details"
                    description={`${inquiry.contact_name} · ${inquiry.listing_reference}`}
                    icon={MessageSquare}
                >
                    <Button variant="outline" asChild>
                        <Link href={routeInquiries.index().url}>
                            <ArrowLeft className="h-4 w-4" /> Back
                        </Link>
                    </Button>
                </AdminPageHeader>

                <motion.div
                    initial={{ opacity: 0, y: 8 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="space-y-6"
                >
                    <div className="flex flex-wrap items-center gap-3">
                        <RouteInquiryStatusBadge status={inquiry.status} />
                        <span className="text-sm text-muted-foreground">
                            Submitted{' '}
                            {new Date(inquiry.created_at).toLocaleString()}
                        </span>

                        {can(PERMISSIONS.ROUTE_INQUIRIES.EDIT) && (
                            <Select
                                value={inquiry.status}
                                onValueChange={(status) =>
                                    router.put(
                                        routeInquiries.update(inquiry.id).url,
                                        { status },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <SelectTrigger
                                    className="w-full sm:w-44"
                                    aria-label="Update status"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
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
                        )}
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <Section title="Member">
                            <Detail
                                label="Name"
                                value={inquiry.contact_name}
                            />
                            <Detail
                                label="Email"
                                value={inquiry.contact_email}
                            />
                            <Detail
                                label="Phone"
                                value={inquiry.contact_phone ?? '—'}
                            />
                        </Section>

                        <Section title="Listing">
                            <Detail
                                label="Listing ID"
                                value={inquiry.listing_reference}
                            />
                            <Detail label="City" value={inquiry.listing_city} />
                            <Detail
                                label="Asking price"
                                value={money(inquiry.listing_asking_price)}
                            />
                            <div className="grid gap-1 sm:grid-cols-[8rem_1fr] sm:gap-3">
                                <dt className="text-sm text-muted-foreground">
                                    Listing page
                                </dt>
                                <dd className="text-sm font-medium">
                                    {listing ? (
                                        can(PERMISSIONS.ROUTE_LISTINGS.EDIT) ? (
                                            <Link
                                                href={
                                                    routeListings.edit(
                                                        listing.id,
                                                    ).url
                                                }
                                                className="underline underline-offset-4"
                                            >
                                                Open listing
                                                {listing.is_active
                                                    ? ''
                                                    : ' (inactive)'}
                                            </Link>
                                        ) : (
                                            listing.about
                                        )
                                    ) : (
                                        'Listing was removed'
                                    )}
                                </dd>
                            </div>
                        </Section>
                    </div>

                    <section className="rounded-xl border bg-card p-5 shadow-sm">
                        <h2 className="mb-4 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                            Message
                        </h2>
                        <p className="text-sm leading-relaxed whitespace-pre-wrap text-foreground">
                            {inquiry.message}
                        </p>
                    </section>
                </motion.div>
            </div>
        </>
    );
}

function Section({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
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

ShowRouteInquiry.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Route inquiries', href: routeInquiries.index() },
        { title: 'Details' },
    ],
};
