<?php

use App\Enums\CreditTransactionType;
use App\Enums\LeadIssueStatus;
use App\Enums\LeadRefundStatus;
use App\Enums\RoleEnum;
use App\Exceptions\Payments\LeadUnavailableException;
use App\Mail\LeadRefundedMail;
use App\Models\CreditTransaction;
use App\Models\LeadIssueReport;
use App\Models\LeadPurchase;
use App\Models\LeadRefund;
use App\Models\User;
use App\Services\CreditService;
use App\Services\LeadPurchaseService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Sleep::fake();
    Http::preventStrayRequests();
    configureSquare();
    fakeSquareHttp([]);
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    $this->admin = User::factory()->create(['name' => 'Admin Amy']);
    $this->admin->assignRole(RoleEnum::SUPER_ADMIN->value);
});

/**
 * An open return request for a paid purchase of $price dollars, $credits of it paid with Credits.
 *
 * @return array{0: LeadIssueReport, 1: LeadPurchase}
 */
function openReturn(int $price = 100, int $credits = 0): array
{
    $purchase = LeadPurchase::factory()->create([
        'price' => $price,
        'credits_applied' => $credits,
        'amount_cents' => ($price - $credits) * 100,
        'square_payment_id' => $price - $credits > 0 ? 'PAY-'.Str::random(10) : null,
    ]);
    $report = LeadIssueReport::factory()->create([
        'lead_purchase_id' => $purchase->id,
        'user_id' => $purchase->user_id,
        'price' => $price,
        'lead_reference' => 'V-'.$purchase->leadable_id,
    ]);

    return [$report, $purchase];
}

function refundPayload(array $overrides = []): array
{
    return $overrides + ['type' => 'credit', 'lead_action' => 'resell', 'admin_note' => 'Checked and refunded.'];
}

test('guests and members cannot refund', function () {
    [$report] = openReturn();

    $this->post(route('admin.lead-issues.refund', $report), refundPayload())->assertRedirect(route('login'));
    $this->actingAs(makeOperator())->post(route('admin.lead-issues.refund', $report), refundPayload())->assertForbidden();
    $this->actingAs(makeOperator())->get(route('admin.refunds.index'))->assertForbidden();

    expect(LeadRefund::query()->count())->toBe(0);
});

test('the admin sees how the member paid before refunding', function () {
    [$report] = openReturn(100, 40);

    $this->actingAs($this->admin)
        ->get(route('admin.lead-issues.show', $report))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.payment.total', 100)
            ->where('report.payment.credits', 40)
            ->where('report.payment.card', 60)
            ->where('report.payment.can_refund', true)
            ->where('report.payment.can_refund_dollars', true)
            ->where('report.refund', null)
        );
});

test('a credit refund gives the member Credits and puts the lead back on sale', function () {
    [$report, $purchase] = openReturn(100, 0);

    $this->actingAs($this->admin)
        ->post(route('admin.lead-issues.refund', $report), refundPayload(['type' => 'credit', 'lead_action' => 'resell']))
        ->assertSessionHasNoErrors();

    $refund = LeadRefund::query()->sole();
    $member = $purchase->user->refresh();

    expect($refund->status)->toBe(LeadRefundStatus::COMPLETED)
        ->and($refund->credit_amount)->toBe(100)
        ->and($refund->card_amount)->toBe(0)
        ->and($member->credit_balance)->toBe(100)
        ->and($report->refresh()->status)->toBe(LeadIssueStatus::APPROVED)
        ->and($report->resolved_by)->toBe($this->admin->id)
        ->and($purchase->refresh()->status)->toBe(LeadPurchase::STATUS_REFUNDED)
        ->and($purchase->refunded_at)->not->toBeNull()
        ->and($purchase->lead_lock)->toBeNull()
        ->and(squareCallCount())->toBe(0)
        ->and(CreditTransaction::query()->where('type', CreditTransactionType::REFUND_CREDIT)->sole()->amount)->toBe(100);

    Mail::assertQueued(LeadRefundedMail::class, fn (LeadRefundedMail $mail) => $mail->hasTo($member->email));

    $this->actingAs($member)->get(route('leads.purchased'))->assertInertia(fn (Assert $page) => $page->has('purchasedLeads', 0));
});

