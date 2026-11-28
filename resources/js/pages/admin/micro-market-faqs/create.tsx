import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CircleHelp } from 'lucide-react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { MicroMarketFaqForm } from '@/components/admin/micro-market-faq-form';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import microMarketFaqs from '@/routes/admin/micro-market-faqs';

export default function CreateMicroMarketFaq({
    defaultSortOrder,
}: {
    defaultSortOrder: number;
}) {
    return (
        <>
            <Head title="Create micro market FAQ" />

            <div className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <AdminPageHeader
                    title="Create micro market FAQ"
                    description="Add a question and answer for the micro market FAQ section."
                    icon={CircleHelp}
                >
                    <Button variant="outline" asChild>
                        <Link href={microMarketFaqs.index().url}>
                            <ArrowLeft className="h-4 w-4" /> Back to FAQs
                        </Link>
                    </Button>
                </AdminPageHeader>

                <div className="rounded-xl border bg-card p-6 shadow-sm">
                    <MicroMarketFaqForm
                        action={microMarketFaqs.store()}
                        defaults={{
                            question: '',
                            answer: '',
                            sort_order: defaultSortOrder,
                            is_active: true,
                        }}
                        onCancel={() =>
                            router.visit(microMarketFaqs.index().url)
                        }
                    />
                </div>
            </div>
        </>
    );
}

CreateMicroMarketFaq.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Micro market FAQs', href: microMarketFaqs.index() },
        { title: 'Create', href: microMarketFaqs.create() },
    ],
};
