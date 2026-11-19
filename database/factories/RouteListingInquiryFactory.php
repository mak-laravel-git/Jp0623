<?php

namespace Database\Factories;

use App\Enums\RouteInquiryStatus;
use App\Models\RouteListing;
use App\Models\RouteListingInquiry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RouteListingInquiry>
 */
class RouteListingInquiryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $listing = RouteListing::factory()->create();

        return [
            'route_listing_id' => $listing->id,
            'user_id' => User::factory(),
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => fake()->numerify('(###) ###-####'),
            'listing_reference' => 'R-'.$listing->id,
            'listing_city' => $listing->city,
            'listing_asking_price' => $listing->asking_price,
            'message' => fake()->sentence(),
            'status' => RouteInquiryStatus::PENDING,
        ];
    }

    public function forListing(RouteListing $listing): static
    {
        return $this->state(fn (array $attributes): array => [
            'route_listing_id' => $listing->id,
            'listing_reference' => 'R-'.$listing->id,
            'listing_city' => $listing->city,
            'listing_asking_price' => $listing->asking_price,
        ]);
    }

    public function withStatus(RouteInquiryStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }
}
