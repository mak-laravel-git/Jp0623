<?php

namespace Database\Factories;

use App\Models\TwilioSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TwilioSetting>
 */
class TwilioSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enabled' => true,
            'sid' => 'AC'.fake()->regexify('[0-9a-f]{32}'),
            'auth_token' => fake()->regexify('[0-9a-f]{32}'),
            'from_number' => '+15005550006',
            'messaging_service_sid' => 'MG'.fake()->regexify('[0-9a-f]{32}'),
            'lead_radius_miles' => 100,
        ];
    }
}
