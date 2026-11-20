<?php

namespace Database\Factories;

use App\Models\HeroImage;
use App\Models\HeroSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeroImage>
 */
class HeroImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hero_section_id' => HeroSection::factory(),
            'image' => '/images/machines/micro-market.jpg',
            'alt' => fake()->words(3, true),
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }

    /**
     * An image uploaded from the admin (stored on the public disk) rather than a default shipped file.
     */
    public function uploaded(string $path = 'hero/uploaded.jpg'): static
    {
        return $this->state(fn (array $attributes): array => [
            'image' => $path,
        ]);
    }
}
