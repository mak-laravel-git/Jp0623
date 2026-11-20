<?php

namespace Database\Seeders;

use App\Models\HeroSection;
use Illuminate\Database\Seeder;

class HeroSectionSeeder extends Seeder
{
    /**
     * Seed the home page hero. It is edited from the admin dashboard afterwards, so an
     * existing hero (and its images) is never overwritten by re-seeding.
     */
    public function run(): void
    {
        $hero = HeroSection::query()->firstOrCreate([], [
            'eyebrow' => 'Vending management · Nationwide',
            'title_before' => 'Vending Machine Location Services,',
            'title_accent' => 'for every location.',
            'lede' => 'We find the vendor, schedule the install and keep the machines stocked and running. You deal with one contact for every site you have.',
            'bullets' => [
                'No equipment fees, install charges or service costs',
                'Product mix chosen for each location',
                'One building or a 500-site rollout, in all 50 states',
            ],
            'cta_label' => 'Request a free machine',
            'cta_href' => '#request-sec',
            'trust_stars' => '★★★★★',
            'trust_text' => 'Used by thousands of businesses across the U.S.',
            'trust_reviews_label' => 'Read reviews',
            'trust_reviews_href' => '#reviews-sec',
        ]);

        if ($hero->images()->exists()) {
            return;
        }

        foreach ($this->images() as $index => $image) {
            $hero->images()->create($image + ['sort_order' => $index]);
        }
    }

    /**
     * @return list<array{image: string, alt: string}>
     */
    private function images(): array
    {
        return [
            [
                'image' => '/images/hero/Vendera-Black-Glass-Display-Cooler.png',
                'alt' => 'Snack and drink combo machine',
            ],
            [
                'image' => '/images/machines/Vendera-Tap-Grab-Go-Cabinet.png',
                'alt' => 'Alpine snack and beverage machine',
            ],
            [
                'image' => '/images/hero/tradational-vending.png',
                'alt' => 'Traditional vending machine',
            ],
            [
                'image' => '/images/machines/Vendera-Tap-Grab-Go-Cabinet.png',
                'alt' => 'Sandstar AI cooler',
            ],
            [
                'image' => '/images/machines/micro-market.jpg',
                'alt' => 'Micro market setup',
            ],
        ];
    }
}
