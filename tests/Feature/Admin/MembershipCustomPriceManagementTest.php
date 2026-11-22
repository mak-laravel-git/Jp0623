<?php

use App\Enums\RoleEnum;
use App\Mail\MembershipCustomPriceMail;
use App\Models\MembershipCustomPrice;
use App\Models\MembershipSetting;
use App\Models\OperatorMembership;
use App\Models\User;
use App\Services\MembershipPricingService;
use App\Services\MembershipSettingsService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::SUPER_ADMIN->value);
});

function setAdminTestMembershipMode(string $mode, int $price = 29): void
{
    app(MembershipSettingsService::class)->update([
        'mode' => $mode,
        'monthly_price' => $price,
        'grace_days' => 0,
    ]);
}

test('guests and members cannot manage custom member prices', function () {
    $row = MembershipCustomPrice::factory()->create();

    $this->get(route('admin.membership-settings.custom-prices.lookup', ['email' => 'a@example.com']))
        ->assertRedirect(route('login'));

    $member = makeOperator();

    $this->actingAs($member)
        ->get(route('admin.membership-settings.custom-prices.lookup', ['email' => 'a@example.com']))
        ->assertForbidden();
    $this->actingAs($member)
        ->post(route('admin.membership-settings.custom-prices.store'), ['email' => 'a@example.com', 'monthly_price' => 10])
        ->assertForbidden();
    $this->actingAs($member)
        ->delete(route('admin.membership-settings.custom-prices.destroy', $row))
        ->assertForbidden();

    expect(MembershipCustomPrice::query()->count())->toBe(1);
});

test('the membership settings page lists the custom prices', function () {
    MembershipCustomPrice::factory()->create(['email' => 'reserved@example.com', 'monthly_price' => 12]);
    $member = makeOperator();
    MembershipCustomPrice::factory()->forUser($member)->create(['monthly_price' => 15]);

    $this->actingAs($this->admin)
        ->get(route('admin.membership-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/membership-settings/edit')
            ->has('customPrices.data', 2)
            ->where('customPrices.data.0.registered', true)
            ->where('customPrices.data.1.email', 'reserved@example.com')
            ->where('customPrices.data.1.registered', false)
        );
});

test('the lookup says when no member has the email yet', function () {
    $this->actingAs($this->admin)
        ->getJson(route('admin.membership-settings.custom-prices.lookup', ['email' => 'nobody@example.com']))
        ->assertOk()
        ->assertJson(['exists' => false, 'name' => null])
        ->assertJsonPath('message', 'No member with this email yet. The price will apply when they register.');

    MembershipCustomPrice::factory()->create(['email' => 'reserved@example.com', 'monthly_price' => 12]);

    $this->actingAs($this->admin)
        ->getJson(route('admin.membership-settings.custom-prices.lookup', ['email' => 'Reserved@Example.com']))
        ->assertJson(['exists' => false])
        ->assertJsonPath('message', fn (string $message) => str_contains($message, '$12/mo is already reserved'));
});

