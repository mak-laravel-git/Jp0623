import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Route } from 'lucide-react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { RouteListingForm } from '@/components/admin/route-listing-form';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import routeListings from '@/routes/admin/route-listings';
import type { AdminRouteListing } from '@/types/admin';

export default function EditRouteListing({
    routeListing,
}: {
    routeListing: AdminRouteListing;
}) {
    return (
        <>
            <Head title={`Edit listing R-${routeListing.id}`} />

            <div className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <AdminPageHeader
                    title="Edit listing"
                    description="Update this established location or route for sale."
                    icon={Route}
                >
                    <Button variant="outline" asChild>
                        <Link href={routeListings.index().url}>
                            <ArrowLeft className="h-4 w-4" /> Back to listings
                        </Link>
                    </Button>
                </AdminPageHeader>

                <div className="rounded-xl border bg-card p-6 shadow-sm">
                    <RouteListingForm
                        action={routeListings.update(routeListing.id)}
                        isEdit
                        defaults={{
                            city: routeListing.city,
                            about: routeListing.about,
                            locations_count: routeListing.locations_count,
                            machines_count: routeListing.machines_count,
                            annual_revenue: routeListing.annual_revenue,
                            asking_price: routeListing.asking_price,
                            seller_financing: routeListing.seller_financing,
                            is_active: routeListing.is_active,
                            listed_at: routeListing.listed_at,
                        }}
                        onCancel={() => router.visit(routeListings.index().url)}
                    />
                </div>
            </div>
        </>
    );
}

EditRouteListing.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Route listings', href: routeListings.index() },
        { title: 'Edit', href: '#' },
    ],
};
