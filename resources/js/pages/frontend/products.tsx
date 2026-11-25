import { PageHero } from '@/components/frontend/page-hero';
import { SeoHead } from '@/components/frontend/seo-head';
import { CatalogSection } from '@/components/products/catalog-section';
import type { ProductsPageProps } from '@/types/frontend';

export default function Products({ hero, catalog }: ProductsPageProps) {
    return (
        <>
            <SeoHead
                title="Snacks & Beverages"
                description="Popular brands, healthier picks and local favorites for vending machines, micro markets and office coffee."
            />

            <PageHero title={hero.title} lead={hero.lead} cta={hero.cta} />
            <CatalogSection data={catalog} />
        </>
    );
}
