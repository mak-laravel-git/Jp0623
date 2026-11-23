<?php

namespace App\Services;

use App\Enums\CreditTransactionType;
use App\Enums\LeadIssueStatus;
use App\Enums\LeadRefundAction;
use App\Enums\LeadRefundStatus;
use App\Enums\LeadRefundType;
use App\Exceptions\Payments\SquarePaymentException;
use App\Mail\LeadRefundedMail;
use App\Models\LeadIssueReport;
use App\Models\LeadPurchase;
use App\Models\LeadRefund;
use App\Models\User;
use App\Services\Payments\SquareClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Refunds the full amount of a returned lead, either to the member's card (Square) or as Credits.
 *
 * A purchase paid partly with Credits and partly with a card keeps that split: a dollar refund sends the card part
 * back to the card and returns the Credit part as Credits (Credits can never turn into dollars); a Credit refund
 * returns everything as Credits.
 */
class LeadRefundService
{
    public function __construct(
        private SquareClient $square,
        private CreditService $credits,
    ) {}

    /**
     * What the admin sees before refunding: how the member paid.
     *
     * @return array{total: int, credits: int, card: int, can_refund: bool, can_refund_dollars: bool}
     */
    public function preview(LeadIssueReport $report): array
    {
        $purchase = $report->leadPurchase;
        $credits = (int) $purchase->credits_applied;
        $card = max(0, (int) $purchase->price - $credits);
        $canRefund = $report->status === LeadIssueStatus::OPEN
            && $purchase->isPaid()
            && ! $report->refund()->exists();

        return [
            'total' => (int) $purchase->price,
            'credits' => $credits,
            'card' => $card,
            'can_refund' => $canRefund,
            'can_refund_dollars' => $canRefund && $card > 0 && $purchase->square_payment_id !== null,
        ];
    }

    /**
     * Every return request the member made, newest first, with the admin decision and refund (basic lead info only).
     *
     * @return list<array<string, mixed>>
     */
    public function requestsFor(User $user): array
    {
        return LeadIssueReport::query()
            ->where('user_id', $user->id)
            ->with(['refund', 'leadPurchase'])
            ->latest()
            ->latest('id')
            ->get()
            ->map(fn (LeadIssueReport $report): array => $this->mapRequest($report))
            ->all();
    }

    /**
     * One return request as the member sees it (basic lead info only).
     *
     * @return array<string, mixed>
     */
    public function mapRequest(LeadIssueReport $report): array
    {
        $report->loadMissing(['refund', 'leadPurchase']);

        $purchase = $report->leadPurchase;
        $credits = (int) $purchase->credits_applied;
        $refund = $report->refund;

        return [
            'id' => $report->id,
            'ticket' => 'T-'.$report->id,
            'lead_reference' => $report->lead_reference,
            'lead_type' => $report->lead_type,
            'lead_city' => $report->lead_city,
            'price' => $report->price,
            'credits_paid' => $credits,
            'card_paid' => max(0, (int) $purchase->price - $credits),
            'purchased_at' => $report->purchased_at?->toIso8601String(),
            'category_label' => $report->category->label(),
            'reason' => $report->reason,
            'status' => $report->status->value,
            'status_label' => $report->status->label(),
            'admin_note' => $report->admin_note,
            'resolved_at' => $report->resolved_at?->toIso8601String(),
            'created_at' => $report->created_at->toIso8601String(),
            'refund' => $refund === null ? null : [
                'type_label' => $refund->type->label(),
                'total_amount' => $refund->total_amount,
                'credit_amount' => $refund->credit_amount,
                'card_amount' => $refund->card_amount,
                'status' => $refund->status->value,
                'status_label' => $refund->status->label(),
                'created_at' => $refund->created_at->toIso8601String(),
            ],
        ];
    }

