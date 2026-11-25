import { ContactSection } from '@/components/contact/contact-section';
import { PageHero } from '@/components/frontend/page-hero';
import { SeoHead } from '@/components/frontend/seo-head';
import type { ContactPageProps } from '@/types/frontend';

export default function Contact({ hero, panel, form }: ContactPageProps) {
    return (
        <>
            <SeoHead
                title="Contact Us"
                description="Contact Vending Machine Location Services. Complete the form or email our team."
            />

            <PageHero title={hero.title} lead={hero.lead} />
            <ContactSection panel={panel} form={form} />
        </>
    );
}
