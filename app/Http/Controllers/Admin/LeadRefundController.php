<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadRefundStatus;
use App\Enums\LeadRefundType;
use App\Http\Controllers\Controller;
use App\Models\LeadRefund;
use App\Services\LeadRefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadRefundController extends Controller
{
    public function __construct(private LeadRefundService $refunds) {}

    public function index(Request $request): Response
    {
        $type = LeadRefundType::tryFrom((string) $request->query('type', ''));
        $status = LeadRefundStatus::tryFrom((string) $request->query('status', ''));

        $refunds = $this->refunds->paginate($type, $status)
            ->through(fn (LeadRefund $refund): array => [
                'id' => $refund->id,
                'report_id' => $refund->lead_issue_report_id,
                'lead_reference' => $refund->report->lead_reference,
                'lead_name' => $refund->report->lead_name,
                'lead_city' => $refund->report->lead_city,
                'operator_name' => $refund->user->name,
                'operator_email' => $refund->user->email,
                'type' => $refund->type->value,
                'type_label' => $refund->type->label(),
                'total_amount' => $refund->total_amount,
                'credit_amount' => $refund->credit_amount,
                'card_amount' => $refund->card_amount,
                'status' => $refund->status->value,
                'status_label' => $refund->status->label(),
                'failure_message' => $refund->failure_message,
                'lead_action' => $refund->lead_action->value,
                'lead_action_label' => $refund->lead_action->label(),
                'created_at' => $refund->created_at->toIso8601String(),
            ]);

        return Inertia::render('admin/refunds/index', [
            'refunds' => $refunds,
            'filters' => ['type' => $type?->value, 'status' => $status?->value],
            'types' => array_map(
                fn (LeadRefundType $case): array => ['value' => $case->value, 'label' => $case->label()],
                LeadRefundType::cases(),
            ),
            'statuses' => array_map(
                fn (LeadRefundStatus $case): array => ['value' => $case->value, 'label' => $case->label()],
                LeadRefundStatus::cases(),
            ),
        ]);
    }

    /**
     * Try a failed card refund again (same Square idempotency key, so it can never refund twice).
     */
    public function retry(LeadRefund $refund): RedirectResponse
    {
        $refund = $this->refunds->retryCardRefund($refund);

        Inertia::flash('toast', $refund->status === LeadRefundStatus::FAILED
            ? ['type' => 'error', 'message' => 'Square could not refund the card: '.$refund->failure_message]
            : ['type' => 'success', 'message' => 'Card refund sent to Square.']);

        return redirect()->back();
    }
}
