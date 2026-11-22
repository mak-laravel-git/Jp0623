<?php

use App\Models\MembershipCustomPrice;
use App\Models\MembershipPayment;
use App\Models\MembershipSetting;
use App\Models\OperatorMembership;
use App\Models\User;
use App\Services\MembershipPricingService;
use App\Services\MembershipService;
use App\Services\MembershipSettingsService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    Sleep::fake();
    Http::preventStrayRequests();
    fakeSquareHttp([]);
    configureSquare();
});

function setCustomPriceTestMode(string $mode, int $price = 29): void
{
    app(MembershipSettingsService::class)->update([
        'mode' => $mode,
        'monthly_price' => $price,
        'grace_days' => 0,
    ]);
}

function fakeCustomPriceTestSquare(): void
{
    fakeSquareHttp([
        'connect.squareupsandbox.com/v2/customers' => Http::response(['customer' => ['id' => 'CUST-CP-1']]),
        'connect.squareupsandbox.com/v2/cards' => Http::response([
            'card' => ['id' => 'CARD-CP-1', 'card_brand' => 'VISA', 'last_4' => '4242'],
        ]),
        'connect.squareupsandbox.com/v2/payments' => function (Request $request) {
            return Http::response(['payment' => squarePaymentObject(null, [
                'id' => 'PAY-'.$request['reference_id'],
                'reference_id' => $request['reference_id'],
                'amount_money' => ['amount' => $request['amount_money']['amount']],
            ])]);
        },
    ]);
}

test('a member with a custom price must pay even while membership is free for everyone', function () {
    setCustomPriceTestMode(MembershipSetting::MODE_FREE);

    $operator = makeOperator();
    MembershipCustomPrice::factory()->forUser($operator)->create(['monthly_price' => 15]);

    $this->actingAs($operator)
        ->get(route('leads.index'))
        ->assertRedirect(route('subscription.index'));

    expect($operator->membership()->first()->status)->toBe(OperatorMembership::STATUS_REQUIRED)
        ->and($operator->membership()->first()->monthly_price)->toBe(15);
});

test('a member without a custom price stays free in free mode', function () {
    setCustomPriceTestMode(MembershipSetting::MODE_FREE);

    $this->actingAs(makeOperator())
        ->get(route('leads.index'))
        ->assertOk();
});

