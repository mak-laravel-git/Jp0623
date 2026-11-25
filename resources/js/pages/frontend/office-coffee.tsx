import { PageHero } from '@/components/frontend/page-hero';
import { SeoHead } from '@/components/frontend/seo-head';
import { OfficeCoffeeFaqSection } from '@/components/office-coffee/faq-section';
import { OfficeCoffeeMachinesSection } from '@/components/office-coffee/machines-section';
import { OfficeCoffeeRequestSection } from '@/components/office-coffee/request-section';
import { OfficeCoffeeServiceSection } from '@/components/office-coffee/service-section';
import { OfficeCoffeeTypesSection } from '@/components/office-coffee/types-section';
import type { OfficeCoffeePageProps } from '@/types/frontend';

export default function OfficeCoffee({
    hero,
    machines,
    types,
    service,
    requestForm,
    faqs,
}: OfficeCoffeePageProps) {
    return (
        <>
            <SeoHead
                title="Office Coffee"
                description="Free office coffee brewers, delivery and installation, with supplies restocked on your schedule."
            />

            <PageHero
                title={hero.title}
                lead={hero.lead}
                cta={hero.cta}
                image={hero.image}
            />
            <OfficeCoffeeMachinesSection data={machines} />
            <OfficeCoffeeTypesSection data={types} />
            <OfficeCoffeeServiceSection data={service} />
            <OfficeCoffeeFaqSection data={faqs} />
            <OfficeCoffeeRequestSection data={requestForm} />
        </>
    );
}
