import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Route } from 'lucide-react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { RouteListingForm } from '@/components/admin/route-listing-form';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import routeListings from '@/routes/admin/route-listings';

export default function CreateRouteListing() {
    return (
        <>
            <Head title="Create listing" />

            <div className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <AdminPageHeader
                    title="Create listing"
                    description="Add an established location or route for sale. Active listings are visible to all members."
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
                        action={routeListings.store()}
                        onCancel={() => router.visit(routeListings.index().url)}
                    />
                </div>
            </div>
        </>
    );
}

CreateRouteListing.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Route listings', href: routeListings.index() },
        { title: 'Create', href: routeListings.create() },
    ],
};
