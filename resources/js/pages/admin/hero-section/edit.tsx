import { Head } from '@inertiajs/react';
import { PanelTop } from 'lucide-react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { HeroSectionForm } from '@/components/admin/hero-section-form';
import { dashboard } from '@/routes';
import heroSectionRoutes from '@/routes/admin/hero-section';
import type { AdminHeroSection } from '@/types/admin';

export default function EditHeroSection({
    heroSection,
}: {
    heroSection: AdminHeroSection;
}) {
    return (
        <>
            <Head title="Hero section" />

            <div className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <AdminPageHeader
                    title="Hero section"
                    description="Edit the text and pictures at the top of the home page."
                    icon={PanelTop}
                />

                <div className="rounded-xl border bg-card p-6 shadow-sm">
                    <HeroSectionForm
                        action={heroSectionRoutes.update()}
                        heroSection={heroSection}
                    />
                </div>
            </div>
        </>
    );
}

EditHeroSection.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Hero section', href: heroSectionRoutes.edit() },
    ],
};
