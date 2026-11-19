<?php

namespace Database\Factories;

use App\Models\RouteListing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RouteListing>
 */
class RouteListingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $annualRevenue = fake()->numberBetween(40000, 200000);

        return [
            'city' => fake()->city().', '.fake()->stateAbbr(),
            'about' => fake()->sentence(3),
            'locations_count' => fake()->numberBetween(3, 20),
            'machines_count' => fake()->numberBetween(3, 30),
            'annual_revenue' => $annualRevenue,
            'asking_price' => (int) round($annualRevenue * fake()->randomFloat(2, 1, 1.5)),
            'seller_financing' => fake()->boolean(),
            'is_active' => true,
            'listed_at' => fake()->dateTimeBetween('-3 months'),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
