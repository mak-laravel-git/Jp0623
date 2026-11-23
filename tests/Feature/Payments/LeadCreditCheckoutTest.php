<?php

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\LeadPurchase;
use App\Models\User;
use App\Models\VendingRequest;
use App\Services\CreditService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    Sleep::fake();
    Mail::fake();
    Http::preventStrayRequests();
    configureSquare();
});

function creditBuyUrl(VendingRequest $lead): string
{
    return route('leads.purchase', ['source' => 'vending', 'lead' => $lead->id]);
}

function operatorWithCredits(int $credits): User
{
    $operator = makeOperator();

    if ($credits > 0) {
        app(CreditService::class)->grant($operator, $credits, CreditTransactionType::REFUND_CREDIT);
    }

    return $operator;
}

test('credits that cover the whole price buy the lead without a card', function () {
    fakeSquareHttp([]);

    $lead = VendingRequest::factory()->create(['price' => 150]);
    $operator = operatorWithCredits(200);

    $this->actingAs($operator)
        ->post(creditBuyUrl($lead), ['use_credits' => true])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('leads.purchased'));

    $purchase = LeadPurchase::query()->sole();

    expect($purchase->status)->toBe(LeadPurchase::STATUS_PAID)
        ->and($purchase->credits_applied)->toBe(150)
        ->and($purchase->amount_cents)->toBe(0)
        ->and($purchase->payment_provider)->toBe(LeadPurchase::PROVIDER_CREDITS)
        ->and($purchase->square_payment_id)->toBeNull()
        ->and($operator->refresh()->credit_balance)->toBe(50)
        ->and(squareCallCount())->toBe(0);

    $spend = CreditTransaction::query()->where('type', CreditTransactionType::PURCHASE_SPEND)->sole();

    expect($spend->amount)->toBe(-150)->and($spend->balance_after)->toBe(50)->and($spend->lead_purchase_id)->toBe($purchase->id);
});

test('credits are used first and the card pays only the rest', function () {
    fakeSquareHttp([
        'connect.squareupsandbox.com/v2/payments' => function (Request $request) {
            return Http::response(['payment' => squarePaymentObject(null, [
                'id' => 'PAY-'.$request['reference_id'],
                'reference_id' => $request['reference_id'],
                'amount_money' => ['amount' => $request['amount_money']['amount']],
            ])]);
        },
    ]);

    $lead = VendingRequest::factory()->create(['price' => 100]);
    $operator = operatorWithCredits(50);

    $this->actingAs($operator)
        ->post(creditBuyUrl($lead), ['use_credits' => true, 'source_id' => 'cnon:card-nonce'])
        ->assertSessionHasNoErrors();

    $purchase = LeadPurchase::query()->sole();

    expect($purchase->status)->toBe(LeadPurchase::STATUS_PAID)
        ->and($purchase->price)->toBe(100)
        ->and($purchase->credits_applied)->toBe(50)
        ->and($purchase->amount_cents)->toBe(5000)
        ->and($operator->refresh()->credit_balance)->toBe(0);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/v2/payments')
        && $request['amount_money']['amount'] === 5000);
});

test('without credits the card pays the full price and the credits stay', function () {
    fakeSquareHttp([
        'connect.squareupsandbox.com/v2/payments' => function (Request $request) {
            return Http::response(['payment' => squarePaymentObject(null, [
                'id' => 'PAY-'.$request['reference_id'],
                'reference_id' => $request['reference_id'],
                'amount_money' => ['amount' => $request['amount_money']['amount']],
            ])]);
        },
    ]);

    $lead = VendingRequest::factory()->create(['price' => 100]);
    $operator = operatorWithCredits(50);

    $this->actingAs($operator)
        ->post(creditBuyUrl($lead), ['source_id' => 'cnon:card-nonce'])
        ->assertSessionHasNoErrors();

    $purchase = LeadPurchase::query()->sole();

    expect($purchase->credits_applied)->toBe(0)
        ->and($purchase->amount_cents)->toBe(10000)
        ->and($operator->refresh()->credit_balance)->toBe(50);
});

test('a card is required when the credits do not cover the price', function () {
    fakeSquareHttp([]);

    $lead = VendingRequest::factory()->create(['price' => 100]);
    $operator = operatorWithCredits(50);

    $this->actingAs($operator)
        ->post(creditBuyUrl($lead), ['use_credits' => true])
        ->assertSessionHasErrors('payment');

    expect($operator->refresh()->credit_balance)->toBe(50)
        ->and(LeadPurchase::query()->sole()->status)->toBe(LeadPurchase::STATUS_FAILED)
        ->and(squareCallCount())->toBe(0);
});

test('a declined card gives the credits back', function () {
    fakeSquareHttp(['connect.squareupsandbox.com/v2/payments' => Http::response([
        'errors' => [['category' => 'PAYMENT_METHOD_ERROR', 'code' => 'GENERIC_DECLINE', 'detail' => 'Declined by issuer']],
    ], 402)]);

    $lead = VendingRequest::factory()->create(['price' => 100]);
    $operator = operatorWithCredits(50);

    $this->actingAs($operator)
        ->post(creditBuyUrl($lead), ['use_credits' => true, 'source_id' => 'cnon:card-nonce'])
        ->assertSessionHasErrors(['payment' => 'Your card was declined.']);

    expect(LeadPurchase::query()->sole()->status)->toBe(LeadPurchase::STATUS_FAILED)
        ->and($operator->refresh()->credit_balance)->toBe(50)
        ->and(CreditTransaction::query()->where('type', CreditTransactionType::PURCHASE_RESTORE)->sole()->amount)->toBe(50);
});

test('the member sees their credit balance on every page', function () {
    fakeSquareHttp([]);

    $operator = operatorWithCredits(75);

    $this->actingAs($operator)
        ->get(route('leads.index'))
        ->assertInertia(fn ($page) => $page->where('credits', 75));
});
