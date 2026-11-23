<?php

use App\Enums\LeadIssueStatus;
use App\Enums\RoleEnum;
use App\Models\LeadIssueReport;
use App\Models\LeadIssueSetting;
use App\Models\LeadPurchase;
use App\Models\User;
use App\Services\LeadIssueSettingsService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    $this->admin = User::factory()->create(['name' => 'Admin Amy']);
    $this->admin->assignRole(RoleEnum::SUPER_ADMIN->value);
});

test('guests and operators cannot see or change lead issues', function () {
    $report = LeadIssueReport::factory()->create();

    $this->get(route('admin.lead-issues.index'))->assertRedirect(route('login'));

    $operator = makeOperator();

    $this->actingAs($operator)->get(route('admin.lead-issues.index'))->assertForbidden();
    $this->actingAs($operator)->get(route('admin.lead-issues.show', $report))->assertForbidden();
    $this->actingAs($operator)
        ->put(route('admin.lead-issues.update', $report), ['status' => 'approved', 'admin_note' => 'Okay'])
        ->assertForbidden();

    expect($report->refresh()->status)->toBe(LeadIssueStatus::OPEN);
});

test('the admin sees the requests newest first with the reason', function () {
    $older = LeadIssueReport::factory()->create(['lead_reference' => 'V-1', 'created_at' => now()->subDay()]);
    $newer = LeadIssueReport::factory()->create(['lead_reference' => 'V-2', 'reason' => 'This is the reason the operator wrote.']);

    $this->actingAs($this->admin)
        ->get(route('admin.lead-issues.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/lead-issues/index')
            ->has('reports.data', 2)
            ->where('reports.data.0.id', $newer->id)
            ->where('reports.data.0.ticket', 'T-'.$newer->id)
            ->where('reports.data.0.reason', 'This is the reason the operator wrote.')
            ->where('reports.data.1.id', $older->id)
        );
});

test('the list can be searched and filtered', function () {
    $open = LeadIssueReport::factory()->create(['lead_reference' => 'V-11', 'lead_name' => 'Alpha Gym']);
    LeadIssueReport::factory()->approved()->create(['lead_reference' => 'V-22', 'lead_name' => 'Beta Hotel', 'category' => 'cannot_serve']);

    $this->actingAs($this->admin)
        ->get(route('admin.lead-issues.index', ['search' => 'alpha']))
        ->assertInertia(fn (Assert $page) => $page->has('reports.data', 1)->where('reports.data.0.id', $open->id));

    $this->actingAs($this->admin)
        ->get(route('admin.lead-issues.index', ['status' => 'approved']))
        ->assertInertia(fn (Assert $page) => $page->has('reports.data', 1)->where('reports.data.0.lead_reference', 'V-22'));

    $this->actingAs($this->admin)
        ->get(route('admin.lead-issues.index', ['category' => 'cannot_serve']))
        ->assertInertia(fn (Assert $page) => $page->has('reports.data', 1)->where('reports.data.0.lead_reference', 'V-22'));

    $operator = $open->user;
    $this->actingAs($this->admin)
        ->get(route('admin.lead-issues.index', ['search' => $operator->email]))
        ->assertInertia(fn (Assert $page) => $page->has('reports.data', 1)->where('reports.data.0.id', $open->id));
});

test('the admin can open the details of a request', function () {
    $report = LeadIssueReport::factory()->create(['lead_name' => 'Alpha Gym', 'price' => 150]);

    $this->actingAs($this->admin)
        ->get(route('admin.lead-issues.show', $report))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/lead-issues/show')
            ->where('report.id', $report->id)
            ->where('report.lead_name', 'Alpha Gym')
            ->where('report.price', 150)
            ->where('report.status', 'open')
            ->where('report.operator.email', $report->user->email)
            ->where('report.reason', $report->reason)
        );
});

