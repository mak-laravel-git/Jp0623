<?php

namespace Database\Factories;

use App\Models\SquareSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SquareSetting>
 */
class SquareSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'environment' => 'sandbox',
            'application_id' => 'sandbox-sq0idb-'.fake()->bothify('????????'),
            'location_id' => 'LOC'.fake()->bothify('#####'),
            'webhook_url' => null,
            'access_token' => 'EAAA'.fake()->bothify('????????????'),
            'webhook_signature_key' => fake()->bothify('????????????????'),
        ];
    }
}
