<?php

use App\Enums\RoleEnum;
use App\Models\RouteListing;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::SUPER_ADMIN->value);
});

/**
 * @return array<string, mixed>
 */
function routeListingPayload(array $overrides = []): array
{
    return array_merge([
        'city' => 'Dallas–Fort Worth, TX',
        'about' => 'Office & light industrial',
        'locations_count' => 14,
        'machines_count' => 22,
        'annual_revenue' => 168000,
        'asking_price' => 210000,
        'seller_financing' => true,
        'is_active' => true,
        'listed_at' => '2026-10-25',
    ], $overrides);
}

test('guests cannot access route listings', function () {
    $this->get(route('admin.route-listings.index'))->assertRedirect(route('login'));
});

test('users without permission are forbidden from managing route listings', function () {
    $plain = User::factory()->create();
    $plain->assignRole(RoleEnum::USER->value);

    $this->actingAs($plain)->get(route('admin.route-listings.index'))->assertForbidden();
    $this->actingAs($plain)->post(route('admin.route-listings.store'), routeListingPayload())->assertForbidden();

    expect(RouteListing::query()->count())->toBe(0);
});

test('super admin can view the route listings list and search it', function () {
    RouteListing::factory()->create(['city' => 'Orlando, FL']);
    RouteListing::factory()->create(['city' => 'Phoenix, AZ']);

    $this->actingAs($this->admin)
        ->get(route('admin.route-listings.index', ['search' => 'Orlando']))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/route-listings/index')
                ->has('routeListings.data', 1)
                ->where('routeListings.data.0.city', 'Orlando, FL')
        );
});

test('a route listing can be created', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.route-listings.store'), routeListingPayload())
        ->assertRedirect(route('admin.route-listings.index'));

    $listing = RouteListing::query()->sole();

    expect($listing->city)->toBe('Dallas–Fort Worth, TX')
        ->and($listing->asking_price)->toBe(210000)
        ->and($listing->seller_financing)->toBeTrue()
        ->and($listing->is_active)->toBeTrue();
});

test('a route listing requires its core fields', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.route-listings.store'), [])
        ->assertSessionHasErrors(['city', 'about', 'locations_count', 'machines_count', 'annual_revenue', 'asking_price']);

    expect(RouteListing::query()->count())->toBe(0);
});

test('a route listing can be updated', function () {
    $listing = RouteListing::factory()->create(['is_active' => true]);

    $this->actingAs($this->admin)
        ->put(route('admin.route-listings.update', $listing), routeListingPayload([
            'city' => 'Charlotte, NC',
            'is_active' => false,
        ]))
        ->assertRedirect(route('admin.route-listings.index'));

    $listing->refresh();

    expect($listing->city)->toBe('Charlotte, NC')
        ->and($listing->is_active)->toBeFalse();
});

test('a route listing can be deleted', function () {
    $listing = RouteListing::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.route-listings.destroy', $listing))
        ->assertRedirect();

    $this->assertModelMissing($listing);
});

test('members only see active route listings on the established locations page', function () {
    RouteListing::factory()->create(['city' => 'Orlando, FL', 'seller_financing' => true]);
    RouteListing::factory()->inactive()->create(['city' => 'Hidden, TX']);

    $member = User::factory()->create();
    $member->assignRole(RoleEnum::USER->value);

    $this->actingAs($member)
        ->get(route('routes.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('operator/routes/index')
                ->has('routes', 1)
                ->where('routes.0.city', 'Orlando, FL')
                ->where('routes.0.fin', 'Yes')
        );
});

test('members see the newest listings first with the fields the price filter needs', function () {
    RouteListing::factory()->create(['city' => 'Older, TX', 'listed_at' => '2026-09-01', 'asking_price' => 84000]);
    RouteListing::factory()->create(['city' => 'Newer, FL', 'listed_at' => '2026-10-25', 'asking_price' => 210000]);

    $member = User::factory()->create();
    $member->assignRole(RoleEnum::USER->value);

    $this->actingAs($member)
        ->get(route('routes.index'))
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('routes.0.city', 'Newer, FL')
                ->where('routes.0.ask', 210000)
                ->where('routes.1.city', 'Older, TX')
                ->where('routes.1.ask', 84000)
        );
});

test('members cannot create route listings', function () {
    $member = User::factory()->create();
    $member->assignRole(RoleEnum::USER->value);

    $this->actingAs($member)
        ->post(route('admin.route-listings.store'), routeListingPayload())
        ->assertForbidden();

    $this->actingAs($member)->get('/routes/sell')->assertNotFound();
});