test('after a resell refund another member can buy the lead, with keep off sale nobody can', function () {
    configureSquare();
    fakeSquareHttp([]);
    fakeSquareHttp([]);

    [$resellReport, $resellPurchase] = openReturn();
    [$keepReport, $keepPurchase] = openReturn();

    $this->actingAs($this->admin)->post(route('admin.lead-issues.refund', $resellReport), refundPayload(['lead_action' => 'resell']));
    $this->actingAs($this->admin)->post(route('admin.lead-issues.refund', $keepReport), refundPayload(['lead_action' => 'keep_off_sale']));

    expect($resellPurchase->refresh()->lead_lock)->toBeNull()
        ->and($keepPurchase->refresh()->lead_lock)->not->toBeNull();

    $service = app(LeadPurchaseService::class);
    $buyer = operatorWithCreditsForRefund(500);

    expect(fn () => $service->checkout($buyer, 'vending', $keepPurchase->leadable_id, null, true))
        ->toThrow(LeadUnavailableException::class);

    $bought = $service->checkout($buyer, 'vending', $resellPurchase->leadable_id, null, true);

    expect($bought->status)->toBe(LeadPurchase::STATUS_PAID);
});

function operatorWithCreditsForRefund(int $credits): User
{
    $operator = makeOperator();
    app(CreditService::class)->grant($operator, $credits, CreditTransactionType::REFUND_CREDIT);

    return $operator;
}

test('a dollar refund sends the card part to Square and the credit part back as Credits', function () {
    fakeSquareHttp(['connect.squareupsandbox.com/v2/refunds' => Http::response([
        'refund' => ['id' => 'REF1', 'status' => 'PENDING'],
    ])]);

    [$report, $purchase] = openReturn(100, 40);

    $this->actingAs($this->admin)
        ->post(route('admin.lead-issues.refund', $report), refundPayload(['type' => 'dollar']))
        ->assertSessionHasNoErrors();

    $refund = LeadRefund::query()->sole();

    expect($refund->type->value)->toBe('dollar')
        ->and($refund->credit_amount)->toBe(40)
        ->and($refund->card_amount)->toBe(60)
        ->and($refund->status)->toBe(LeadRefundStatus::PENDING)
        ->and($refund->square_refund_id)->toBe('REF1')
        ->and($purchase->user->refresh()->credit_balance)->toBe(40);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/v2/refunds')
        && $request['payment_id'] === $purchase->square_payment_id
        && $request['amount_money']['amount'] === 6000
        && $request['idempotency_key'] === $refund->idempotency_key);
});

test('a dollar refund needs a card payment', function () {
    fakeSquareHttp([]);

    [$report] = openReturn(100, 100);

    $this->actingAs($this->admin)
        ->post(route('admin.lead-issues.refund', $report), refundPayload(['type' => 'dollar']))
        ->assertSessionHasErrors('type');

    expect(LeadRefund::query()->count())->toBe(0)->and($report->refresh()->status)->toBe(LeadIssueStatus::OPEN);
});

test('a card refund that Square rejects is marked failed and can be retried', function () {
    Http::fake([
        '*__inertia_ssr*' => Http::response('', 404),
        'connect.squareupsandbox.com/v2/refunds' => Http::sequence()
            ->push(['errors' => [['category' => 'REFUND_ERROR', 'code' => 'REFUND_DECLINED', 'detail' => 'Window passed']]], 400)
            ->push(['refund' => ['id' => 'REF2', 'status' => 'COMPLETED']]),
    ]);

    [$report] = openReturn(100, 0);

    $this->actingAs($this->admin)->post(route('admin.lead-issues.refund', $report), refundPayload(['type' => 'dollar']));

    $refund = LeadRefund::query()->sole();

    expect($refund->status)->toBe(LeadRefundStatus::FAILED)
        ->and($refund->failure_message)->toBe('Window passed');

    $this->actingAs($this->admin)->post(route('admin.refunds.retry', $refund))->assertSessionHasNoErrors();

    expect($refund->refresh()->status)->toBe(LeadRefundStatus::COMPLETED)
        ->and($refund->square_refund_id)->toBe('REF2');
});

