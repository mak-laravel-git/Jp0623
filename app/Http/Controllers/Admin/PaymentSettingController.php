<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentSetting\UpdatePaymentSettingsRequest;
use App\Services\Payments\SquareSettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaymentSettingController extends Controller
{
    public function __construct(private SquareSettingsService $settings) {}

    public function edit(): Response
    {
        return Inertia::render('admin/payment-settings/edit', [
            'settings' => $this->settings->summary(),
            'environments' => SquareSettingsService::ENVIRONMENTS,
        ]);
    }

    public function update(UpdatePaymentSettingsRequest $request): RedirectResponse
    {
        $this->settings->update($request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment settings saved.']);

        return redirect()->back();
    }

    /**
     * Check the saved credentials against Square (read-only call).
     */
    public function test(): RedirectResponse
    {
        $result = $this->settings->testConnection();

        Inertia::flash('toast', ['type' => $result['ok'] ? 'success' : 'error', 'message' => $result['message']]);

        return redirect()->back();
    }
}
