<?php

use App\Enums\RoleEnum;
use App\Models\LeadIssueReport;
use App\Models\LeadPurchase;
use App\Models\LeadRefund;
use App\Models\MembershipPayment;
use App\Models\User;
use App\Models\VendingRequest;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    fakeSquareHttp([]);
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    config([
        'receipts.business_name' => 'Acme Vending Co',
        'receipts.address' => '1 Main St, Houston TX',
        'receipts.email' => 'help@acme.test',
        'receipts.phone' => '555-0100',
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::SUPER_ADMIN->value);
});

test('a member opens the receipt of their lead purchase', function () {
    $operator = makeOperator();
    $lead = VendingRequest::factory()->create(['business_name' => 'Hidden Biz', 'email' => 'hidden@example.com', 'phone' => '555-0199', 'city' => 'Houston']);
    $purchase = LeadPurchase::factory()->create([
        'user_id' => $operator->id,
        'leadable_id' => $lead->id,
        'price' => 100,
        'credits_applied' => 40,
        'amount_cents' => 6000,
    ]);

    $response = $this->actingAs($operator)
        ->get(route('receipts.lead', $purchase))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('receipts/show')
            ->where('receipt.number', 'LP-'.$purchase->id)
            ->where('receipt.status', 'Paid')
            ->where('receipt.total', 100)
            ->where('receipt.credits_applied', 40)
            ->where('receipt.card_paid', 60)
            ->where('receipt.payment_method', 'VISA ending 1111')
            ->where('receipt.seller.name', 'Acme Vending Co')
            ->where('receipt.seller.address', '1 Main St, Houston TX')
            ->where('receipt.details', fn ($details) => collect($details)->contains(fn ($row) => $row['label'] === 'Transaction ID' && $row['value'] === $purchase->square_payment_id)
                && collect($details)->contains(fn ($row) => $row['label'] === 'Card' && $row['value'] === 'VISA ending 1111')
                && collect($details)->contains(fn ($row) => $row['label'] === 'Credits used' && $row['value'] === '40 Credits'))
            ->where('receipt.customer.email', $operator->email)
            ->where('backUrl', route('billing.index'))
        );

    $props = json_encode($response->viewData('page')['props']['receipt']);

    expect($props)->not->toContain('Hidden Biz')->and($props)->not->toContain('hidden@example.com')->and($props)->not->toContain('555-0199');
});

test('a purchase paid only with credits shows no card amount', function () {
    $operator = makeOperator();
    $purchase = LeadPurchase::factory()->create([
        'user_id' => $operator->id,
        'price' => 80,
        'credits_applied' => 80,
        'amount_cents' => 0,
        'card_brand' => null,
        'card_last4' => null,
        'square_payment_id' => null,
    ]);

    $this->actingAs($operator)
        ->get(route('receipts.lead', $purchase))
        ->assertInertia(fn (Assert $page) => $page
            ->where('receipt.card_paid', 0)
            ->where('receipt.credits_applied', 80)
            ->where('receipt.payment_method', 'Credits')
        );
});

test('a refunded purchase shows Refunded and links to the refund receipt', function () {
    $purchase = LeadPurchase::factory()->create(['status' => LeadPurchase::STATUS_REFUNDED]);
    $report = LeadIssueReport::factory()->approved()->create(['lead_purchase_id' => $purchase->id, 'user_id' => $purchase->user_id]);
    $refund = LeadRefund::factory()->create(['lead_issue_report_id' => $report->id, 'total_amount' => 150, 'credit_amount' => 150]);

    $this->actingAs($purchase->user)
        ->get(route('receipts.lead', $purchase))
        ->assertInertia(fn (Assert $page) => $page
            ->where('receipt.status', 'Refunded')
            ->where('receipt.related.url', route('receipts.refund', $refund))
        );

    $this->actingAs($purchase->user)
        ->get(route('receipts.refund', $refund))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('receipt.number', 'RF-'.$refund->id)
            ->where('receipt.status', 'Completed')
            ->where('receipt.credits_applied', 150)
            ->where('receipt.payment_method', 'Credits')
        );
});

test('a member opens a membership payment receipt', function () {
    $operator = makeOperator();
    $payment = MembershipPayment::factory()->create(['user_id' => $operator->id]);

    $this->actingAs($operator)
        ->get(route('receipts.membership', $payment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('receipt.number', 'MB-'.$payment->id)
            ->where('receipt.items.0.description', 'Monthly membership')
        );
});

test('other members and guests cannot open a receipt and unpaid ones are missing', function () {
    $purchase = LeadPurchase::factory()->create();
    $pending = LeadPurchase::factory()->pending()->create(['user_id' => $purchase->user_id]);

    $this->actingAs(makeOperator())->get(route('receipts.lead', $purchase))->assertNotFound();
    $this->actingAs($purchase->user)->get(route('receipts.lead', $pending))->assertNotFound();

    auth()->logout();
    $this->get(route('receipts.lead', $purchase))->assertRedirect(route('login'));
});

test('an admin opens any receipt', function () {
    $purchase = LeadPurchase::factory()->create(['square_payment_id' => 'PAY-ADMIN']);

    $this->actingAs($this->admin)
        ->get(route('receipts.lead', $purchase))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('receipt.details', fn ($details) => collect($details)->contains(fn ($row) => $row['value'] === 'PAY-ADMIN'))
            ->where('backUrl', route('admin.payments.index'))
        );
});

test('billing, purchased leads and admin pages link to our receipt, not Square', function () {
    $operator = makeOperator();
    $purchase = LeadPurchase::factory()->create(['user_id' => $operator->id]);
    $url = route('receipts.lead', $purchase);

    $this->actingAs($operator)
        ->get(route('billing.index'))
        ->assertInertia(fn (Assert $page) => $page->where('invoices.0.receipt_url', $url));

    $this->actingAs($operator)
        ->get(route('leads.purchased'))
        ->assertInertia(fn (Assert $page) => $page->where('purchasedLeads.0.receipt_url', $url));

    $this->actingAs($this->admin)
        ->get(route('admin.payments.index'))
        ->assertInertia(fn (Assert $page) => $page->where('payments.data.0.receipt_url', $url));
});
