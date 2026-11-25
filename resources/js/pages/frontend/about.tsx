import { AboutCtaSection } from '@/components/about/cta-section';
import { AboutStorySection } from '@/components/about/story-section';
import { PageHero } from '@/components/frontend/page-hero';
import { SeoHead } from '@/components/frontend/seo-head';
import { FaqSection } from '@/components/home/faq-section';
import type { AboutPageProps } from '@/types/frontend';

export default function About({ hero, story, faqs, cta }: AboutPageProps) {
    return (
        <>
            <SeoHead
                title="About Us"
                description="We make vending simple for businesses with one location or hundreds, and we help independent operators grow."
            />

            <PageHero title={hero.title} lead={hero.lead} image={hero.image} />
            <AboutStorySection data={story} />
            {faqs.items.length > 0 && <FaqSection data={faqs} />}
            <AboutCtaSection data={cta} />
        </>
    );
}