test('the lookup tells which membership an existing member is already on', function () {
    $member = makeOperator();
    $member->update(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    $lookup = fn () => $this->actingAs($this->admin)
        ->getJson(route('admin.membership-settings.custom-prices.lookup', ['email' => 'ada@example.com']))
        ->assertOk()
        ->assertJson(['exists' => true, 'name' => 'Ada Lovelace'])
        ->json('message');

    setAdminTestMembershipMode(MembershipSetting::MODE_FREE);
    expect($lookup())->toBe('Ada Lovelace is already on: Free membership.');

    setAdminTestMembershipMode(MembershipSetting::MODE_PAID, 29);
    OperatorMembership::factory()->required()->create(['user_id' => $member->id]);
    expect($lookup())->toBe('Ada Lovelace is already on: Paid monthly ($29/mo, payment required).');

    MembershipCustomPrice::factory()->forUser($member)->create(['monthly_price' => 15]);
    expect($lookup())->toBe('Ada Lovelace is already on: Custom price ($15/mo, payment required).');
});

test('a price can be saved for an email that has no account and the person is invited by email', function () {
    Mail::fake();

    $this->actingAs($this->admin)
        ->post(route('admin.membership-settings.custom-prices.store'), [
            'email' => '  New.Person@Example.com ',
            'monthly_price' => 18,
            'send_email' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $row = MembershipCustomPrice::query()->sole();

    expect($row->email)->toBe('new.person@example.com')
        ->and($row->monthly_price)->toBe(18)
        ->and($row->user_id)->toBeNull()
        ->and($row->created_by)->toBe($this->admin->id)
        ->and($row->notified_at)->not->toBeNull();

    Mail::assertQueued(MembershipCustomPriceMail::class, fn (MembershipCustomPriceMail $mail) => $mail->hasTo('new.person@example.com')
        && $mail->price === 18
        && $mail->registered === false);
});

test('a price set for a free member makes them pay from now on', function () {
    Mail::fake();
    setAdminTestMembershipMode(MembershipSetting::MODE_FREE);

    $member = makeOperator();
    $membership = OperatorMembership::factory()->create(['user_id' => $member->id, 'status' => OperatorMembership::STATUS_ACTIVE_FREE]);

    $this->actingAs($this->admin)
        ->post(route('admin.membership-settings.custom-prices.store'), [
            'email' => $member->email,
            'monthly_price' => 15,
            'send_email' => true,
        ])
        ->assertSessionHasNoErrors();

    $row = MembershipCustomPrice::query()->sole();
    $membership->refresh();

    expect($row->user_id)->toBe($member->id)
        ->and($membership->status)->toBe(OperatorMembership::STATUS_REQUIRED)
        ->and($membership->monthly_price)->toBe(15);

    Mail::assertQueued(MembershipCustomPriceMail::class, fn (MembershipCustomPriceMail $mail) => $mail->registered === true
        && $mail->hasTo($member->email));
});

test('a paying member keeps the current paid period and gets the new price', function () {
    Mail::fake();
    setAdminTestMembershipMode(MembershipSetting::MODE_PAID, 29);

    $member = makeOperator();
    $membership = OperatorMembership::factory()->active()->create(['user_id' => $member->id, 'monthly_price' => 29]);
    $periodEnd = $membership->current_period_end;

    $this->actingAs($this->admin)
        ->post(route('admin.membership-settings.custom-prices.store'), [
            'email' => $member->email,
            'monthly_price' => 19,
        ])
        ->assertSessionHasNoErrors();

    $membership->refresh();

    expect($membership->status)->toBe(OperatorMembership::STATUS_ACTIVE)
        ->and($membership->monthly_price)->toBe(29)
        ->and($membership->current_period_end->equalTo($periodEnd))->toBeTrue()
        ->and(app(MembershipPricingService::class)->priceFor($member))->toBe(19);

    Mail::assertNothingQueued();
});

test('saving a price for the same email updates it instead of adding another', function () {
    Mail::fake();

    foreach ([20, 25] as $price) {
        $this->actingAs($this->admin)
            ->post(route('admin.membership-settings.custom-prices.store'), [
                'email' => 'Same@Example.com',
                'monthly_price' => $price,
            ])
            ->assertSessionHasNoErrors();
    }

    expect(MembershipCustomPrice::query()->count())->toBe(1)
        ->and(MembershipCustomPrice::query()->sole()->monthly_price)->toBe(25);
});

test('the price and email are validated', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.membership-settings.custom-prices.store'), ['email' => 'not-an-email', 'monthly_price' => 0])
        ->assertSessionHasErrors(['email', 'monthly_price']);

    $this->actingAs($this->admin)
        ->post(route('admin.membership-settings.custom-prices.store'), [])
        ->assertSessionHasErrors(['email', 'monthly_price']);

    $this->actingAs($this->admin)
        ->getJson(route('admin.membership-settings.custom-prices.lookup', ['email' => 'nope']))
        ->assertUnprocessable();

    expect(MembershipCustomPrice::query()->count())->toBe(0);
});

test('an email failure does not undo the saved price', function () {
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Mail server unavailable'));

    $this->actingAs($this->admin)
        ->post(route('admin.membership-settings.custom-prices.store'), [
            'email' => 'safe@example.com',
            'monthly_price' => 14,
            'send_email' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $row = MembershipCustomPrice::query()->sole();

    expect($row->monthly_price)->toBe(14)
        ->and($row->notified_at)->toBeNull();
});

test('removing a custom price moves the member to the default price even while membership is free', function () {
    setAdminTestMembershipMode(MembershipSetting::MODE_FREE, 29);

    $member = makeOperator();
    $other = makeOperator();
    $membership = OperatorMembership::factory()->required()->create(['user_id' => $member->id, 'monthly_price' => 15]);
    $row = MembershipCustomPrice::factory()->forUser($member)->create(['monthly_price' => 15]);

    $this->actingAs($this->admin)
        ->delete(route('admin.membership-settings.custom-prices.destroy', $row))
        ->assertRedirect();

    $membership->refresh();
    $pricing = app(MembershipPricingService::class);

    expect(MembershipCustomPrice::query()->count())->toBe(0)
        ->and($membership->status)->toBe(OperatorMembership::STATUS_REQUIRED)
        ->and($membership->pays_default_price)->toBeTrue()
        ->and($membership->monthly_price)->toBe(29)
        ->and($pricing->requiresPayment($member))->toBeTrue()
        ->and($pricing->typeFor($member))->toBe('paid')
        ->and($pricing->requiresPayment($other))->toBeFalse();

    // The default price is whatever the admin sets, so the member follows it.
    setAdminTestMembershipMode(MembershipSetting::MODE_FREE, 40);

    expect($pricing->priceFor($member))->toBe(40);
});

test('removing the custom price of a free member makes them pay the default price', function () {
    setAdminTestMembershipMode(MembershipSetting::MODE_FREE, 25);

    $member = makeOperator();
    $membership = OperatorMembership::factory()->create(['user_id' => $member->id, 'status' => OperatorMembership::STATUS_ACTIVE_FREE]);
    $row = MembershipCustomPrice::factory()->forUser($member)->create(['monthly_price' => 10]);

    $this->actingAs($this->admin)
        ->delete(route('admin.membership-settings.custom-prices.destroy', $row))
        ->assertRedirect();

    expect($membership->refresh()->status)->toBe(OperatorMembership::STATUS_REQUIRED)
        ->and($membership->monthly_price)->toBe(25);
});

test('removing a custom price from a paying member keeps the paid period', function () {
    setAdminTestMembershipMode(MembershipSetting::MODE_PAID, 29);

    $member = makeOperator();
    $membership = OperatorMembership::factory()->active()->create(['user_id' => $member->id, 'monthly_price' => 12]);
    $periodEnd = $membership->current_period_end;
    $row = MembershipCustomPrice::factory()->forUser($member)->create(['monthly_price' => 12]);

    $this->actingAs($this->admin)
        ->delete(route('admin.membership-settings.custom-prices.destroy', $row))
        ->assertRedirect();

    $membership->refresh();

    expect($membership->status)->toBe(OperatorMembership::STATUS_ACTIVE)
        ->and($membership->monthly_price)->toBe(12)
        ->and($membership->current_period_end->equalTo($periodEnd))->toBeTrue()
        ->and(app(MembershipPricingService::class)->priceFor($member))->toBe(29);
});

test('setting a custom price again clears the default price flag', function () {
    setAdminTestMembershipMode(MembershipSetting::MODE_FREE, 29);

    $member = makeOperator();
    $membership = OperatorMembership::factory()->required()->create(['user_id' => $member->id, 'pays_default_price' => true]);

    $this->actingAs($this->admin)
        ->post(route('admin.membership-settings.custom-prices.store'), ['email' => $member->email, 'monthly_price' => 18])
        ->assertSessionHasNoErrors();

    expect($membership->refresh()->pays_default_price)->toBeFalse()
        ->and($membership->monthly_price)->toBe(18);
});

test('the price of an entry can be edited', function () {
    Mail::fake();
    $row = MembershipCustomPrice::factory()->create(['email' => 'edit@example.com', 'monthly_price' => 10]);

    $this->actingAs($this->admin)
        ->put(route('admin.membership-settings.custom-prices.update', $row), [
            'monthly_price' => 22,
            'send_email' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($row->refresh()->monthly_price)->toBe(22)
        ->and(MembershipCustomPrice::query()->count())->toBe(1);

    Mail::assertQueued(MembershipCustomPriceMail::class, fn (MembershipCustomPriceMail $mail) => $mail->price === 22);

    $this->actingAs($this->admin)
        ->put(route('admin.membership-settings.custom-prices.update', $row), ['monthly_price' => 0])
        ->assertSessionHasErrors('monthly_price');

    $this->actingAs(makeOperator())
        ->put(route('admin.membership-settings.custom-prices.update', $row), ['monthly_price' => 30])
        ->assertForbidden();

    expect($row->refresh()->monthly_price)->toBe(22);
});

test('the list shows when each price was created and last updated', function () {
    MembershipCustomPrice::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.membership-settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('customPrices.data.0.created_at')
            ->has('customPrices.data.0.updated_at')
        );
});

test('linking a reserved price to a new account does not change its updated time', function () {
    $row = MembershipCustomPrice::factory()->create(['email' => 'link@example.com']);
    $row->forceFill(['updated_at' => now()->subDays(3)])->saveQuietly();
    $updatedAt = $row->refresh()->updated_at;

    $user = User::factory()->create(['email' => 'link@example.com']);
    app(MembershipPricingService::class)->customRowFor($user);

    expect($row->refresh()->user_id)->toBe($user->id)
        ->and($row->updated_at->equalTo($updatedAt))->toBeTrue();
});

test('removing a price reserved for an email without an account just deletes it', function () {
    $row = MembershipCustomPrice::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.membership-settings.custom-prices.destroy', $row))
        ->assertRedirect();

    expect(MembershipCustomPrice::query()->count())->toBe(0);
});

test('the custom price email names the price and links to the right page', function () {
    $registered = new MembershipCustomPriceMail(price: 15, name: 'Ada Lovelace', registered: true);
    $registered->assertSeeInHtml('$15 per month');
    $registered->assertSeeInHtml('Ada Lovelace');
    $registered->assertSeeInHtml(route('subscription.index'));

    $invite = new MembershipCustomPriceMail(price: 18, name: null, registered: false);
    $invite->assertSeeInHtml('$18 per month');
    $invite->assertSeeInHtml(route('register'));
});
