<?php

namespace Database\Factories;

use App\Models\HeroSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeroSection>
 */
class HeroSectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'eyebrow' => fake()->words(3, true),
            'title_before' => fake()->sentence(3),
            'title_accent' => fake()->words(3, true).'.',
            'lede' => fake()->sentence(12),
            'bullets' => [fake()->sentence(4), fake()->sentence(4)],
            'cta_label' => 'Request a free machine',
            'cta_href' => '#request-sec',
            'trust_stars' => '★★★★★',
            'trust_text' => fake()->sentence(5),
            'trust_reviews_label' => 'Read reviews',
            'trust_reviews_href' => '#reviews-sec',
        ];
    }
}
