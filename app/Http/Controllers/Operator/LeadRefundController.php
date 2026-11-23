<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\LeadIssueReport;
use App\Services\CreditService;
use App\Services\LeadRefundService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadRefundController extends Controller
{
    public function __construct(
        private CreditService $credits,
        private LeadRefundService $refunds,
    ) {}

    /**
     * One return request: the reason, the admin decision and the refund. Other members' requests are a 404.
     */
    public function show(Request $request, LeadIssueReport $report): Response
    {
        abort_unless($report->user_id === $request->user()->id, 404);

        return Inertia::render('operator/leads/refund-details', [
            'returnRequest' => $this->refunds->mapRequest($report),
        ]);
    }

    /**
     * The member's Credits, every return request with the admin decision and refund, and the Credits history.
     * Only basic lead info is shown, never contact details.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('operator/leads/refunds', [
            'requests' => $this->refunds->requestsFor($user),
            'creditHistory' => $this->credits->historyFor($user)
                ->map(fn (CreditTransaction $row): array => [
                    'id' => $row->id,
                    'label' => $row->type->label(),
                    'note' => $row->note,
                    'amount' => $row->amount,
                    'balance_after' => $row->balance_after,
                    'created_at' => $row->created_at->toIso8601String(),
                ])
                ->all(),
        ]);
    }
}
