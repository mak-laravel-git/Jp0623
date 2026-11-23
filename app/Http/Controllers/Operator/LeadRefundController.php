<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\LeadRefund;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadRefundController extends Controller
{
    public function __construct(private CreditService $credits) {}

    /**
     * The member's refund history (basic lead info only, never contact details) and their Credits.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $refunds = LeadRefund::query()
            ->where('user_id', $user->id)
            ->with('report')
            ->latest()
            ->latest('id')
            ->get()
            ->map(fn (LeadRefund $refund): array => [
                'id' => $refund->id,
                'lead_reference' => $refund->report->lead_reference,
                'lead_type' => $refund->report->lead_type,
                'lead_city' => $refund->report->lead_city,
                'total_amount' => $refund->total_amount,
                'credit_amount' => $refund->credit_amount,
                'card_amount' => $refund->card_amount,
                'type_label' => $refund->type->label(),
                'status' => $refund->status->value,
                'status_label' => $refund->status->label(),
                'note' => $refund->admin_note,
                'refunded_at' => $refund->created_at->toIso8601String(),
            ])
            ->all();

        return Inertia::render('operator/leads/refunds', [
            'refunds' => $refunds,
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
