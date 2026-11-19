<?php

use App\Enums\RoleEnum;
use App\Enums\RouteInquiryStatus;
use App\Models\RouteListingInquiry;
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

test('guests cannot access route inquiries', function () {
    $inquiry = RouteListingInquiry::factory()->create();

    $this->get(route('admin.route-inquiries.index'))->assertRedirect(route('login'));
    $this->get(route('admin.route-inquiries.show', $inquiry))->assertRedirect(route('login'));
});

test('members cannot access or change route inquiries', function () {
    $inquiry = RouteListingInquiry::factory()->create();
    $member = makeOperator();

    $this->actingAs($member)->get(route('admin.route-inquiries.index'))->assertForbidden();
    $this->actingAs($member)->get(route('admin.route-inquiries.show', $inquiry))->assertForbidden();
    $this->actingAs($member)
        ->put(route('admin.route-inquiries.update', $inquiry), ['status' => RouteInquiryStatus::CLOSED->value])
        ->assertForbidden();

    expect($inquiry->refresh()->status)->toBe(RouteInquiryStatus::PENDING);
});

test('the member who sent an inquiry cannot open it in the admin area', function () {
    $inquiry = RouteListingInquiry::factory()->create();

    $this->actingAs($inquiry->user)
        ->get(route('admin.route-inquiries.show', $inquiry))
        ->assertForbidden();
});

test('admin can view all inquiries', function () {
    RouteListingInquiry::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.route-inquiries.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/route-inquiries/index')
                ->has('inquiries.data', 3)
                ->has('filters')
        );
});

test('admin can filter inquiries by status and search', function () {
    RouteListingInquiry::factory()->create(['contact_name' => 'Pending Pat']);
    RouteListingInquiry::factory()->withStatus(RouteInquiryStatus::CONTACTED)->create(['contact_name' => 'Contacted Cam']);

    $this->actingAs($this->admin)
        ->get(route('admin.route-inquiries.index', ['status' => 'contacted']))
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('inquiries.data', 1)
                ->where('inquiries.data.0.contact_name', 'Contacted Cam')
                ->where('filters.status', 'contacted')
        );

    $this->actingAs($this->admin)
        ->get(route('admin.route-inquiries.index', ['search' => 'Pat']))
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('inquiries.data', 1)
                ->where('inquiries.data.0.contact_name', 'Pending Pat')
        );
});

test('admin can view an inquiry with its listing and member details', function () {
    $inquiry = RouteListingInquiry::factory()->create([
        'contact_name' => 'Alex Jordan',
        'contact_phone' => '(555) 010-0000',
        'message' => 'Please send financials.',
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.route-inquiries.show', $inquiry))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/route-inquiries/show')
                ->where('inquiry.contact_name', 'Alex Jordan')
                ->where('inquiry.contact_phone', '(555) 010-0000')
                ->where('inquiry.message', 'Please send financials.')
                ->where('inquiry.status', 'pending')
                ->where('inquiry.route_listing.id', $inquiry->route_listing_id)
        );
});

test('admin can update an inquiry status', function () {
    $inquiry = RouteListingInquiry::factory()->create();

    foreach ([RouteInquiryStatus::CONTACTED, RouteInquiryStatus::CLOSED, RouteInquiryStatus::PENDING] as $status) {
        $this->actingAs($this->admin)
            ->put(route('admin.route-inquiries.update', $inquiry), ['status' => $status->value])
            ->assertRedirect();

        expect($inquiry->refresh()->status)->toBe($status);
    }
});

test('an invalid inquiry status is rejected', function () {
    $inquiry = RouteListingInquiry::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.route-inquiries.update', $inquiry), ['status' => 'archived'])
        ->assertSessionHasErrors('status');

    expect($inquiry->refresh()->status)->toBe(RouteInquiryStatus::PENDING);
});
