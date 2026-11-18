<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

/**
 * @param  array<string, mixed>  $profile
 */
function makeLocatedOperator(array $profile = []): User
{
    return makeOperator($profile + [
        'primary_city' => 'Houston, TX',
        'city' => 'Houston',
        'state' => 'TX',
        'latitude' => 29.7604267,
        'longitude' => -95.3698028,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function locationPayload(array $overrides = []): array
{
    return $overrides + [
        'city' => 'Dallas',
        'state' => 'TX',
        'latitude' => 32.7766642,
        'longitude' => -96.7969879,
        'additional_service_areas' => 'Austin, TX; San Antonio, TX',
    ];
}

test('the location page shows the saved city, additional areas and remaining changes', function () {
    $operator = makeLocatedOperator(['additional_service_areas' => 'Austin, TX']);

    $this->actingAs($operator)
        ->get(route('account.location'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/account/location')
            ->where('primaryCity', 'Houston, TX')
            ->where('additionalServiceAreas', 'Austin, TX')
            ->where('location.city', 'Houston')
            ->where('changesRemaining', 5)
            ->where('changesTotal', 5)
            ->where('changeWindowDays', 90));
});

test('saving a new primary city updates the location and uses one change', function () {
    $operator = makeLocatedOperator();

    $this->actingAs($operator)
        ->put(route('account.location.update'), locationPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $profile = $operator->operatorProfile->refresh();

    expect($profile->primary_city)->toBe('Dallas, TX')
        ->and($profile->city)->toBe('Dallas')
        ->and((float) $profile->latitude)->toBe(32.7766642)
        ->and((float) $profile->longitude)->toBe(-96.7969879)
        ->and($profile->additional_service_areas)->toBe('Austin, TX; San Antonio, TX')
        ->and($profile->primary_city_changes)->toHaveCount(1);

    $this->actingAs($operator)
        ->get(route('account.location'))
        ->assertInertia(fn (Assert $page) => $page->where('changesRemaining', 4));
});

test('saving only the additional service areas does not use a change', function () {
    $operator = makeLocatedOperator();

    $this->actingAs($operator)
        ->put(route('account.location.update'), locationPayload([
            'city' => 'Houston',
            'latitude' => 29.7604267,
            'longitude' => -95.3698028,
        ]))
        ->assertSessionHasNoErrors();

    $profile = $operator->operatorProfile->refresh();

    expect($profile->primary_city)->toBe('Houston, TX')
        ->and($profile->additional_service_areas)->toBe('Austin, TX; San Antonio, TX')
        ->and($profile->primary_city_changes)->toBeEmpty();
});

test('a blank additional service areas value clears the saved areas', function () {
    $operator = makeLocatedOperator(['additional_service_areas' => 'Austin, TX']);

    $this->actingAs($operator)
        ->put(route('account.location.update'), locationPayload([
            'city' => 'Houston',
            'additional_service_areas' => '',
        ]))
        ->assertSessionHasNoErrors();

    expect($operator->operatorProfile->refresh()->additional_service_areas)->toBeNull();
});

test('a different primary city is rejected once all five changes are used within 90 days', function () {
    $operator = makeLocatedOperator([
        'primary_city_changes' => collect(range(1, 5))
            ->map(fn (int $days): string => now()->subDays($days * 10)->toIso8601String())
            ->all(),
    ]);

    $this->actingAs($operator)
        ->put(route('account.location.update'), locationPayload())
        ->assertSessionHasErrors('city');

    expect($operator->operatorProfile->refresh()->primary_city)->toBe('Houston, TX');
});

test('the same primary city can still be saved after all five changes are used', function () {
    $operator = makeLocatedOperator([
        'primary_city_changes' => array_fill(0, 5, now()->subDays(5)->toIso8601String()),
    ]);

    $this->actingAs($operator)
        ->put(route('account.location.update'), locationPayload([
            'city' => 'Houston',
            'latitude' => 29.7604267,
            'longitude' => -95.3698028,
        ]))
        ->assertSessionHasNoErrors();

    expect($operator->operatorProfile->refresh()->additional_service_areas)->toBe('Austin, TX; San Antonio, TX');
});

test('changes older than 90 days no longer count toward the limit', function () {
    $operator = makeLocatedOperator([
        'primary_city_changes' => array_fill(0, 5, now()->subDays(100)->toIso8601String()),
    ]);

    $this->actingAs($operator)
        ->put(route('account.location.update'), locationPayload())
        ->assertSessionHasNoErrors();

    $profile = $operator->operatorProfile->refresh();

    expect($profile->primary_city)->toBe('Dallas, TX')
        ->and($profile->primary_city_changes)->toHaveCount(1);
});

test('saving requires a city chosen from the suggestions', function () {
    $operator = makeLocatedOperator();

    $this->actingAs($operator)
        ->put(route('account.location.update'), [
            'additional_service_areas' => 'Austin, TX',
        ])
        ->assertSessionHasErrors(['city', 'state', 'latitude', 'longitude']);
});

test('saving a location is forbidden for an account without an operator profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('account.location.update'), locationPayload())
        ->assertForbidden();
});

test('guests are redirected to log in when saving a location', function () {
    $this->put(route('account.location.update'), locationPayload())
        ->assertRedirect(route('login'));
});
