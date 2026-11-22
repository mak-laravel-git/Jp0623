<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Services\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MembershipAutoRenewController extends Controller
{
    public function __construct(private MembershipService $memberships) {}

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'auto_renew' => ['required', 'boolean'],
        ]);

        $autoRenew = (bool) $validated['auto_renew'];

        if (! $this->memberships->setAutoRenew($request->user(), $autoRenew)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Auto-renew can only be changed while your membership is active.',
            ]);

            return redirect()->back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $autoRenew
                ? 'Auto-renew is on. Your card will be charged when your membership renews.'
                : 'Auto-renew is off. You will not be charged again; your membership stays active until the end of the paid period.',
        ]);

        return redirect()->back();
    }
}
