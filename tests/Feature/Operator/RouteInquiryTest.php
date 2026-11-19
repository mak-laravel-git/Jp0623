<?php

use App\Enums\RouteInquiryStatus;
use App\Mail\RouteInquiryReceivedMail;
use App\Models\RouteListing;
use App\Models\RouteListingInquiry;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    config(['route-listings.inquiry_notification_email' => 'sales@example.test']);
});

/**
 * @return array<string, mixed>
 */
function inquiryPayload(array $overrides = []): array
{
    return array_merge([
        'contact_name' => 'Alex Jordan',
        'contact_email' => 'alex@example.test',
        'contact_phone' => '(555) 010-0000',
        'message' => 'I am interested in this route. Please send more details.',
    ], $overrides);
}

test('the routes page gives the member contact details to prefill the inquiry form', function () {
    $member = makeOperator(['first_name' => 'Alex', 'last_name' => 'Jordan', 'phone' => '(555) 010-0000']);
    RouteListing::factory()->create();

    $this->actingAs($member)
        ->get(route('routes.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('operator/routes/index')
                ->where('memberContact.name', 'Alex Jordan')
                ->where('memberContact.email', $member->email)
                ->where('memberContact.phone', '(555) 010-0000')
                ->has('routes.0.listing_id')
        );
});

test('a member can send an inquiry about an active listing', function () {
    Mail::fake();

    $member = makeOperator();
    $listing = RouteListing::factory()->create(['city' => 'Orlando, FL', 'asking_price' => 72000]);

    $this->actingAs($member)
        ->post(route('routes.inquiries.store', $listing), inquiryPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $inquiry = RouteListingInquiry::query()->sole();

    expect($inquiry->user_id)->toBe($member->id)
        ->and($inquiry->route_listing_id)->toBe($listing->id)
        ->and($inquiry->contact_name)->toBe('Alex Jordan')
        ->and($inquiry->contact_email)->toBe('alex@example.test')
        ->and($inquiry->listing_reference)->toBe('R-'.$listing->id)
        ->and($inquiry->listing_city)->toBe('Orlando, FL')
        ->and($inquiry->listing_asking_price)->toBe(72000)
        ->and($inquiry->status)->toBe(RouteInquiryStatus::PENDING);
});

test('a member cannot send an inquiry as another user', function () {
    Mail::fake();

    $member = makeOperator();
    $other = makeOperator();
    $listing = RouteListing::factory()->create();

    $this->actingAs($member)
        ->post(route('routes.inquiries.store', $listing), inquiryPayload([
            'user_id' => $other->id,
            'status' => RouteInquiryStatus::CLOSED->value,
        ]))
        ->assertRedirect();

    $inquiry = RouteListingInquiry::query()->sole();

    expect($inquiry->user_id)->toBe($member->id)
        ->and($inquiry->status)->toBe(RouteInquiryStatus::PENDING);
});

test('guests cannot send inquiries', function () {
    $listing = RouteListing::factory()->create();

    $this->post(route('routes.inquiries.store', $listing), inquiryPayload())
        ->assertRedirect(route('login'));

    expect(RouteListingInquiry::query()->count())->toBe(0);
});

test('a member cannot send an inquiry about an inactive listing', function () {
    $listing = RouteListing::factory()->inactive()->create();

    $this->actingAs(makeOperator())
        ->post(route('routes.inquiries.store', $listing), inquiryPayload())
        ->assertNotFound();

    expect(RouteListingInquiry::query()->count())->toBe(0);
});

test('a member cannot send an inquiry about a listing that does not exist', function () {
    $this->actingAs(makeOperator())
        ->post(route('routes.inquiries.store', 999999), inquiryPayload())
        ->assertNotFound();
});

test('the inquiry requires valid contact details and a message', function () {
    $listing = RouteListing::factory()->create();

    $this->actingAs(makeOperator())
        ->post(route('routes.inquiries.store', $listing), [])
        ->assertSessionHasErrors(['contact_name', 'contact_email', 'message']);

    $this->actingAs(makeOperator())
        ->post(route('routes.inquiries.store', $listing), inquiryPayload([
            'contact_email' => 'not-an-email',
            'contact_phone' => 'call me maybe',
        ]))
        ->assertSessionHasErrors(['contact_email', 'contact_phone']);

    expect(RouteListingInquiry::query()->count())->toBe(0);
});

test('the business is emailed when an inquiry is sent', function () {
    Mail::fake();

    $listing = RouteListing::factory()->create();

    $this->actingAs(makeOperator())
        ->post(route('routes.inquiries.store', $listing), inquiryPayload())
        ->assertRedirect();

    Mail::assertQueued(RouteInquiryReceivedMail::class, function (RouteInquiryReceivedMail $mail) {
        return $mail->hasTo('sales@example.test');
    });
});

test('the inquiry email contains the member, listing and an admin link', function () {
    $inquiry = RouteListingInquiry::factory()->create([
        'contact_name' => 'Alex Jordan',
        'contact_email' => 'alex@example.test',
        'contact_phone' => '(555) 010-0000',
        'message' => 'Please send financials.',
    ]);

    $mail = new RouteInquiryReceivedMail($inquiry);

    $mail->assertSeeInHtml('Alex Jordan');
    $mail->assertSeeInHtml('alex@example.test');
    $mail->assertSeeInHtml('(555) 010-0000');
    $mail->assertSeeInHtml($inquiry->listing_reference);
    $mail->assertSeeInHtml('Please send financials.');
    $mail->assertSeeInHtml(route('admin.route-inquiries.show', $inquiry));
});

test('the inquiry is saved without an email when no notification address is configured', function () {
    Mail::fake();
    config(['route-listings.inquiry_notification_email' => null]);

    $listing = RouteListing::factory()->create();

    $this->actingAs(makeOperator())
        ->post(route('routes.inquiries.store', $listing), inquiryPayload())
        ->assertRedirect();

    expect(RouteListingInquiry::query()->count())->toBe(1);
    Mail::assertNothingQueued();
});

test('an email failure does not remove the saved inquiry', function () {
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Mail server unavailable'));

    $listing = RouteListing::factory()->create();

    $this->actingAs(makeOperator())
        ->post(route('routes.inquiries.store', $listing), inquiryPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(RouteListingInquiry::query()->count())->toBe(1);
});

test('a repeated identical submission creates one inquiry and one email', function () {
    Mail::fake();

    $member = makeOperator();
    $listing = RouteListing::factory()->create();

    $this->actingAs($member)->post(route('routes.inquiries.store', $listing), inquiryPayload())->assertRedirect();
    $this->actingAs($member)->post(route('routes.inquiries.store', $listing), inquiryPayload())->assertRedirect();

    expect(RouteListingInquiry::query()->count())->toBe(1);
    Mail::assertQueued(RouteInquiryReceivedMail::class, 1);
});

test('the same message about a different listing is a separate inquiry', function () {
    Mail::fake();

    $member = makeOperator();
    $first = RouteListing::factory()->create();
    $second = RouteListing::factory()->create();

    $this->actingAs($member)->post(route('routes.inquiries.store', $first), inquiryPayload())->assertRedirect();
    $this->actingAs($member)->post(route('routes.inquiries.store', $second), inquiryPayload())->assertRedirect();

    expect(RouteListingInquiry::query()->count())->toBe(2);
});

test('deleting a listing keeps its inquiries', function () {
    $inquiry = RouteListingInquiry::factory()->create();

    $inquiry->routeListing->delete();

    $inquiry->refresh();

    expect($inquiry->route_listing_id)->toBeNull()
        ->and($inquiry->listing_city)->not->toBeEmpty();
});
