<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MembershipPriceHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MembershipPriceHistoryController extends Controller
{
    /**
     * Read-only log of every custom membership price change.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $action = $request->query('action');
        $action = is_string($action) && in_array($action, [
            MembershipPriceHistory::ACTION_CREATED,
            MembershipPriceHistory::ACTION_UPDATED,
            MembershipPriceHistory::ACTION_REMOVED,
        ], true) ? $action : null;

        $history = MembershipPriceHistory::query()
            ->with(['user:id,name', 'changedBy:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.addcslashes(mb_strtolower($search), '\\%_').'%';

                $query->where('email', 'like', $term);
            })
            ->when($action !== null, fn ($query) => $query->where('action', $action))
            ->latest()
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (MembershipPriceHistory $entry): array => [
                'id' => $entry->id,
                'email' => $entry->email,
                'member_name' => $entry->user?->name,
                'registered' => $entry->user_id !== null,
                'action' => $entry->action,
                'old_price' => $entry->old_price,
                'new_price' => $entry->new_price,
                'changed_by' => $entry->changedBy?->name,
                'email_sent' => $entry->email_sent,
                'created_at' => $entry->created_at->toIso8601String(),
            ]);

        return Inertia::render('admin/membership-price-history/index', [
            'history' => $history,
            'filters' => [
                'search' => $search,
                'action' => $action,
            ],
        ]);
    }
}
