<?php

namespace App\Services;

use App\Models\CoffeeServiceRequest;
use App\Models\LeadPurchase;
use App\Models\LeadRefund;
use App\Models\MembershipPayment;
use App\Models\User;
use App\Models\VendingRequest;
use Illuminate\Database\Eloquent\Model;

/**
 * Builds the data for our own receipt page, so receipts work in Square's sandbox and in production
 * (Square's own receipt links do not open for sandbox payments).
 *
 * Every kind returns the same shape; amounts are whole or decimal dollars. Lead contact details are never included.
 */
class ReceiptService
{
    /**
     * @return array<string, mixed>
     */
    public function forLeadPurchase(LeadPurchase $purchase): array
    {
        $purchase->loadMissing(['user.operatorProfile', 'leadable']);

        $credits = (int) $purchase->credits_applied;
        $cardPaid = $credits > 0
            ? $purchase->amount_cents / 100
            : ($purchase->amount_cents ?: ((int) $purchase->price * 100)) / 100;
        $refund = LeadRefund::query()->where('lead_purchase_id', $purchase->id)->first();

        return [
            'number' => 'LP-'.$purchase->id,
            'kind' => 'lead',
            'title' => 'Lead purchase receipt',
            'date' => ($purchase->paid_at ?? $purchase->purchased_at ?? $purchase->created_at)->format('M j, Y g:i A'),
            'status' => $purchase->status === LeadPurchase::STATUS_REFUNDED ? 'Refunded' : 'Paid',
            'customer' => $this->customer($purchase->user),
            'items' => [[
                'description' => $this->leadDescription($purchase),
                'amount' => (float) $purchase->price,
            ]],
            'total' => (float) $purchase->price,
            'credits_applied' => $credits,
            'card_paid' => round((float) $cardPaid, 2),
            'payment_method' => $this->paymentMethod($purchase->card_brand, $purchase->card_last4, $credits > 0 && $cardPaid == 0),
            'details' => $this->purchaseDetails($purchase),
            'related' => $refund === null ? null : [
                'label' => 'Refund receipt RF-'.$refund->id,
                'url' => route('receipts.refund', $refund),
            ],
            'seller' => $this->seller(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forMembershipPayment(MembershipPayment $payment): array
    {
        $payment->loadMissing('user.operatorProfile');

        $amount = ($payment->amount_cents ?: ((int) $payment->price * 100)) / 100;

        return [
            'number' => 'MB-'.$payment->id,
            'kind' => 'membership',
            'title' => 'Membership payment receipt',
            'date' => ($payment->paid_at ?? $payment->created_at)->format('M j, Y g:i A'),
            'status' => 'Paid',
            'customer' => $this->customer($payment->user),
            'items' => [['description' => 'Monthly membership', 'amount' => (float) $amount]],
            'total' => (float) $amount,
            'credits_applied' => 0,
            'card_paid' => round((float) $amount, 2),
            'payment_method' => $this->paymentMethod($payment->card_brand, $payment->card_last4, false),
            'details' => $this->membershipDetails($payment),
            'related' => null,
            'seller' => $this->seller(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forRefund(LeadRefund $refund): array
    {
        $refund->loadMissing(['user.operatorProfile', 'report', 'leadPurchase']);

        return [
            'number' => 'RF-'.$refund->id,
            'kind' => 'refund',
            'title' => 'Refund receipt',
            'date' => $refund->created_at->format('M j, Y g:i A'),
            'status' => $refund->status->label(),
            'customer' => $this->customer($refund->user),
            'items' => [[
                'description' => 'Refund for lead '.$refund->report->lead_reference
                    .($refund->report->lead_city ? ' · '.$refund->report->lead_city : ''),
                'amount' => (float) $refund->total_amount,
            ]],
            'total' => (float) $refund->total_amount,
            'credits_applied' => $refund->credit_amount,
            'card_paid' => (float) $refund->card_amount,
            'payment_method' => $refund->card_amount > 0
                ? $this->paymentMethod($refund->leadPurchase->card_brand, $refund->leadPurchase->card_last4, false)
                : 'Credits',
            'details' => $this->refundDetails($refund),
            'related' => [
                'label' => 'Purchase receipt LP-'.$refund->lead_purchase_id,
                'url' => route('receipts.lead', $refund->lead_purchase_id),
            ],
            'seller' => $this->seller(),
        ];
    }

    /**
     * Every payment detail we hold for a lead purchase (transaction id, order, card, amounts), as label/value rows.
     *
     * @return list<array{label: string, value: string}>
     */
    public function purchaseDetails(LeadPurchase $purchase): array
    {
        $credits = (int) $purchase->credits_applied;
        $cardCents = (int) $purchase->amount_cents;
        $method = match (true) {
            $credits > 0 && $cardCents === 0 => 'Credits',
            $credits > 0 => 'Credits + card',
            default => 'Card',
        };

        return $this->rows([
            'Transaction ID' => $purchase->square_payment_id,
            'Order ID' => $purchase->square_order_id,
            'Square receipt no.' => $purchase->square_receipt_number,
            'Paid with' => $method,
            'Card' => $this->cardLabel($purchase->card_brand, $purchase->card_last4),
            'Credits used' => $credits > 0 ? $credits.' Credits' : null,
            'Charged to card' => $cardCents > 0 ? '$'.number_format($cardCents / 100, 2).' '.$purchase->currency : null,
            'Paid on' => ($purchase->paid_at ?? $purchase->purchased_at)?->format('M j, Y g:i A'),
            'Payment status' => $purchase->square_status,
        ]);
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function membershipDetails(MembershipPayment $payment): array
    {
        return $this->rows([
            'Transaction ID' => $payment->square_payment_id,
            'Order ID' => $payment->square_order_id,
            'Square receipt no.' => $payment->square_receipt_number,
            'Paid with' => 'Card',
            'Card' => $this->cardLabel($payment->card_brand, $payment->card_last4),
            'Charged to card' => '$'.number_format(($payment->amount_cents ?: ((int) $payment->price * 100)) / 100, 2).' '.$payment->currency,
            'Paid on' => $payment->paid_at?->format('M j, Y g:i A'),
            'Payment status' => $payment->square_status,
        ]);
    }

    /**
     * The refund and the original payment it came from.
     *
     * @return list<array{label: string, value: string}>
     */
    public function refundDetails(LeadRefund $refund): array
    {
        $purchase = $refund->leadPurchase;

        return $this->rows([
            'Refund ID' => $refund->square_refund_id,
            'Refund type' => $refund->type->label(),
            'Refund status' => $refund->status->label(),
            'Square status' => $refund->square_status,
            'Refunded to' => $refund->card_amount > 0
                ? ($this->cardLabel($purchase->card_brand, $purchase->card_last4) ?? 'Original card')
                : null,
            'Back to card' => $refund->card_amount > 0 ? '$'.number_format($refund->card_amount, 2).' '.$purchase->currency : null,
            'Back as Credits' => $refund->credit_amount > 0 ? $refund->credit_amount.' Credits' : null,
            'Original transaction ID' => $purchase->square_payment_id,
            'Original receipt' => 'LP-'.$purchase->id,
            'Refunded on' => $refund->created_at->format('M j, Y g:i A'),
        ]);
    }

    /**
     * Keep only the rows that have a value.
     *
     * @param  array<string, string|null>  $values
     * @return list<array{label: string, value: string}>
     */
    private function rows(array $values): array
    {
        $rows = [];

        foreach ($values as $label => $value) {
            if ($value !== null && $value !== '') {
                $rows[] = ['label' => $label, 'value' => $value];
            }
        }

        return $rows;
    }

    private function cardLabel(?string $brand, ?string $last4): ?string
    {
        return $last4 !== null && $last4 !== '' ? trim(($brand ?: 'Card').' ending '.$last4) : null;
    }

    private function leadDescription(LeadPurchase $purchase): string
    {
        $lead = $purchase->leadable;
        $reference = LeadService::publicIdFor((string) $purchase->leadable_type, (int) $purchase->leadable_id);

        if (! $lead instanceof Model) {
            return 'Location lead '.$reference;
        }

        $type = $lead instanceof CoffeeServiceRequest
            ? 'Office coffee'
            : ($lead instanceof VendingRequest ? $lead->locationTypeLabel() : null);

        return trim(collect(['Location lead '.$reference, $type, $lead->city ?? null])->filter()->implode(' · '));
    }

    private function paymentMethod(?string $brand, ?string $last4, bool $creditsOnly): string
    {
        if ($creditsOnly) {
            return 'Credits';
        }

        return $last4 !== null && $last4 !== '' ? trim(($brand ?: 'Card').' ending '.$last4) : 'Card';
    }

    /**
     * @return array{name: string, email: string, company: string|null}
     */
    private function customer(?User $user): array
    {
        return [
            'name' => (string) $user?->name,
            'email' => (string) $user?->email,
            'company' => $user?->operatorProfile?->company_name,
        ];
    }

    /**
     * @return array{name: string, address: string|null, email: string|null, phone: string|null}
     */
    private function seller(): array
    {
        return [
            'name' => (string) config('receipts.business_name'),
            'address' => config('receipts.address'),
            'email' => config('receipts.email'),
            'phone' => config('receipts.phone'),
        ];
    }
}
