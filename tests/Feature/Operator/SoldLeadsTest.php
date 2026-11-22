<?php

use App\Models\CoffeeServiceRequest;
use App\Models\LeadPurchase;
use App\Models\VendingRequest;
use App\Services\LeadService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

/**
 * An operator based in Houston, TX (the default location of the lead factories).
 */
function houstonOperator(array $profile = [])
{
    return makeOperator(['latitude' => 29.7604, 'longitude' => -95.3698, 'primary_city' => 'Houston, TX'] + $profile);
}

test('guests are redirected from sold leads', function () {
    $this->get(route('leads.sold'))->assertRedirect(route('login'));
});

test('paid leads sold within 100 miles are listed', function () {
    $near = VendingRequest::factory()->create(['city' => 'Houston', 'business_name' => 'Near Co']);
    $dallas = VendingRequest::factory()->create(['city' => 'Dallas', 'latitude' => 32.7767, 'longitude' => -96.7970]);

    LeadPurchase::factory()->create(['leadable_id' => $near->id, 'price' => 150]);
    LeadPurchase::factory()->create(['leadable_id' => $dallas->id]);

    $this->actingAs(houstonOperator())
        ->get(route('leads.sold'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/leads/sold')
            ->has('soldLeads', 1)
            ->where('soldLeads.0.id', 'V-'.$near->id)
            ->where('soldLeads.0.city', 'Houston')
            ->where('soldLeads.0.price', 150)
            ->where('soldLeads.0.mine', false)
            ->where('hasLocation', true)
            ->where('radius', 100)
        );
});

test('only paid purchases count as sold and unsold leads are not listed', function () {
    $pending = VendingRequest::factory()->create();
    $failed = VendingRequest::factory()->create();
    $refunded = VendingRequest::factory()->create();
    VendingRequest::factory()->create();

    LeadPurchase::factory()->pending()->create(['leadable_id' => $pending->id]);
    LeadPurchase::factory()->failed()->create(['leadable_id' => $failed->id]);
    LeadPurchase::factory()->create([
        'leadable_id' => $refunded->id,
        'status' => LeadPurchase::STATUS_REFUNDED,
        'lead_lock' => null,
    ]);

    $this->actingAs(houstonOperator())
        ->get(route('leads.sold'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('soldLeads', 0));
});

test('vending and coffee leads both appear, newest sold first', function () {
    $older = VendingRequest::factory()->create();
    $newer = CoffeeServiceRequest::factory()->create();

    LeadPurchase::factory()->create(['leadable_id' => $older->id, 'purchased_at' => now()->subDays(3)]);
    LeadPurchase::factory()->create(['leadable_type' => 'coffee', 'leadable_id' => $newer->id, 'purchased_at' => now()]);

    $this->actingAs(houstonOperator())
        ->get(route('leads.sold'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('soldLeads', 2)
            ->where('soldLeads.0.id', 'C-'.$newer->id)
            ->where('soldLeads.0.type', 'Office coffee')
            ->where('soldLeads.0.sold', 'Today')
            ->where('soldLeads.1.id', 'V-'.$older->id)
        );
});

test('the list is capped at the requested limit', function () {
    foreach (VendingRequest::factory()->count(4)->create() as $lead) {
        LeadPurchase::factory()->create(['leadable_id' => $lead->id]);
    }

    expect(app(LeadService::class)->soldNear(houstonOperator(), 3))->toHaveCount(3);
});

test('sold leads never expose business, contact or buyer details', function () {
    $lead = VendingRequest::factory()->create([
        'business_name' => 'Secret Corp',
        'address' => '99 Hidden Ave',
        'address_or_zip' => '99 Hidden Ave, Houston',
        'contact_name' => 'Hidden Person',
        'email' => 'hidden@example.com',
        'phone' => '555-0100',
        'postal_code' => '77002',
    ]);
    $buyer = makeOperator(['first_name' => 'Hidden', 'last_name' => 'Buyer', 'company_name' => 'Buyer Vending LLC']);

    LeadPurchase::factory()->create(['leadable_id' => $lead->id, 'user_id' => $buyer->id]);

    $response = $this->actingAs(houstonOperator())->get(route('leads.sold'));
    $response->assertOk();

    $props = $response->viewData('page')['props'];

    expect(array_keys($props['soldLeads'][0]))
        ->toEqualCanonicalizing(['id', 'type', 'city', 'st', 'zip', 'dist', 'people', 'wants', 'price', 'sold', 'mine'])
        ->and($props['soldLeads'][0]['zip'])->toBe('770');

    $body = json_encode($props);

    foreach (['Secret Corp', '99 Hidden Ave', 'Hidden Person', 'hidden@example.com', '555-0100', '77002', 'Hidden Buyer', 'Buyer Vending LLC', $buyer->email, 'receipt', 'sqpay_'] as $secret) {
        expect($body)->not->toContain($secret);
    }
});

test('an operator without a saved location sees an empty list and a prompt to set one', function () {
    $lead = VendingRequest::factory()->create();
    LeadPurchase::factory()->create(['leadable_id' => $lead->id]);

    $this->actingAs(makeOperator())
        ->get(route('leads.sold'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('soldLeads', 0)
            ->where('hasLocation', false)
        );
});

test('an operator sees their own purchase marked as theirs', function () {
    $operator = houstonOperator();
    $mine = VendingRequest::factory()->create();
    $theirs = VendingRequest::factory()->create();

    LeadPurchase::factory()->create(['leadable_id' => $mine->id, 'user_id' => $operator->id, 'purchased_at' => now()]);
    LeadPurchase::factory()->create(['leadable_id' => $theirs->id, 'purchased_at' => now()->subHour()]);

    $this->actingAs($operator)
        ->get(route('leads.sold'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('soldLeads', 2)
            ->where('soldLeads.0.mine', true)
            ->where('soldLeads.1.mine', false)
        );
});
