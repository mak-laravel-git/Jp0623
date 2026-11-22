<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadIssueCategory;
use App\Enums\LeadIssueStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LeadIssue\UpdateLeadIssueRequest;
use App\Http\Requests\Admin\LeadIssue\UpdateLeadIssueSettingsRequest;
use App\Models\LeadIssueReport;
use App\Services\LeadIssueReportService;
use App\Services\LeadIssueSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadIssueController extends Controller
{
    public function __construct(
        private LeadIssueReportService $reports,
        private LeadIssueSettingsService $settings,
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
        $leadIssue->load(['user.operatorProfile', 'resolver:id,name', 'leadPurchase']);

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
                'receipt_url' => $purchase->square_receipt_url,
                'payment_status' => $purchase->status,
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

    public function update(UpdateLeadIssueRequest $request, LeadIssueReport $leadIssue): RedirectResponse
    {
        $status = LeadIssueStatus::from($request->validated('status'));

        $this->reports->resolve($leadIssue, $status, $request->validated('admin_note'), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $status === LeadIssueStatus::APPROVED ? 'Return approved.' : 'Return rejected.',
        ]);

        return redirect()->back();
    }
}
