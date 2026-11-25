import { PageHero } from '@/components/frontend/page-hero';
import { SeoHead } from '@/components/frontend/seo-head';
import { CitiesSection } from '@/components/home/cities-section';
import type { CitiesData } from '@/types/frontend';

type CitiesPageProps = {
    hero: { title: string; lead: string };
    cities: CitiesData;
};

export default function CitiesPage({ hero, cities }: CitiesPageProps) {
    return (
        <>
            <SeoHead title="Cities We Serve" description={hero.lead} />

            <PageHero title={hero.title} lead={hero.lead} />
            <CitiesSection data={cities} />
        </>
    );
}