test('a request can only be refunded once and only while open', function () {
    fakeSquareHttp([]);

    [$report, $purchase] = openReturn();

    $this->actingAs($this->admin)->post(route('admin.lead-issues.refund', $report), refundPayload());
    $this->actingAs($this->admin)
        ->post(route('admin.lead-issues.refund', $report), refundPayload())
        ->assertSessionHasErrors('type');

    [$rejected] = openReturn();
    $rejected->update(['status' => LeadIssueStatus::REJECTED]);

    $this->actingAs($this->admin)
        ->post(route('admin.lead-issues.refund', $rejected), refundPayload())
        ->assertSessionHasErrors('type');

    expect(LeadRefund::query()->count())->toBe(1)->and($purchase->user->refresh()->credit_balance)->toBe(100);
});

test('the refund needs a valid type, lead option and note', function () {
    [$report] = openReturn();

    $this->actingAs($this->admin)
        ->post(route('admin.lead-issues.refund', $report), ['type' => 'cash', 'lead_action' => 'x', 'admin_note' => ''])
        ->assertSessionHasErrors(['type', 'lead_action', 'admin_note']);
});

test('the member sees their refund history with basic lead info only', function () {
    [$report, $purchase] = openReturn(100, 0);

    $this->actingAs($this->admin)->post(route('admin.lead-issues.refund', $report), refundPayload());
    [$otherReport] = openReturn();
    $this->actingAs($this->admin)->post(route('admin.lead-issues.refund', $otherReport), refundPayload());

    $response = $this->actingAs($purchase->user)
        ->get(route('leads.refunds'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/leads/refunds')
            ->has('requests', 1)
            ->where('requests.0.status', 'approved')
            ->where('requests.0.admin_note', 'Checked and refunded.')
            ->where('requests.0.refund.total_amount', 100)
            ->where('requests.0.refund.credit_amount', 100)
            ->where('requests.0.credits_paid', 0)
            ->where('requests.0.card_paid', 100)
            ->has('creditHistory', 1)
            ->where('creditHistory.0.amount', 100)
            ->where('credits', 100)
        );

    $props = json_encode($response->viewData('page')['props']['requests']);

    expect($props)->not->toContain('email')->and($props)->not->toContain('phone');
});

test('the member sees open and rejected requests with the reason too', function () {
    $operator = makeOperator();
    $purchaseA = LeadPurchase::factory()->create(['user_id' => $operator->id]);
    $purchaseB = LeadPurchase::factory()->create(['user_id' => $operator->id]);

    LeadIssueReport::factory()->create(['lead_purchase_id' => $purchaseA->id, 'user_id' => $operator->id, 'reason' => 'Nobody answered the phone.']);
    LeadIssueReport::factory()->rejected()->create(['lead_purchase_id' => $purchaseB->id, 'user_id' => $operator->id]);

    $this->actingAs($operator)
        ->get(route('leads.refunds'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('requests', 2)
            ->where('requests.0.status', 'rejected')
            ->where('requests.1.status', 'open')
            ->where('requests.1.reason', 'Nobody answered the phone.')
            ->where('requests.1.refund', null)
        );
});

test('the member opens one request on its own page and cannot open anyone elses', function () {
    [$report, $purchase] = openReturn(100, 40);

    $this->actingAs($this->admin)->post(route('admin.lead-issues.refund', $report), refundPayload());

    $this->actingAs($purchase->user)
        ->get(route('leads.refunds.show', $report))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/leads/refund-details')
            ->where('returnRequest.id', $report->id)
            ->where('returnRequest.credits_paid', 40)
            ->where('returnRequest.card_paid', 60)
            ->where('returnRequest.admin_note', 'Checked and refunded.')
            ->where('returnRequest.refund.total_amount', 100)
        );

    $this->actingAs(makeOperator())->get(route('leads.refunds.show', $report))->assertNotFound();
    auth()->logout();
    $this->get(route('leads.refunds.show', $report))->assertRedirect(route('login'));
});

test('billing keeps the refunded charge and adds a refund line', function () {
    fakeSquareHttp([]);

    [$report, $purchase] = openReturn(100, 40);

    $this->actingAs($this->admin)->post(route('admin.lead-issues.refund', $report), refundPayload(['type' => 'credit']));

    $this->actingAs($purchase->user)
        ->get(route('billing.index'))
        ->assertInertia(function (Assert $page) use ($purchase) {
            $page->where('invoices', function ($invoices) use ($purchase) {
                $rows = collect($invoices)->keyBy('id');
                $charge = $rows['LP-'.$purchase->id];
                $refund = $rows->first(fn ($row) => $row['source'] === 'refund');

                return $charge['status'] === 'Refunded'
                    && $charge['credits_used'] === 40
                    && $charge['amount'] == 60
                    && $refund['credits_refunded'] === 100
                    && $refund['amount'] == 0
                    && $refund['status'] === 'Completed';
            });
        });
});

test('billing shows a purchase paid fully with credits and a card refund as negative', function () {
    fakeSquareHttp(['connect.squareupsandbox.com/v2/refunds' => Http::response(['refund' => ['id' => 'R9', 'status' => 'PENDING']])]);

    [$creditOnly] = openReturn(80, 80);
    $creditOnlyPurchase = $creditOnly->leadPurchase;
    [$cardReport, $cardPurchase] = openReturn(100, 0);

    $this->actingAs($this->admin)->post(route('admin.lead-issues.refund', $cardReport), refundPayload(['type' => 'dollar']));

    $this->actingAs($creditOnlyPurchase->user)
        ->get(route('billing.index'))
        ->assertInertia(fn (Assert $page) => $page->where('invoices.0.credits_used', 80)->where('invoices.0.amount', 0));

    $this->actingAs($cardPurchase->user)
        ->get(route('billing.index'))
        ->assertInertia(fn (Assert $page) => $page->where('invoices', fn ($invoices) => collect($invoices)->contains(
            fn ($row) => $row['source'] === 'refund' && $row['amount'] == -100 && $row['status'] === 'Processing',
        )));
});

test('the admin lists refunds and can filter them', function () {
    fakeSquareHttp(['connect.squareupsandbox.com/v2/refunds' => Http::response(['refund' => ['id' => 'R', 'status' => 'PENDING']])]);

    [$creditReport] = openReturn(100, 0);
    [$dollarReport] = openReturn(100, 0);

    $this->actingAs($this->admin)->post(route('admin.lead-issues.refund', $creditReport), refundPayload());
    $this->actingAs($this->admin)->post(route('admin.lead-issues.refund', $dollarReport), refundPayload(['type' => 'dollar']));

    $this->actingAs($this->admin)
        ->get(route('admin.refunds.index'))
        ->assertInertia(fn (Assert $page) => $page->has('refunds.data', 2));

    $this->actingAs($this->admin)
        ->get(route('admin.refunds.index', ['type' => 'dollar']))
        ->assertInertia(fn (Assert $page) => $page->has('refunds.data', 1)->where('refunds.data.0.type', 'dollar'));
});

test('admin and member see the transaction ids, card and refund details', function () {
    fakeSquareHttp(['connect.squareupsandbox.com/v2/refunds' => Http::response(['refund' => ['id' => 'REF-DET', 'status' => 'PENDING']])]);

    [$report, $purchase] = openReturn(100, 40);
    $purchase->update(['square_order_id' => 'ORD-1', 'square_receipt_number' => 'rcpt1']);

    $this->actingAs($this->admin)
        ->get(route('admin.lead-issues.show', $report))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.payment.card_label', 'VISA ending 1111')
            ->where('report.payment_details', fn ($rows) => collect($rows)->contains(fn ($row) => $row['label'] === 'Transaction ID' && $row['value'] === $purchase->square_payment_id)
                && collect($rows)->contains(fn ($row) => $row['label'] === 'Order ID' && $row['value'] === 'ORD-1'))
        );

    $this->actingAs($this->admin)->post(route('admin.lead-issues.refund', $report), refundPayload(['type' => 'dollar']));

    $refundRows = fn ($rows) => collect($rows)->contains(fn ($row) => $row['label'] === 'Refund ID' && $row['value'] === 'REF-DET')
        && collect($rows)->contains(fn ($row) => $row['label'] === 'Refunded to' && $row['value'] === 'VISA ending 1111')
        && collect($rows)->contains(fn ($row) => $row['label'] === 'Original transaction ID' && $row['value'] === $purchase->square_payment_id);

    $this->actingAs($this->admin)
        ->get(route('admin.lead-issues.show', $report))
        ->assertInertia(fn (Assert $page) => $page->where('report.refund.details', $refundRows));

    $this->actingAs($purchase->user)
        ->get(route('leads.refunds.show', $report))
        ->assertInertia(fn (Assert $page) => $page
            ->where('returnRequest.refund.details', $refundRows)
            ->where('returnRequest.payment_details', fn ($rows) => collect($rows)->contains(fn ($row) => $row['label'] === 'Transaction ID'))
        );
});
