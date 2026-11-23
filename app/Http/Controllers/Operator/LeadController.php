<?php

namespace App\Http\Controllers\Operator;

use App\Demo\OperatorDashboardData;
use App\Exceptions\Payments\LeadUnavailableException;
use App\Exceptions\Payments\PaymentFailedException;
use App\Exceptions\Payments\SquareNotConfiguredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\PurchaseLeadRequest;
use App\Models\LeadIssueReport;
use App\Services\LeadPurchaseService;
use App\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function __construct(private LeadService $leads) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $user->loadMissing('operatorProfile');

        return Inertia::render('operator/leads/index', [
            'leads' => $this->leads->availableFor($user),
            'primaryCity' => $user->operatorProfile?->primary_city ?: 'Not set',
        ]);
    }

    /**
     * Leads other operators bought nearby, with public details only.
     */
    public function sold(Request $request): Response
    {
        $user = $request->user();
        $user->loadMissing('operatorProfile');
        $profile = $user->operatorProfile;

        return Inertia::render('operator/leads/sold', [
            'soldLeads' => $this->leads->soldNear($user),
            'primaryCity' => $profile?->primary_city ?: 'Not set',
            'hasLocation' => $profile?->latitude !== null && $profile?->longitude !== null,
            'radius' => LeadService::SOLD_RADIUS_MILES,
        ]);
    }

    public function purchased(Request $request): Response
    {
        $user = $request->user();
        $user->loadMissing('operatorProfile');

        return Inertia::render('operator/leads/purchased', [
            'purchasedLeads' => $this->leads->purchasedBy($user),
            'primaryCity' => $user->operatorProfile?->primary_city ?: 'Not set',
        ]);
    }

    /**
     * Buy a lead with the card token from Square's Web Payments SDK.
     */
    public function purchase(
        PurchaseLeadRequest $request,
        LeadPurchaseService $purchases,
        string $source,
        int $lead,
    ): RedirectResponse {
        try {
            $purchase = $purchases->checkout(
                $request->user(),
                $source,
                $lead,
                $request->validated('source_id'),
                $request->boolean('use_credits'),
            );
        } catch (LeadUnavailableException|PaymentFailedException $exception) {
            throw ValidationException::withMessages(['payment' => $exception->getMessage()]);
        } catch (SquareNotConfiguredException $exception) {
            throw ValidationException::withMessages(['payment' => $exception->userMessage()]);
        }

        if ($purchase->isPaid()) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Lead purchased. Contact details are unlocked.']);
        } else {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Payment is processing. Details unlock as soon as it is confirmed.']);
        }

        return redirect()->route('leads.purchased');
    }

    /**
     * The operator's requests to send purchased leads back, with the admin's decision.
     */
    public function tickets(Request $request): Response
    {
        $tickets = LeadIssueReport::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->latest('id')
            ->get()
            ->map(fn (LeadIssueReport $report): array => [
                'id' => 'T-'.$report->id,
                'lead' => trim($report->lead_reference.' · '.($report->lead_name ?? ''), ' ·'),
                'issue' => $report->category->label().': '.Str::limit($report->reason, 80),
                'opened' => $report->created_at->format('M j'),
                'status' => $report->status->label(),
                'outcome' => $report->admin_note,
            ])
            ->all();

        return Inertia::render('operator/leads/tickets', [
            'tickets' => $tickets,
        ]);
    }

    public function faq(): Response
    {
        return Inertia::render('operator/leads/faq', [
            'faqs' => OperatorDashboardData::faq(),
        ]);
    }
}