test('the subscription page shows the custom price and type', function () {
    setCustomPriceTestMode(MembershipSetting::MODE_FREE);

    $operator = makeOperator();
    MembershipCustomPrice::factory()->forUser($operator)->create(['monthly_price' => 15]);

    $this->actingAs($operator)
        ->get(route('subscription.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('settings.type', 'custom')
            ->where('settings.is_paid', true)
            ->where('settings.monthly_price', 15)
            ->where('membership.needs_payment', true)
            ->where('membership.allows_access', false)
        );
});

test('checkout charges the custom price in free mode', function () {
    fakeCustomPriceTestSquare();
    setCustomPriceTestMode(MembershipSetting::MODE_FREE);

    $operator = makeOperator();
    MembershipCustomPrice::factory()->forUser($operator)->create(['monthly_price' => 15]);

    $this->actingAs($operator)
        ->post(route('subscription.checkout'), ['source_id' => 'cnon:card-nonce'])
        ->assertRedirect(route('subscription.index'));

    $payment = MembershipPayment::query()->sole();

    expect($payment->price)->toBe(15)
        ->and($payment->amount_cents)->toBe(1500)
        ->and($operator->membership()->first()->status)->toBe(OperatorMembership::STATUS_ACTIVE)
        ->and($operator->membership()->first()->monthly_price)->toBe(15);
});

test('checkout charges the custom price instead of the default in paid mode', function () {
    fakeCustomPriceTestSquare();
    setCustomPriceTestMode(MembershipSetting::MODE_PAID, 29);

    $custom = makeOperator();
    $regular = makeOperator();
    MembershipCustomPrice::factory()->forUser($custom)->create(['monthly_price' => 12]);

    $this->actingAs($custom)->post(route('subscription.checkout'), ['source_id' => 'cnon:a'])->assertRedirect();
    $this->actingAs($regular)->post(route('subscription.checkout'), ['source_id' => 'cnon:b'])->assertRedirect();

    expect(MembershipPayment::query()->where('user_id', $custom->id)->sole()->price)->toBe(12)
        ->and(MembershipPayment::query()->where('user_id', $regular->id)->sole()->price)->toBe(29);
});

test('renewals charge the custom price and in free mode only custom members are billed', function () {
    fakeCustomPriceTestSquare();
    setCustomPriceTestMode(MembershipSetting::MODE_FREE);

    $custom = makeOperator();
    $other = makeOperator();
    MembershipCustomPrice::factory()->forUser($custom)->create(['monthly_price' => 15]);

    foreach ([$custom, $other] as $operator) {
        OperatorMembership::factory()->active()->create([
            'user_id' => $operator->id,
            'monthly_price' => 29,
            'current_period_end' => now()->subHour(),
            'next_billing_at' => now()->subHour(),
        ]);
    }

    $this->artisan('memberships:bill')->assertSuccessful();

    $payment = MembershipPayment::query()->sole();

    expect($payment->user_id)->toBe($custom->id)
        ->and($payment->price)->toBe(15)
        ->and($other->membership()->first()->next_billing_at->isPast())->toBeTrue();
});

test('renewals in paid mode charge the custom price instead of the default', function () {
    fakeCustomPriceTestSquare();
    setCustomPriceTestMode(MembershipSetting::MODE_PAID, 35);

    $custom = makeOperator();
    MembershipCustomPrice::factory()->forUser($custom)->create(['monthly_price' => 20]);

    OperatorMembership::factory()->active()->create([
        'user_id' => $custom->id,
        'monthly_price' => 35,
        'current_period_end' => now()->subHour(),
        'next_billing_at' => now()->subHour(),
    ]);

    $this->artisan('memberships:bill')->assertSuccessful();

    expect(MembershipPayment::query()->sole()->price)->toBe(20);
});

test('a price reserved for an email applies when that person registers', function () {
    setCustomPriceTestMode(MembershipSetting::MODE_FREE);

    $reserved = MembershipCustomPrice::factory()->create(['email' => 'new.member@example.com', 'monthly_price' => 18]);
    $user = User::factory()->create(['email' => 'New.Member@Example.com']);

    $membership = app(MembershipService::class)->ensureFor($user);

    expect($membership->status)->toBe(OperatorMembership::STATUS_REQUIRED)
        ->and($membership->monthly_price)->toBe(18)
        ->and($reserved->refresh()->user_id)->toBe($user->id)
        ->and(app(MembershipPricingService::class)->typeFor($user))->toBe('custom');
});

test('the membership type is free, paid or custom', function () {
    $pricing = app(MembershipPricingService::class);
    $user = makeOperator();

    setCustomPriceTestMode(MembershipSetting::MODE_FREE);
    expect($pricing->typeFor($user))->toBe('free')
        ->and($pricing->requiresPayment($user))->toBeFalse();

    setCustomPriceTestMode(MembershipSetting::MODE_PAID, 29);
    expect($pricing->typeFor($user))->toBe('paid')
        ->and($pricing->priceFor($user))->toBe(29);

    MembershipCustomPrice::factory()->forUser($user)->create(['monthly_price' => 10]);
    expect($pricing->typeFor($user))->toBe('custom')
        ->and($pricing->priceFor($user))->toBe(10);
});

test('in free mode a member moved to the default price is billed the default price and others are not', function () {
    fakeCustomPriceTestSquare();
    setCustomPriceTestMode(MembershipSetting::MODE_FREE, 33);

    $moved = makeOperator();
    $other = makeOperator();

    foreach ([$moved, $other] as $operator) {
        OperatorMembership::factory()->active()->create([
            'user_id' => $operator->id,
            'monthly_price' => 15,
            'pays_default_price' => $operator->is($moved),
            'current_period_end' => now()->subHour(),
            'next_billing_at' => now()->subHour(),
        ]);
    }

    $this->artisan('memberships:bill')->assertSuccessful();

    $payment = MembershipPayment::query()->sole();

    expect($payment->user_id)->toBe($moved->id)
        ->and($payment->price)->toBe(33);
});

test('a member moved to the default price must pay at checkout while others stay free', function () {
    fakeCustomPriceTestSquare();
    setCustomPriceTestMode(MembershipSetting::MODE_FREE, 33);

    $moved = makeOperator();
    OperatorMembership::factory()->required()->create(['user_id' => $moved->id, 'pays_default_price' => true]);

    $this->actingAs($moved)->get(route('leads.index'))->assertRedirect(route('subscription.index'));
    $this->actingAs($moved)->post(route('subscription.checkout'), ['source_id' => 'cnon:x'])->assertRedirect();

    expect(MembershipPayment::query()->sole()->price)->toBe(33);

    $this->actingAs(makeOperator())->get(route('leads.index'))->assertOk();
});
