<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MembershipSetting\StoreMembershipCustomPriceRequest;
use App\Http\Requests\Admin\MembershipSetting\UpdateMembershipCustomPriceRequest;
use App\Models\MembershipCustomPrice;
use App\Services\MembershipPricingService;
use App\Services\MembershipSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MembershipCustomPriceController extends Controller
{
    public function __construct(
        private MembershipPricingService $pricing,
        private MembershipSettingsService $settings,
    ) {}

    /**
     * Tells the admin whether the email belongs to a member and which membership they are already on.
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        return response()->json($this->pricing->lookup($validated['email']));
    }

    public function store(StoreMembershipCustomPriceRequest $request): RedirectResponse
    {
        $row = $this->pricing->set(
            $request->validated('email'),
            (int) $request->validated('monthly_price'),
            $request->user(),
        );

        $emailed = $request->boolean('send_email') && $this->pricing->notify($row);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Custom price \${$row->monthly_price}/mo saved for {$row->email}."
                .($emailed ? ' They were emailed.' : ''),
        ]);

        return redirect()->back();
    }

    public function update(UpdateMembershipCustomPriceRequest $request, MembershipCustomPrice $customPrice): RedirectResponse
    {
        $row = $this->pricing->set(
            $customPrice->email,
            (int) $request->validated('monthly_price'),
            $request->user(),
        );

        $emailed = $request->boolean('send_email') && $this->pricing->notify($row);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Custom price updated to \${$row->monthly_price}/mo for {$row->email}."
                .($emailed ? ' They were emailed.' : ''),
        ]);

        return redirect()->back();
    }

    public function destroy(Request $request, MembershipCustomPrice $customPrice): RedirectResponse
    {
        $this->pricing->remove($customPrice, $request->user());

        $message = $customPrice->user_id !== null
            ? "Custom price removed for {$customPrice->email}. They now pay the default price (\${$this->settings->monthlyPrice()}/mo)."
            : "Custom price removed for {$customPrice->email}.";

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);

        return redirect()->back();
    }
}
