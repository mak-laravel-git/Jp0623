<?php

use App\Enums\RoleEnum;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

test('admins are sent to their profile instead of the appearance page', function (RoleEnum $role) {
    $admin = User::factory()->create();
    $admin->assignRole($role->value);

    $this->actingAs($admin)
        ->get(route('appearance.edit'))
        ->assertRedirect(route('profile.edit'));
})->with([
    'admin' => RoleEnum::ADMIN,
    'super admin' => RoleEnum::SUPER_ADMIN,
]);

test('members can still open the appearance page', function () {
    $member = User::factory()->create();
    $member->assignRole(RoleEnum::USER->value);

    $this->actingAs($member)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/appearance'));
});
