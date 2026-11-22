<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\StoreLeadIssueRequest;
use App\Models\LeadPurchase;
use App\Services\LeadIssueReportService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LeadIssueController extends Controller
{
    public function __construct(private LeadIssueReportService $reports) {}

    /**
     * Send a purchased lead back to the admin with a written reason.
     */
    public function store(StoreLeadIssueRequest $request, LeadPurchase $purchase): RedirectResponse
    {
        $this->reports->create($request->user(), $purchase, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Your request was sent to the admin. We will review it and update the ticket.',
        ]);

        return redirect()->route('leads.tickets');
    }
}
