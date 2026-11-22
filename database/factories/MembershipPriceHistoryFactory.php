<?php

namespace Database\Factories;

use App\Models\MembershipPriceHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipPriceHistory>
 */
class MembershipPriceHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'user_id' => null,
            'action' => MembershipPriceHistory::ACTION_CREATED,
            'old_price' => null,
            'new_price' => fake()->numberBetween(5, 50),
            'changed_by' => null,
            'email_sent' => false,
        ];
    }
}
