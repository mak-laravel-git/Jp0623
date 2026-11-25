import { LinkImageCardGrid } from '@/components/frontend/link-image-card-grid';
import { PageHero } from '@/components/frontend/page-hero';
import { SeoHead } from '@/components/frontend/seo-head';
import type { IndustriesPageProps } from '@/types/frontend';

export default function Industries({ hero, items }: IndustriesPageProps) {
    return (
        <>
            <SeoHead
                title="Industries"
                description="Vending, coffee and micro markets set up for how your location works—across offices, warehouses, schools, hospitals and more."
            />

            <PageHero title={hero.title} lead={hero.lead} />
            <LinkImageCardGrid items={items} />
        </>
    );
}
