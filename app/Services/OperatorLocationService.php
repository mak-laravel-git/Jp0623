<?php

namespace App\Services;

use App\Models\OperatorProfile;
use Illuminate\Support\Carbon;

/**
 * Reads and saves an operator's lead area: the primary city (which drives lead
 * alerts and distances) and any additional service areas.
 *
 * The primary city can be changed {@see self::MAX_PRIMARY_CITY_CHANGES} times
 * in any rolling {@see self::CHANGE_WINDOW_DAYS} days.
 */
class OperatorLocationService
{
    public const MAX_PRIMARY_CITY_CHANGES = 5;

    public const CHANGE_WINDOW_DAYS = 90;

    /**
     * Props for the location settings page.
     *
     * @return array{
     *     primaryCity: string,
     *     location: array{label: string, address: string|null, city: string, state: string, postal_code: string|null, latitude: float, longitude: float}|null,
     *     additionalServiceAreas: string,
     *     changesRemaining: int,
     *     changesTotal: int,
     *     changeWindowDays: int
     * }
     */
    public function settingsFor(?OperatorProfile $profile): array
    {
        $hasLocation = $profile !== null
            && filled($profile->city)
            && filled($profile->state)
            && $profile->latitude !== null
            && $profile->longitude !== null;

        return [
            'primaryCity' => $profile?->primary_city ?: 'Not set',
            'location' => $hasLocation ? [
                'label' => (string) $profile->primary_city,
                'address' => $profile->address,
                'city' => (string) $profile->city,
                'state' => (string) $profile->state,
                'postal_code' => $profile->postal_code,
                'latitude' => (float) $profile->latitude,
                'longitude' => (float) $profile->longitude,
            ] : null,
            'additionalServiceAreas' => (string) ($profile?->additional_service_areas ?? ''),
            'changesRemaining' => $profile !== null ? $this->changesRemaining($profile) : self::MAX_PRIMARY_CITY_CHANGES,
            'changesTotal' => self::MAX_PRIMARY_CITY_CHANGES,
            'changeWindowDays' => self::CHANGE_WINDOW_DAYS,
        ];
    }

    /**
     * How many primary city changes the operator has left in the current window.
     */
    public function changesRemaining(OperatorProfile $profile): int
    {
        return max(self::MAX_PRIMARY_CITY_CHANGES - count($this->recentChanges($profile)), 0);
    }

    /**
     * The label stored as `primary_city` for a city and state, e.g. "Houston, TX".
     */
    public function primaryCityLabel(string $city, string $state): string
    {
        return trim($city).', '.trim($state);
    }

    /**
     * Whether saving this city and state would change the operator's primary city.
     */
    public function changesPrimaryCity(OperatorProfile $profile, string $city, string $state): bool
    {
        return strcasecmp((string) $profile->primary_city, $this->primaryCityLabel($city, $state)) !== 0;
    }

    /**
     * Save the lead area. Only an actual change of primary city uses up one of
     * the allowed changes; editing the additional areas alone is free.
     *
     * @param  array{city: string, state: string, address?: string|null, postal_code?: string|null, latitude: float|int|string, longitude: float|int|string, additional_service_areas?: string|null}  $data
     */
    public function update(OperatorProfile $profile, array $data): OperatorProfile
    {
        $changes = $this->recentChanges($profile);

        if ($this->changesPrimaryCity($profile, $data['city'], $data['state'])) {
            $changes[] = now()->toIso8601String();
        }

        $profile->forceFill([
            'primary_city' => $this->primaryCityLabel($data['city'], $data['state']),
            'city' => trim($data['city']),
            'state' => trim($data['state']),
            'address' => filled($data['address'] ?? null) ? trim((string) $data['address']) : null,
            'postal_code' => filled($data['postal_code'] ?? null) ? trim((string) $data['postal_code']) : null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'additional_service_areas' => filled($data['additional_service_areas'] ?? null)
                ? trim((string) $data['additional_service_areas'])
                : null,
            'primary_city_changes' => $changes,
        ])->save();

        return $profile;
    }

    /**
     * Timestamps of primary city changes that are still inside the window.
     *
     * @return list<string>
     */
    private function recentChanges(OperatorProfile $profile): array
    {
        $cutoff = now()->subDays(self::CHANGE_WINDOW_DAYS);

        return array_values(array_filter(
            (array) ($profile->primary_city_changes ?? []),
            fn (string $changedAt): bool => Carbon::parse($changedAt)->greaterThan($cutoff),
        ));
    }
}
