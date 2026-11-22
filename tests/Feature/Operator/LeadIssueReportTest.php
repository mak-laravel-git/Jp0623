<?php

use App\Enums\LeadIssueStatus;
use App\Mail\LeadIssueReportedMail;
use App\Models\LeadIssueReport;
use App\Models\LeadPurchase;
use App\Models\VendingRequest;
use App\Services\LeadIssueSettingsService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    config(['leads.issue_notification_email' => 'admin@example.com']);
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

function issueFormData(array $overrides = []): array
{
    return $overrides + [
        'category' => 'fake_lead',
        'reason' => 'The phone number is wrong and nobody there asked for vending.',
    ];
}

test('an operator can send a paid lead back to the admin with a written reason', function () {
    $operator = makeOperator();
    $lead = VendingRequest::factory()->create(['business_name' => 'Acme Gym', 'city' => 'Houston']);
    $purchase = LeadPurchase::factory()->create(['user_id' => $operator->id, 'leadable_id' => $lead->id, 'price' => 150]);

    $this->actingAs($operator)
        ->post(route('leads.purchases.issue.store', $purchase), issueFormData(['category' => 'cannot_serve']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('leads.tickets'));

    $report = LeadIssueReport::query()->sole();

    expect($report->lead_purchase_id)->toBe($purchase->id)
        ->and($report->user_id)->toBe($operator->id)
        ->and($report->category->value)->toBe('cannot_serve')
        ->and($report->status)->toBe(LeadIssueStatus::OPEN)
        ->and($report->lead_reference)->toBe('V-'.$lead->id)
        ->and($report->lead_name)->toBe('Acme Gym')
        ->and($report->lead_city)->toBe('Houston')
        ->and($report->price)->toBe(150)
        ->and($report->reason)->toContain('phone number is wrong');

    Mail::assertQueued(LeadIssueReportedMail::class, fn (LeadIssueReportedMail $mail) => $mail->hasTo('admin@example.com')
        && $mail->report->is($report));
});

test('the written reason and the reason type are required', function () {
    $operator = makeOperator();
    $purchase = LeadPurchase::factory()->create(['user_id' => $operator->id]);

    $this->actingAs($operator)
        ->post(route('leads.purchases.issue.store', $purchase), ['category' => 'fake_lead', 'reason' => ''])
        ->assertSessionHasErrors('reason');

    $this->actingAs($operator)
        ->post(route('leads.purchases.issue.store', $purchase), issueFormData(['reason' => 'too short']))
        ->assertSessionHasErrors('reason');

    $this->actingAs($operator)
        ->post(route('leads.purchases.issue.store', $purchase), issueFormData(['category' => 'nonsense']))
        ->assertSessionHasErrors('category');

    expect(LeadIssueReport::query()->count())->toBe(0);
});

test('guests and other operators cannot send a lead back', function () {
    $owner = makeOperator();
    $purchase = LeadPurchase::factory()->create(['user_id' => $owner->id]);

    $this->post(route('leads.purchases.issue.store', $purchase), issueFormData())
        ->assertRedirect(route('login'));

    $this->actingAs(makeOperator())
        ->post(route('leads.purchases.issue.store', $purchase), issueFormData())
        ->assertNotFound();

    expect(LeadIssueReport::query()->count())->toBe(0);
});

test('only a paid purchase can be sent back', function () {
    $operator = makeOperator();
    $pending = LeadPurchase::factory()->pending()->create(['user_id' => $operator->id]);

    $this->actingAs($operator)
        ->post(route('leads.purchases.issue.store', $pending), issueFormData())
        ->assertSessionHasErrors('reason');

    expect(LeadIssueReport::query()->count())->toBe(0);
});

test('a purchase can only be sent back once', function () {
    $operator = makeOperator();
    $purchase = LeadPurchase::factory()->create(['user_id' => $operator->id]);

    $this->actingAs($operator)->post(route('leads.purchases.issue.store', $purchase), issueFormData());
    $this->actingAs($operator)
        ->post(route('leads.purchases.issue.store', $purchase), issueFormData())
        ->assertSessionHasErrors('reason');

    expect(LeadIssueReport::query()->count())->toBe(1);
});

test('there is no time limit unless the admin sets one', function () {
    $operator = makeOperator();
    $old = LeadPurchase::factory()->create([
        'user_id' => $operator->id,
        'purchased_at' => now()->subDays(60),
        'paid_at' => now()->subDays(60),
    ]);

    $this->actingAs($operator)
        ->post(route('leads.purchases.issue.store', $old), issueFormData())
        ->assertSessionHasNoErrors();

    expect(LeadIssueReport::query()->count())->toBe(1);
});

test('a time limit set by the admin stops late requests and hides the button', function () {
    app(LeadIssueSettingsService::class)->updateReportWindowDays(7);

    $operator = makeOperator();
    $late = LeadPurchase::factory()->create([
        'user_id' => $operator->id,
        'purchased_at' => now()->subDays(10),
        'paid_at' => now()->subDays(10),
    ]);
    $recent = LeadPurchase::factory()->create(['user_id' => $operator->id, 'purchased_at' => now()->subDays(2)]);

    $this->actingAs($operator)
        ->post(route('leads.purchases.issue.store', $late), issueFormData())
        ->assertSessionHasErrors('reason');

    expect(LeadIssueReport::query()->count())->toBe(0);

    $this->actingAs($operator)
        ->get(route('leads.purchased'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('purchasedLeads', fn ($leads) => collect($leads)->firstWhere('purchase_id', $late->id)['can_report'] === false
                && collect($leads)->firstWhere('purchase_id', $recent->id)['can_report'] === true)
        );
});

test('the request is saved even when no email address is configured or sending fails', function () {
    config(['leads.issue_notification_email' => null]);

    $operator = makeOperator();
    $first = LeadPurchase::factory()->create(['user_id' => $operator->id]);

    $this->actingAs($operator)->post(route('leads.purchases.issue.store', $first), issueFormData());

    Mail::assertNothingQueued();
    expect(LeadIssueReport::query()->count())->toBe(1);

    config(['leads.issue_notification_email' => 'admin@example.com']);
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Mail server unavailable'));

    $second = LeadPurchase::factory()->create(['user_id' => $operator->id]);

    $this->actingAs($operator)
        ->post(route('leads.purchases.issue.store', $second), issueFormData())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('leads.tickets'));

    expect(LeadIssueReport::query()->count())->toBe(2);
});

test('the purchased leads page shows the state of each request', function () {
    $operator = makeOperator();
    $plain = LeadPurchase::factory()->create(['user_id' => $operator->id]);
    $open = LeadPurchase::factory()->create(['user_id' => $operator->id]);
    $approved = LeadPurchase::factory()->create(['user_id' => $operator->id]);
    $rejected = LeadPurchase::factory()->create(['user_id' => $operator->id]);

    LeadIssueReport::factory()->create(['lead_purchase_id' => $open->id, 'user_id' => $operator->id]);
    LeadIssueReport::factory()->approved()->create(['lead_purchase_id' => $approved->id, 'user_id' => $operator->id]);
    LeadIssueReport::factory()->rejected()->create(['lead_purchase_id' => $rejected->id, 'user_id' => $operator->id]);

    $this->actingAs($operator)
        ->get(route('leads.purchased'))
        ->assertInertia(function (Assert $page) use ($plain, $open, $approved, $rejected) {
            $page->where('purchasedLeads', function ($leads) use ($plain, $open, $approved, $rejected) {
                $leads = collect($leads)->keyBy('purchase_id');

                return $leads[$plain->id]['status'] === 'ok' && $leads[$plain->id]['can_report'] === true
                    && $leads[$open->id]['status'] === 'issue_open' && $leads[$open->id]['can_report'] === false
                    && $leads[$approved->id]['status'] === 'issue_approved'
                    && $leads[$rejected->id]['status'] === 'issue_rejected';
            });
        });
});

test('the tickets page lists only the operator own requests with the admin decision', function () {
    $operator = makeOperator();
    $mine = LeadPurchase::factory()->create(['user_id' => $operator->id]);
    $theirs = LeadPurchase::factory()->create();

    LeadIssueReport::factory()->rejected()->create([
        'lead_purchase_id' => $mine->id,
        'user_id' => $operator->id,
        'lead_reference' => 'V-7',
        'lead_name' => 'Acme Gym',
    ]);
    LeadIssueReport::factory()->create(['lead_purchase_id' => $theirs->id]);

    $this->actingAs($operator)
        ->get(route('leads.tickets'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/leads/tickets')
            ->has('tickets', 1)
            ->where('tickets.0.lead', 'V-7 · Acme Gym')
            ->where('tickets.0.status', 'Return rejected')
            ->where('tickets.0.outcome', 'The lead details were correct.')
        );
});
