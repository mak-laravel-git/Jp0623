<?php

use App\Enums\RoleEnum;
use App\Models\MembershipCustomPrice;
use App\Models\MembershipPriceHistory;
use App\Models\MembershipSetting;
use App\Models\OperatorMembership;
use App\Models\User;
use App\Services\MembershipSettingsService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    $this->admin = User::factory()->create(['name' => 'Admin Amy']);
    $this->admin->assignRole(RoleEnum::SUPER_ADMIN->value);

    app(MembershipSettingsService::class)->update([
        'mode' => MembershipSetting::MODE_FREE,
        'monthly_price' => 29,
        'grace_days' => 0,
    ]);
});

test('setting a price is logged as created with who did it', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.membership-settings.custom-prices.store'), [
            'email' => 'New@Example.com',
            'monthly_price' => 15,
            'send_email' => true,
        ])
        ->assertSessionHasNoErrors();

    $entry = MembershipPriceHistory::query()->sole();

    expect($entry->email)->toBe('new@example.com')
        ->and($entry->action)->toBe('created')
        ->and($entry->old_price)->toBeNull()
        ->and($entry->new_price)->toBe(15)
        ->and($entry->changed_by)->toBe($this->admin->id)
        ->and($entry->email_sent)->toBeTrue();
});

test('changing a price logs the old and the new price, and an unchanged price logs nothing', function () {
    $post = fn (int $price) => $this->actingAs($this->admin)
        ->post(route('admin.membership-settings.custom-prices.store'), ['email' => 'a@example.com', 'monthly_price' => $price])
        ->assertSessionHasNoErrors();

    $post(10);
    $post(20);
    $post(20);

    $entries = MembershipPriceHistory::query()->orderBy('id')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries[1]->action)->toBe('updated')
        ->and($entries[1]->old_price)->toBe(10)
        ->and($entries[1]->new_price)->toBe(20)
        ->and($entries[1]->email_sent)->toBeFalse();
});

test('editing a price from the list is logged too', function () {
    $row = MembershipCustomPrice::factory()->create(['email' => 'e@example.com', 'monthly_price' => 12]);

    $this->actingAs($this->admin)
        ->put(route('admin.membership-settings.custom-prices.update', $row), ['monthly_price' => 18])
        ->assertSessionHasNoErrors();

    $entry = MembershipPriceHistory::query()->sole();

    expect($entry->action)->toBe('updated')
        ->and($entry->old_price)->toBe(12)
        ->and($entry->new_price)->toBe(18);
});

test('removing a price logs the old price and the default price the member now pays, and the log stays', function () {
    $member = makeOperator();
    $row = MembershipCustomPrice::factory()->forUser($member)->create(['monthly_price' => 15]);

    $this->actingAs($this->admin)
        ->delete(route('admin.membership-settings.custom-prices.destroy', $row))
        ->assertRedirect();

    $entry = MembershipPriceHistory::query()->sole();

    expect(MembershipCustomPrice::query()->count())->toBe(0)
        ->and($entry->action)->toBe('removed')
        ->and($entry->old_price)->toBe(15)
        ->and($entry->new_price)->toBe(29)
        ->and($entry->user_id)->toBe($member->id)
        ->and(OperatorMembership::query()->where('user_id', $member->id)->value('pays_default_price'))->toBeTruthy();
});

test('removing a price reserved for an email without an account logs it without a new price', function () {
    $row = MembershipCustomPrice::factory()->create(['monthly_price' => 9]);

    $this->actingAs($this->admin)->delete(route('admin.membership-settings.custom-prices.destroy', $row));

    $entry = MembershipPriceHistory::query()->sole();

    expect($entry->action)->toBe('removed')
        ->and($entry->old_price)->toBe(9)
        ->and($entry->new_price)->toBeNull()
        ->and($entry->user_id)->toBeNull();
});

test('only admins with the membership permission see the history page', function () {
    $this->get(route('admin.membership-price-history.index'))->assertRedirect(route('login'));

    $this->actingAs(makeOperator())
        ->get(route('admin.membership-price-history.index'))
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->get(route('admin.membership-price-history.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/membership-price-history/index'));
});

test('the history page lists newest first and can be searched and filtered', function () {
    MembershipPriceHistory::factory()->create(['email' => 'old@example.com', 'created_at' => now()->subDay()]);
    MembershipPriceHistory::factory()->create(['email' => 'new@example.com', 'action' => 'updated', 'old_price' => 5, 'new_price' => 8, 'changed_by' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.membership-price-history.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('history.data', 2)
            ->where('history.data.0.email', 'new@example.com')
            ->where('history.data.0.old_price', 5)
            ->where('history.data.0.new_price', 8)
            ->where('history.data.0.changed_by', 'Admin Amy')
        );

    $this->actingAs($this->admin)
        ->get(route('admin.membership-price-history.index', ['search' => 'OLD@']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('history.data', 1)
            ->where('history.data.0.email', 'old@example.com')
        );

    $this->actingAs($this->admin)
        ->get(route('admin.membership-price-history.index', ['action' => 'updated']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('history.data', 1)
            ->where('history.data.0.action', 'updated')
        );
});
