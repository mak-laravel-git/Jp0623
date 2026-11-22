<?php

namespace Database\Factories;

use App\Models\MembershipCustomPrice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipCustomPrice>
 */
class MembershipCustomPriceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'monthly_price' => fake()->numberBetween(5, 99),
            'user_id' => null,
            'created_by' => null,
            'notified_at' => null,
        ];
    }

    /**
     * A price for an existing member (the email follows the user).
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'email' => mb_strtolower($user->email),
            'user_id' => $user->id,
        ]);
    }
}
