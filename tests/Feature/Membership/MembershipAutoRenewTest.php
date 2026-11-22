<?php

use App\Enums\RoleEnum;
use App\Mail\MembershipCustomPriceMail;
use App\Models\MembershipCustomPrice;
use App\Models\MembershipPayment;
use App\Models\MembershipSetting;
use App\Models\OperatorMembership;
use App\Models\User;
use App\Services\MembershipSettingsService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    Sleep::fake();
    Http::preventStrayRequests();
    fakeSquareHttp([]);
    configureSquare();

    app(MembershipSettingsService::class)->update([
        'mode' => MembershipSetting::MODE_PAID,
        'monthly_price' => 29,
        'grace_days' => 0,
    ]);
});

function fakeAutoRenewTestSquare(): void
{
    fakeSquareHttp([
        'connect.squareupsandbox.com/v2/customers' => Http::response(['customer' => ['id' => 'CUST-AR-1']]),
        'connect.squareupsandbox.com/v2/cards' => Http::response([
            'card' => ['id' => 'CARD-AR-1', 'card_brand' => 'VISA', 'last_4' => '4242'],
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

test('a member can switch auto-renew off and keeps access until the paid period ends', function () {
    $member = makeOperator();
    $membership = OperatorMembership::factory()->active()->create(['user_id' => $member->id]);

    $this->actingAs($member)
        ->put(route('subscription.auto-renew'), ['auto_renew' => false])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($membership->refresh()->auto_renew)->toBeFalse()
        ->and($membership->status)->toBe(OperatorMembership::STATUS_ACTIVE);

    $this->actingAs($member)
        ->get(route('subscription.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('membership.auto_renew', false)
            ->where('membership.can_change_auto_renew', true)
            ->where('membership.next_payment', null)
            ->where('membership.allows_access', true)
        );

    $this->actingAs($member)->get(route('leads.index'))->assertOk();
});

test('a member can switch auto-renew back on', function () {
    $member = makeOperator();
    $membership = OperatorMembership::factory()->active()->create(['user_id' => $member->id, 'auto_renew' => false]);

    $this->actingAs($member)
        ->put(route('subscription.auto-renew'), ['auto_renew' => true])
        ->assertSessionHasNoErrors();

    expect($membership->refresh()->auto_renew)->toBeTrue();
});

test('auto-renew cannot be changed without a running paid period', function () {
    $member = makeOperator();
    $membership = OperatorMembership::factory()->required()->create(['user_id' => $member->id]);

    $this->actingAs($member)
        ->put(route('subscription.auto-renew'), ['auto_renew' => false])
        ->assertRedirect();

    expect($membership->refresh()->auto_renew)->toBeTrue();
});

test('guests cannot change auto-renew and the value is validated', function () {
    $this->put(route('subscription.auto-renew'), ['auto_renew' => false])->assertRedirect(route('login'));

    $member = makeOperator();
    OperatorMembership::factory()->active()->create(['user_id' => $member->id]);

    $this->actingAs($member)
        ->put(route('subscription.auto-renew'), [])
        ->assertSessionHasErrors('auto_renew');
});

test('the subscription page shows the next payment with the price the member will be charged', function () {
    $member = makeOperator();
    $membership = OperatorMembership::factory()->active()->create(['user_id' => $member->id, 'monthly_price' => 29]);
    MembershipCustomPrice::factory()->forUser($member)->create(['monthly_price' => 19]);

    $this->actingAs($member)
        ->get(route('subscription.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('membership.auto_renew', true)
            ->where('membership.current_price', 29)
            ->where('membership.next_payment.amount', 19)
            ->where('membership.next_payment.date', $membership->next_billing_at->toIso8601String())
        );
});

test('renewals skip members who turned auto-renew off and end their membership after the paid period', function () {
    fakeAutoRenewTestSquare();

    $renewing = makeOperator();
    $leaving = makeOperator();

    foreach ([$renewing, $leaving] as $operator) {
        OperatorMembership::factory()->active()->create([
            'user_id' => $operator->id,
            'auto_renew' => $operator->is($renewing),
            'current_period_end' => now()->subHour(),
            'next_billing_at' => now()->subHour(),
        ]);
    }

    $this->artisan('memberships:bill')->assertSuccessful();

    $leavingMembership = $leaving->membership()->first();

    expect(MembershipPayment::query()->sole()->user_id)->toBe($renewing->id)
        ->and($leavingMembership->status)->toBe(OperatorMembership::STATUS_CANCELED)
        ->and($leavingMembership->canceled_at)->not->toBeNull();
});

test('a member with auto-renew off is locked out once the paid period is over and can pay again to reactivate', function () {
    fakeAutoRenewTestSquare();

    $member = makeOperator();
    $membership = OperatorMembership::factory()->active()->create([
        'user_id' => $member->id,
        'auto_renew' => false,
        'current_period_end' => now()->subMinute(),
        'next_billing_at' => now()->subMinute(),
    ]);

    $this->actingAs($member)->get(route('leads.index'))->assertRedirect(route('subscription.index'));

    expect($membership->refresh()->status)->toBe(OperatorMembership::STATUS_CANCELED);

    $this->actingAs($member)
        ->post(route('subscription.checkout'), ['source_id' => 'cnon:again'])
        ->assertRedirect(route('subscription.index'));

    expect($membership->refresh()->status)->toBe(OperatorMembership::STATUS_ACTIVE)
        ->and($membership->auto_renew)->toBeTrue()
        ->and($membership->canceled_at)->toBeNull();
});

test('the dashboard membership card shows the next payment, or when the membership ends', function () {
    $member = makeOperator();
    $membership = OperatorMembership::factory()->active()->create(['user_id' => $member->id, 'monthly_price' => 29]);

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscription.auto_renew', true)
            ->where('subscription.next_payment.amount', 29)
            ->where('subscription.next_payment.date', $membership->next_billing_at->format('M j'))
            ->where('kpis.2.hint', 'Next payment $29 on '.$membership->next_billing_at->format('M j'))
        );

    $membership->forceFill(['auto_renew' => false])->save();

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscription.next_payment', null)
            ->where('subscription.ends_on', $membership->current_period_end->format('M j'))
            ->where('kpis.2.hint', 'Ends '.$membership->current_period_end->format('M j').', no more charges')
        );
});

test('the dashboard says the membership ended after a canceled membership', function () {
    $member = makeOperator();
    OperatorMembership::factory()->create([
        'user_id' => $member->id,
        'status' => OperatorMembership::STATUS_CANCELED,
        'auto_renew' => false,
        'current_period_end' => now()->subDay(),
    ]);

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscription.status', 'canceled')
            ->where('subscription.needs_payment', true)
            ->where('kpis.2.value', 'Canceled')
        );
});

test('a price set by the admin while a member is paying only applies as the next payment', function () {
    Mail::fake();
    fakeAutoRenewTestSquare();

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::SUPER_ADMIN->value);

    $member = makeOperator();
    $membership = OperatorMembership::factory()->active()->create(['user_id' => $member->id, 'monthly_price' => 29]);

    $this->actingAs($admin)
        ->post(route('admin.membership-settings.custom-prices.store'), [
            'email' => $member->email,
            'monthly_price' => 200,
            'send_email' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($membership->refresh()->monthly_price)->toBe(29);

    $this->actingAs($member)
        ->get(route('subscription.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('membership.current_price', 29)
            ->where('membership.next_payment.amount', 200)
        );

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.2.value', '$29/mo')
            ->where('kpis.2.hint', fn (string $hint) => str_contains($hint, 'Next payment $200') && str_ends_with($hint, '(new price)'))
        );

    Mail::assertQueued(MembershipCustomPriceMail::class, fn (MembershipCustomPriceMail $mail) => $mail->price === 200
        && $mail->appliesFrom === $membership->next_billing_at->format('F j, Y'));

    $membership->forceFill(['current_period_end' => now()->subHour(), 'next_billing_at' => now()->subHour()])->save();
    $this->artisan('memberships:bill')->assertSuccessful();

    expect(MembershipPayment::query()->sole()->price)->toBe(200)
        ->and($membership->refresh()->monthly_price)->toBe(200);
});

test('a member who must pay now gets the new price straight away and the email says so', function () {
    Mail::fake();

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::SUPER_ADMIN->value);

    $member = makeOperator();
    $membership = OperatorMembership::factory()->required()->create(['user_id' => $member->id, 'monthly_price' => 29]);

    $this->actingAs($admin)
        ->post(route('admin.membership-settings.custom-prices.store'), [
            'email' => $member->email,
            'monthly_price' => 200,
            'send_email' => true,
        ]);

    expect($membership->refresh()->monthly_price)->toBe(200);

    Mail::assertQueued(MembershipCustomPriceMail::class, fn (MembershipCustomPriceMail $mail) => $mail->appliesFrom === null);
});

test('the admin lookup shows what a paying member pays now and from the next renewal', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::SUPER_ADMIN->value);

    $member = makeOperator();
    OperatorMembership::factory()->active()->create(['user_id' => $member->id, 'monthly_price' => 29]);
    MembershipCustomPrice::factory()->forUser($member)->create(['monthly_price' => 200]);

    $this->actingAs($admin)
        ->getJson(route('admin.membership-settings.custom-prices.lookup', ['email' => $member->email]))
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Custom price ($200/mo, from next renewal; paying $29/mo until'));
});

test('the custom price email tells a paying member the new price starts at the next renewal', function () {
    $mail = new MembershipCustomPriceMail(price: 200, name: 'Ada', registered: true, appliesFrom: 'December 22, 2026');

    $mail->assertSeeInHtml('Your current paid period is not affected');
    $mail->assertSeeInHtml('December 22, 2026');
});
