<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadIssueCategory;
use App\Enums\LeadIssueStatus;
use App\Enums\LeadRefundAction;
use App\Enums\LeadRefundType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LeadIssue\RefundLeadIssueRequest;
use App\Http\Requests\Admin\LeadIssue\UpdateLeadIssueRequest;
use App\Http\Requests\Admin\LeadIssue\UpdateLeadIssueSettingsRequest;
use App\Models\LeadIssueReport;
use App\Services\LeadIssueReportService;
use App\Services\LeadIssueSettingsService;
use App\Services\LeadRefundService;
use App\Services\ReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadIssueController extends Controller
{
    public function __construct(
        private LeadIssueReportService $reports,
        private LeadIssueSettingsService $settings,
        private LeadRefundService $refunds,
        private ReceiptService $receipts,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = LeadIssueStatus::tryFrom((string) $request->query('status', ''));
        $category = LeadIssueCategory::tryFrom((string) $request->query('category', ''));

        $reports = $this->reports->paginate($search, $status, $category)
            ->through(fn (LeadIssueReport $report): array => [
                'id' => $report->id,
                'ticket' => 'T-'.$report->id,
                'lead_reference' => $report->lead_reference,
                'lead_name' => $report->lead_name,
                'lead_city' => $report->lead_city,
                'operator_name' => $report->user->name,
                'operator_email' => $report->user->email,
                'category' => $report->category->value,
                'category_label' => $report->category->label(),
                'reason' => $report->reason,
                'status' => $report->status->value,
                'status_label' => $report->status->label(),
                'price' => $report->price,
                'created_at' => $report->created_at->toIso8601String(),
            ]);

        return Inertia::render('admin/lead-issues/index', [
            'reports' => $reports,
            'filters' => [
                'search' => $search,
                'status' => $status?->value,
                'category' => $category?->value,
            ],
            'settings' => [
                'report_window_days' => $this->settings->reportWindowDays(),
            ],
            'statuses' => array_map(
                fn (LeadIssueStatus $case): array => ['value' => $case->value, 'label' => $case->label()],
                LeadIssueStatus::cases(),
            ),
            'categories' => array_map(
                fn (LeadIssueCategory $case): array => ['value' => $case->value, 'label' => $case->label()],
                LeadIssueCategory::cases(),
            ),
        ]);
    }

    public function show(LeadIssueReport $leadIssue): Response
    {
        $leadIssue->load(['user.operatorProfile', 'resolver:id,name', 'leadPurchase', 'refund.refundedBy:id,name']);

        $operator = $leadIssue->user;
        $purchase = $leadIssue->leadPurchase;

        return Inertia::render('admin/lead-issues/show', [
            'report' => [
                'id' => $leadIssue->id,
                'ticket' => 'T-'.$leadIssue->id,
                'status' => $leadIssue->status->value,
                'status_label' => $leadIssue->status->label(),
                'category' => $leadIssue->category->value,
                'category_label' => $leadIssue->category->label(),
                'reason' => $leadIssue->reason,
                'admin_note' => $leadIssue->admin_note,
                'resolved_by' => $leadIssue->resolver?->name,
                'resolved_at' => $leadIssue->resolved_at?->toIso8601String(),
                'created_at' => $leadIssue->created_at->toIso8601String(),
                'lead_reference' => $leadIssue->lead_reference,
                'lead_name' => $leadIssue->lead_name,
                'lead_type' => $leadIssue->lead_type,
                'lead_city' => $leadIssue->lead_city,
                'price' => $leadIssue->price,
                'purchased_at' => $leadIssue->purchased_at?->toIso8601String(),
                'receipt_url' => route('receipts.lead', $purchase),
                'payment_status' => $purchase->status,
                'payment' => $this->refunds->preview($leadIssue),
                'payment_details' => $this->receipts->purchaseDetails($purchase),
                'refund' => $leadIssue->refund === null ? null : [
                    'id' => $leadIssue->refund->id,
                    'type' => $leadIssue->refund->type->value,
                    'type_label' => $leadIssue->refund->type->label(),
                    'total_amount' => $leadIssue->refund->total_amount,
                    'credit_amount' => $leadIssue->refund->credit_amount,
                    'card_amount' => $leadIssue->refund->card_amount,
                    'status' => $leadIssue->refund->status->value,
                    'status_label' => $leadIssue->refund->status->label(),
                    'failure_message' => $leadIssue->refund->failure_message,
                    'square_refund_id' => $leadIssue->refund->square_refund_id,
                    'lead_action_label' => $leadIssue->refund->lead_action->label(),
                    'refunded_by' => $leadIssue->refund->refundedBy?->name,
                    'details' => $this->receipts->refundDetails($leadIssue->refund),
                    'receipt_url' => route('receipts.refund', $leadIssue->refund),
                    'created_at' => $leadIssue->refund->created_at->toIso8601String(),
                ],
                'operator' => [
                    'name' => $operator->name,
                    'email' => $operator->email,
                    'phone' => $operator->operatorProfile?->phone,
                    'company' => $operator->operatorProfile?->company_name,
                ],
            ],
        ]);
    }

    /**
     * Set how many days after buying a lead the operator can send it back (empty = no limit).
     */
    public function updateSettings(UpdateLeadIssueSettingsRequest $request): RedirectResponse
    {
        $days = $request->validated('report_window_days');

        $this->settings->updateReportWindowDays($days === null ? null : (int) $days);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $days === null
                ? 'Operators can send a lead back at any time.'
                : "Operators can send a lead back within {$days} days of buying it.",
        ]);

        return redirect()->back();
    }

    /**
     * Refund the returned lead in full: to the card (dollars) or as Credits.
     */
    public function refund(RefundLeadIssueRequest $request, LeadIssueReport $leadIssue): RedirectResponse
    {
        $this->refunds->refund(
            $leadIssue,
            LeadRefundType::from($request->validated('type')),
            LeadRefundAction::from($request->validated('lead_action')),
            $request->validated('admin_note'),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Refund issued.']);

        return redirect()->back();
    }

    public function update(UpdateLeadIssueRequest $request, LeadIssueReport $leadIssue): RedirectResponse
    {
        $status = LeadIssueStatus::from($request->validated('status'));

        $this->reports->resolve($leadIssue, $status, $request->validated('admin_note'), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Return rejected.',
        ]);

        return redirect()->back();
    }
}