    /**
     * @return LengthAwarePaginator<int, LeadRefund>
     */
    public function paginate(?LeadRefundType $type = null, ?LeadRefundStatus $status = null, int $perPage = 15): LengthAwarePaginator
    {
        return LeadRefund::query()
            ->with(['user:id,name,email', 'report:id,lead_reference,lead_name,lead_city'])
            ->when($type !== null, fn ($query) => $query->where('type', $type?->value))
            ->when($status !== null, fn ($query) => $query->where('status', $status?->value))
            ->latest()
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @throws ValidationException
     */
    public function refund(
        LeadIssueReport $report,
        LeadRefundType $type,
        LeadRefundAction $leadAction,
        string $note,
        User $admin,
    ): LeadRefund {
        $refund = DB::transaction(function () use ($report, $type, $leadAction, $note, $admin): LeadRefund {
            $locked = LeadIssueReport::query()->lockForUpdate()->findOrFail($report->id);
            $purchase = LeadPurchase::query()->lockForUpdate()->findOrFail($locked->lead_purchase_id);

            if ($locked->status !== LeadIssueStatus::OPEN || $locked->refund()->exists()) {
                throw ValidationException::withMessages(['type' => 'This request has already been decided.']);
            }

            if (! $purchase->isPaid()) {
                throw ValidationException::withMessages(['type' => 'Only a paid lead can be refunded.']);
            }

            $credits = (int) $purchase->credits_applied;
            $card = max(0, (int) $purchase->price - $credits);

            if ($type === LeadRefundType::DOLLAR && ($card === 0 || $purchase->square_payment_id === null)) {
                throw ValidationException::withMessages(['type' => 'Nothing was paid by card, so this can only be refunded as Credits.']);
            }

            $creditAmount = $type === LeadRefundType::CREDIT ? (int) $purchase->price : $credits;
            $cardAmount = $type === LeadRefundType::CREDIT ? 0 : $card;

            $refund = LeadRefund::query()->create([
                'lead_issue_report_id' => $locked->id,
                'lead_purchase_id' => $purchase->id,
                'user_id' => $purchase->user_id,
                'refunded_by' => $admin->id,
                'type' => $type,
                'total_amount' => (int) $purchase->price,
                'credit_amount' => $creditAmount,
                'card_amount' => $cardAmount,
                'status' => $cardAmount > 0 ? LeadRefundStatus::PENDING : LeadRefundStatus::COMPLETED,
                'idempotency_key' => (string) Str::uuid(),
                'lead_action' => $leadAction,
                'admin_note' => trim($note),
            ]);

            if ($creditAmount > 0) {
                $this->credits->grant(
                    $purchase->user,
                    $creditAmount,
                    CreditTransactionType::REFUND_CREDIT,
                    $purchase,
                    $locked,
                    $admin,
                    'Refund for lead '.$locked->lead_reference,
                );
            }

            $purchase->forceFill([
                'status' => LeadPurchase::STATUS_REFUNDED,
                'refunded_at' => now(),
                'refund_idempotency_key' => $refund->idempotency_key,
                // Resell frees the lead for the next buyer; keeping the lock keeps it off sale.
                'lead_lock' => $leadAction === LeadRefundAction::RESELL ? null : $purchase->lead_lock,
            ])->save();

            $locked->update([
                'status' => LeadIssueStatus::APPROVED,
                'admin_note' => trim($note),
                'resolved_by' => $admin->id,
                'resolved_at' => now(),
            ]);

            return $refund;
        });

        if ($refund->card_amount > 0) {
            $this->sendCardRefund($refund);
        }

        $this->notifyMember($refund->refresh());

        return $refund;
    }

    /**
     * Apply a Square refund object (webhook or the refund call) to our record. Idempotent.
     *
     * @param  array<string, mixed>  $squareRefund
     */
    public function applySquareRefund(LeadRefund $refund, array $squareRefund): LeadRefund
    {
        $status = strtoupper((string) ($squareRefund['status'] ?? ''));

        $attributes = [
            'square_refund_id' => $squareRefund['id'] ?? $refund->square_refund_id,
            'square_status' => $status !== '' ? $status : $refund->square_status,
        ];

        if ($refund->status === LeadRefundStatus::PENDING) {
            if ($status === 'COMPLETED') {
                $attributes['status'] = LeadRefundStatus::COMPLETED;
                $attributes['failure_message'] = null;
            } elseif (in_array($status, ['FAILED', 'REJECTED', 'CANCELED'], true)) {
                $attributes['status'] = LeadRefundStatus::FAILED;
                $attributes['failure_message'] = 'Square '.strtolower($status).' the card refund.';
            }
        }

        $refund->forceFill($attributes)->save();

        return $refund;
    }

    /**
     * Asks Square to send the card part back. A failure keeps the refund (Credits and the purchase state are already
     * saved) marked `failed` so the admin can see it needs attention; it is retried with the same idempotency key.
     */
    public function sendCardRefund(LeadRefund $refund): LeadRefund
    {
        $purchase = $refund->leadPurchase;

        try {
            $squareRefund = $this->square->refundPayment(
                (string) $purchase->square_payment_id,
                $refund->card_amount * 100,
                $purchase->currency,
                $refund->idempotency_key,
                'Returned lead '.$refund->report->lead_reference,
            );
        } catch (SquarePaymentException $exception) {
            if ($exception->isOutcomeUnknown()) {
                // Square may have accepted it; the webhook or a retry with the same key settles it.
                return $refund;
            }

            $refund->forceFill([
                'status' => LeadRefundStatus::FAILED,
                'failure_message' => mb_substr($exception->detail ?? $exception->getMessage(), 0, 500),
            ])->save();

            return $refund;
        }

        return $this->applySquareRefund($refund, $squareRefund);
    }

    /**
     * Try a failed or unconfirmed card refund again (same idempotency key, so Square never refunds twice).
     */
    public function retryCardRefund(LeadRefund $refund): LeadRefund
    {
        abort_unless($refund->card_amount > 0 && $refund->status !== LeadRefundStatus::COMPLETED, 422);

        $refund->forceFill(['status' => LeadRefundStatus::PENDING, 'failure_message' => null])->save();

        return $this->sendCardRefund($refund);
    }

    /**
     * A mail failure must never undo a refund.
     */
    private function notifyMember(LeadRefund $refund): void
    {
        $email = $refund->user->email;

        if ($email === '') {
            return;
        }

        try {
            Mail::to($email)->queue(new LeadRefundedMail($refund));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
