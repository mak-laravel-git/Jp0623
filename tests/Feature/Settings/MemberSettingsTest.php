<?php

use App\Enums\RoleEnum;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

test('members are sent to account info instead of the settings pages', function (string $route) {
    $this->actingAs(makeOperator())
        ->get(route($route))
        ->assertRedirect(route('account.index'));
})->with(['profile.edit', 'security.edit']);

test('members cannot change or delete their account through settings', function () {
    $member = makeOperator();
    $email = $member->email;

    $this->actingAs($member)
        ->patch(route('profile.update'), ['name' => 'Changed', 'email' => 'changed@example.com'])
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertForbidden();

    expect(User::query()->find($member->id)?->email)->toBe($email);
});

test('admins keep their settings pages', function (RoleEnum $role) {
    $admin = User::factory()->create();
    $admin->assignRole($role->value);

    $this->actingAs($admin)->get(route('profile.edit'))->assertOk();

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk();
})->with([
    'admin' => RoleEnum::ADMIN,
    'super admin' => RoleEnum::SUPER_ADMIN,
]);

test('the appearance page is gone', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::SUPER_ADMIN->value);

    $this->actingAs($admin)->get('/settings/appearance')->assertNotFound();
});
