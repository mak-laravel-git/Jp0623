<?php

use App\Enums\RoleEnum;
use App\Models\MicroMarketFaq;
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

test('guests cannot access micro market faqs', function () {
    $this->get(route('admin.micro-market-faqs.index'))->assertRedirect(route('login'));
});

test('users without permission are forbidden from micro market faqs', function () {
    $plain = User::factory()->create();

    $this->actingAs($plain)
        ->get(route('admin.micro-market-faqs.index'))
        ->assertForbidden();
});

test('super admin can view the micro market faqs list', function () {
    MicroMarketFaq::factory()->create(['question' => 'How much space is needed?']);

    $this->actingAs($this->admin)
        ->get(route('admin.micro-market-faqs.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/micro-market-faqs/index')
                ->has('faqs.data', 1)
                ->has('filters')
        );
});

test('an micro market faq can be created', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.micro-market-faqs.store'), [
            'question' => 'Do you restock the shelves?',
            'answer' => 'Yes, on a schedule that fits your market.',
            'sort_order' => 1,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.micro-market-faqs.index'));

    $faq = MicroMarketFaq::query()->where('question', 'Do you restock the shelves?')->sole();

    expect($faq->answer)->toBe('Yes, on a schedule that fits your market.')
        ->and($faq->is_active)->toBeTrue();
});

test('creating an micro market faq validates required fields', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.micro-market-faqs.store'), [
            'question' => '',
        ])
        ->assertSessionHasErrors(['question', 'answer']);
});

test('an micro market faq can be updated', function () {
    $faq = MicroMarketFaq::factory()->create(['question' => 'Old question?']);

    $this->actingAs($this->admin)
        ->put(route('admin.micro-market-faqs.update', $faq), [
            'question' => 'Updated question?',
            'answer' => 'Updated answer.',
            'sort_order' => 5,
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.micro-market-faqs.index'));

    $faq->refresh();

    expect($faq->question)->toBe('Updated question?')
        ->and($faq->answer)->toBe('Updated answer.')
        ->and($faq->is_active)->toBeFalse();
});

test('an micro market faq can be deleted', function () {
    $faq = MicroMarketFaq::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.micro-market-faqs.destroy', $faq))
        ->assertRedirect();

    expect(MicroMarketFaq::query()->whereKey($faq->id)->exists())->toBeFalse();
});
