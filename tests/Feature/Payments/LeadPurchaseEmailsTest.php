<?php

use App\Mail\LeadPurchasedBuyerMail;
use App\Mail\LeadPurchasedContactMail;
use App\Models\CoffeeServiceRequest;
use App\Models\LeadPurchase;
use App\Models\User;
use App\Models\VendingRequest;
use App\Services\LeadPurchaseService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    configureSquare();
});

function pendingPurchaseFor(User $operator, string $source, Model $lead): LeadPurchase
{
    return LeadPurchase::factory()->pending()->create([
        'user_id' => $operator->id,
        'leadable_type' => $source,
        'leadable_id' => $lead->id,
    ]);
}

function paidPurchaseFor(User $operator, string $source, Model $lead): LeadPurchase
{
    return LeadPurchase::factory()->create([
        'user_id' => $operator->id,
        'leadable_type' => $source,
        'leadable_id' => $lead->id,
    ]);
}

function makeOperatorWithEmail(string $email, array $profile = []): User
{
    $operator = makeOperator($profile);
    $operator->update(['email' => $email]);

    return $operator;
}

test('a completed payment queues the lead details to the buyer and the operator details to the lead contact', function () {
    $lead = VendingRequest::factory()->create(['email' => 'contact@example.com']);
    $operator = makeOperatorWithEmail('buyer@example.com');
    $purchase = pendingPurchaseFor($operator, 'vending', $lead);

    Mail::fake();

    app(LeadPurchaseService::class)->applySquarePayment($purchase, squarePaymentObject($purchase));

    Mail::assertQueued(LeadPurchasedBuyerMail::class, fn (LeadPurchasedBuyerMail $mail): bool => $mail->hasTo('buyer@example.com'));
    Mail::assertQueued(LeadPurchasedContactMail::class, fn (LeadPurchasedContactMail $mail): bool => $mail->hasTo('contact@example.com'));
    Mail::assertQueuedCount(2);
});

test('a failed payment does not queue any email', function () {
    $lead = VendingRequest::factory()->create();
    $operator = makeOperator();
    $purchase = pendingPurchaseFor($operator, 'vending', $lead);

    Mail::fake();

    app(LeadPurchaseService::class)->applySquarePayment($purchase, squarePaymentObject($purchase, ['status' => 'FAILED']));

    Mail::assertNothingQueued();
});

test('replaying a completed payment does not queue the emails twice', function () {
    $lead = VendingRequest::factory()->create();
    $operator = makeOperator();
    $purchase = pendingPurchaseFor($operator, 'vending', $lead);

    Mail::fake();

    $service = app(LeadPurchaseService::class);
    $service->applySquarePayment($purchase, squarePaymentObject($purchase));
    $service->applySquarePayment($purchase, squarePaymentObject($purchase));

    Mail::assertQueuedCount(2);
});

test('a purchased coffee lead emails both parties', function () {
    $lead = CoffeeServiceRequest::factory()->create(['email' => 'coffee-contact@example.com']);
    $operator = makeOperatorWithEmail('coffee-buyer@example.com');
    $purchase = pendingPurchaseFor($operator, 'coffee', $lead);

    Mail::fake();

    app(LeadPurchaseService::class)->applySquarePayment($purchase, squarePaymentObject($purchase));

    Mail::assertQueued(LeadPurchasedBuyerMail::class, fn (LeadPurchasedBuyerMail $mail): bool => $mail->hasTo('coffee-buyer@example.com'));
    Mail::assertQueued(LeadPurchasedContactMail::class, fn (LeadPurchasedContactMail $mail): bool => $mail->hasTo('coffee-contact@example.com'));
});

test('the buyer email lists the lead contact details', function () {
    $lead = VendingRequest::factory()->create([
        'business_name' => 'Visible Cafe',
        'contact_name' => 'Visible Person',
        'email' => 'visible@example.com',
        'phone' => '555-0199',
        'address' => '12 Visible St',
    ]);
    $purchase = paidPurchaseFor(makeOperator(), 'vending', $lead);

    $mail = new LeadPurchasedBuyerMail($purchase);

    $mail->assertHasSubject("Your lead details: Visible Cafe (V-{$lead->id})");
    $mail->assertSeeInHtml('Visible Cafe');
    $mail->assertSeeInHtml('Visible Person');
    $mail->assertSeeInHtml('visible@example.com');
    $mail->assertSeeInHtml('555-0199');
    $mail->assertSeeInHtml('12 Visible St');
});

test('the contact email lists the buyer company name, email and phone number', function () {
    $lead = VendingRequest::factory()->create(['contact_name' => 'Visible Person']);
    $operator = makeOperatorWithEmail('buyer@example.com', [
        'company_name' => 'Acme Vending',
        'phone' => '555-0100',
    ]);

    $mail = new LeadPurchasedContactMail(paidPurchaseFor($operator, 'vending', $lead));

    $mail->assertHasSubject('Acme Vending will be contacting you about your request');
    $mail->assertSeeInHtml('Visible Person');
    $mail->assertSeeInHtml('Acme Vending');
    $mail->assertSeeInHtml('buyer@example.com');
    $mail->assertSeeInHtml('555-0100');
});

test('the contact email says the phone number was not provided when the buyer has none', function () {
    $lead = VendingRequest::factory()->create();
    $operator = makeOperator(['phone' => null]);

    $mail = new LeadPurchasedContactMail(paidPurchaseFor($operator, 'vending', $lead));

    $mail->assertSeeInHtml('Not provided');
});