test('the admin can reject a request and nothing else changes', function () {
    $purchase = LeadPurchase::factory()->create();
    $report = LeadIssueReport::factory()->create(['lead_purchase_id' => $purchase->id, 'user_id' => $purchase->user_id]);

    $this->actingAs($this->admin)
        ->put(route('admin.lead-issues.update', $report), ['status' => 'rejected', 'admin_note' => 'The details were correct.'])
        ->assertSessionHasNoErrors();

    $report->refresh();

    expect($report->status)->toBe(LeadIssueStatus::REJECTED)
        ->and($report->resolved_by)->toBe($this->admin->id)
        ->and($report->resolved_at)->not->toBeNull()
        ->and($purchase->refresh()->status)->toBe(LeadPurchase::STATUS_PAID)
        ->and($purchase->refunded_at)->toBeNull();

    $this->actingAs($this->admin)
        ->get(route('admin.lead-issues.show', $report))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.resolved_by', 'Admin Amy')
            ->where('report.admin_note', 'The details were correct.')
        );
});

test('a decision needs a note and cannot approve without a refund or put a request back to open', function () {
    $report = LeadIssueReport::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.lead-issues.update', $report), ['status' => 'rejected', 'admin_note' => ''])
        ->assertSessionHasErrors('admin_note');

    $this->actingAs($this->admin)
        ->put(route('admin.lead-issues.update', $report), ['status' => 'approved', 'admin_note' => 'No refund'])
        ->assertSessionHasErrors('status');

    $this->actingAs($this->admin)
        ->put(route('admin.lead-issues.update', $report), ['status' => 'open', 'admin_note' => 'Back to open'])
        ->assertSessionHasErrors('status');

    expect($report->refresh()->status)->toBe(LeadIssueStatus::OPEN);
});

test('the operator sees the decision on the tickets page', function () {
    $purchase = LeadPurchase::factory()->create();
    $report = LeadIssueReport::factory()->create(['lead_purchase_id' => $purchase->id, 'user_id' => $purchase->user_id]);

    $this->actingAs($this->admin)
        ->put(route('admin.lead-issues.update', $report), ['status' => 'rejected', 'admin_note' => 'Details were correct.']);

    $this->actingAs($purchase->user)
        ->get(route('leads.tickets'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tickets.0.status', 'Return rejected')
            ->where('tickets.0.outcome', 'Details were correct.')
        );
});

test('the admin sets and removes the return time limit', function () {
    $settings = app(LeadIssueSettingsService::class);

    $this->actingAs($this->admin)
        ->get(route('admin.lead-issues.index'))
        ->assertInertia(fn (Assert $page) => $page->where('settings.report_window_days', null));

    $this->actingAs($this->admin)
        ->put(route('admin.lead-issues.settings.update'), ['report_window_days' => 7])
        ->assertSessionHasNoErrors();

    expect($settings->reportWindowDays())->toBe(7);

    $this->actingAs($this->admin)
        ->get(route('admin.lead-issues.index'))
        ->assertInertia(fn (Assert $page) => $page->where('settings.report_window_days', 7));

    $this->actingAs($this->admin)
        ->put(route('admin.lead-issues.settings.update'), ['report_window_days' => ''])
        ->assertSessionHasNoErrors();

    expect($settings->reportWindowDays())->toBeNull()
        ->and(LeadIssueSetting::query()->count())->toBe(1);
});

test('the return time limit is validated and only editors can change it', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.lead-issues.settings.update'), ['report_window_days' => 0])
        ->assertSessionHasErrors('report_window_days');

    $this->actingAs($this->admin)
        ->put(route('admin.lead-issues.settings.update'), ['report_window_days' => 'abc'])
        ->assertSessionHasErrors('report_window_days');

    $this->actingAs(makeOperator())
        ->put(route('admin.lead-issues.settings.update'), ['report_window_days' => 5])
        ->assertForbidden();

    expect(app(LeadIssueSettingsService::class)->reportWindowDays())->toBeNull();
});
