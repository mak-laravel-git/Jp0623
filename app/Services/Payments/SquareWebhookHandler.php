<?php

namespace App\Services\Payments;

use App\Models\LeadPurchase;
use App\Models\LeadRefund;
use App\Models\PaymentEvent;
use App\Services\LeadPurchaseService;
use App\Services\LeadRefundService;

/**
 * Applies verified Square webhook events to lead purchases.
 *
 * `payment.*` events settle purchases and `refund.*` events settle card refunds; other event types
 * are stored and ignored. All state changes go through
 * {@see LeadPurchaseService::applySquarePayment()}, which is idempotent, so the
 * webhook can race with the checkout request or the reconcile command safely.
 */
class SquareWebhookHandler
{
    public function __construct(
        private LeadPurchaseService $purchases,
        private LeadRefundService $refunds,
    ) {}

    /**
     * @return string One of the `PaymentEvent::OUTCOME_*` constants.
     */
    public function handle(PaymentEvent $event): string
    {
        return match ($event->type) {
            'payment.created', 'payment.updated' => $this->handlePayment($event),
            'refund.created', 'refund.updated' => $this->handleRefund($event),
            default => PaymentEvent::OUTCOME_IGNORED,
        };
    }

    private function handleRefund(PaymentEvent $event): string
    {
        $squareRefund = $event->payload['data']['object']['refund'] ?? null;

        if (! is_array($squareRefund) || ! is_string($squareRefund['id'] ?? null)) {
            return PaymentEvent::OUTCOME_IGNORED;
        }

        $refund = LeadRefund::query()->where('square_refund_id', $squareRefund['id'])->first();

        if ($refund === null && is_string($squareRefund['payment_id'] ?? null)) {
            $refund = LeadRefund::query()
                ->whereNull('square_refund_id')
                ->whereHas('leadPurchase', fn ($query) => $query->where('square_payment_id', $squareRefund['payment_id']))
                ->first();
        }

        if ($refund === null) {
            return PaymentEvent::OUTCOME_UNMATCHED;
        }

        $before = $refund->status;

        return $this->refunds->applySquareRefund($refund, $squareRefund)->status !== $before
            ? PaymentEvent::OUTCOME_APPLIED
            : PaymentEvent::OUTCOME_IGNORED;
    }

    private function handlePayment(PaymentEvent $event): string
    {
        $payment = $event->payload['data']['object']['payment'] ?? null;

        if (! is_array($payment)) {
            return PaymentEvent::OUTCOME_IGNORED;
        }

        $purchase = $this->findPurchase($payment, $event->payload['data']['id'] ?? null);

        if ($purchase === null) {
            return PaymentEvent::OUTCOME_UNMATCHED;
        }

        $status = (string) ($payment['status'] ?? '');

        if ($status === 'COMPLETED'
            && $purchase->isPending()
            && ! $this->purchases->paymentMatchesPurchase($purchase, $payment)) {
            // Records the payload and logs it critically; the purchase stays pending.
            $this->purchases->applySquarePayment($purchase, $payment);

            return PaymentEvent::OUTCOME_MISMATCH;
        }

        $wasPending = $purchase->isPending();
        $updated = $this->purchases->applySquarePayment($purchase, $payment);

        return $wasPending && ! $updated->isPending()
            ? PaymentEvent::OUTCOME_APPLIED
            : PaymentEvent::OUTCOME_IGNORED;
    }

    /**
     * Match by Square payment id first, then by our `LP-{id}` reference.
     *
     * @param  array<string, mixed>  $payment
     */
    private function findPurchase(array $payment, mixed $eventObjectId): ?LeadPurchase
    {
        $paymentId = $payment['id'] ?? (is_string($eventObjectId) ? $eventObjectId : null);

        if (is_string($paymentId) && $paymentId !== '') {
            $byPayment = LeadPurchase::query()->where('square_payment_id', $paymentId)->first();

            if ($byPayment !== null) {
                return $byPayment;
            }
        }

        $reference = (string) ($payment['reference_id'] ?? '');

        if (preg_match('/^LP-(\d+)$/', $reference, $matches) !== 1) {
            return null;
        }

        return LeadPurchase::query()
            ->whereKey((int) $matches[1])
            ->where('payment_provider', LeadPurchase::PROVIDER_SQUARE)
            ->first();
    }
}
