import { SeoHead } from '@/components/frontend/seo-head';
import { CitiesSection } from '@/components/home/cities-section';
import { FaqSection } from '@/components/home/faq-section';
import { FeaturedMachinesSection } from '@/components/home/featured-machines-section';
import { HeroSection } from '@/components/home/hero-section';
import { HowItWorksSection } from '@/components/home/how-it-works-section';
import { IndustriesSection } from '@/components/home/industries-section';
import { ProductsTeaserSection } from '@/components/home/products-teaser-section';
import { RequestSection } from '@/components/home/request-section';
import { ResourcesSection } from '@/components/home/resources-section';
import { ReviewsSection } from '@/components/home/reviews-section';
import { ServicesSection } from '@/components/home/services-section';
import type { HomePageProps } from '@/types/frontend';

export default function Home({
    hero,
    howItWorks,
    services,
    featuredMachines,
    products,
    requestForm,
    reviews,
    industries,
    faqs,
    articles,
    cities,
}: HomePageProps) {
    return (
        <>
            <SeoHead
                title="Home"
                description="Nationwide vending services with free machine installation, stocking and service. Vending machines, micro markets, Sandstar AI coolers and office coffee for businesses in all 50 states."
            />

            <HeroSection hero={hero} />
            <HowItWorksSection data={howItWorks} />
            <ServicesSection data={services} />
            <FeaturedMachinesSection data={featuredMachines} />
            <ProductsTeaserSection data={products} />
            <RequestSection data={requestForm} />
            {reviews.items.length > 0 && <ReviewsSection data={reviews} />}
            <IndustriesSection data={industries} />
            <FaqSection data={faqs} />
            <ResourcesSection data={articles} />
            <CitiesSection data={cities} />
        </>
    );
}
