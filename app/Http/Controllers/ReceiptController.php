<?php

namespace App\Http\Controllers;

use App\Enums\PermissionEnum;
use App\Models\LeadPurchase;
use App\Models\LeadRefund;
use App\Models\MembershipPayment;
use App\Services\ReceiptService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Our own receipt pages. Members open their own; admins who can see payments open any. Everyone else gets a 404.
 */
class ReceiptController extends Controller
{
    public function __construct(private ReceiptService $receipts) {}

    public function lead(Request $request, LeadPurchase $purchase): Response
    {
        $this->authorizeReceipt($request, $purchase->user_id);

        abort_unless(in_array($purchase->status, [LeadPurchase::STATUS_PAID, LeadPurchase::STATUS_REFUNDED], true), 404);

        return $this->render($this->receipts->forLeadPurchase($purchase), $request);
    }

    public function membership(Request $request, MembershipPayment $payment): Response
    {
        $this->authorizeReceipt($request, $payment->user_id);

        abort_unless($payment->status === MembershipPayment::STATUS_PAID, 404);

        return $this->render($this->receipts->forMembershipPayment($payment), $request);
    }

    public function refund(Request $request, LeadRefund $refund): Response
    {
        $this->authorizeReceipt($request, $refund->user_id);

        return $this->render($this->receipts->forRefund($refund), $request);
    }

    /**
     * @param  array<string, mixed>  $receipt
     */
    private function render(array $receipt, Request $request): Response
    {
        return Inertia::render('receipts/show', [
            'receipt' => $receipt,
            'backUrl' => $this->isAdmin($request) ? route('admin.payments.index') : route('billing.index'),
        ]);
    }

    private function authorizeReceipt(Request $request, int $ownerId): void
    {
        abort_unless($request->user()->id === $ownerId || $this->isAdmin($request), 404);
    }

    private function isAdmin(Request $request): bool
    {
        return $request->user()->can(PermissionEnum::PAYMENTS_INDEX->value);
    }
}
