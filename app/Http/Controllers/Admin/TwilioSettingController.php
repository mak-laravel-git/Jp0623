<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TwilioSetting\UpdateTwilioSettingsRequest;
use App\Services\TwilioSettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TwilioSettingController extends Controller
{
    public function __construct(private TwilioSettingsService $settings) {}

    public function edit(): Response
    {
        return Inertia::render('admin/twilio-settings/edit', [
            'settings' => $this->settings->summary(),
        ]);
    }

    public function update(UpdateTwilioSettingsRequest $request): RedirectResponse
    {
        $this->settings->update($request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Twilio settings saved.']);

        return redirect()->back();
    }

    /**
     * Check the saved credentials against Twilio (read-only calls, no SMS).
     */
    public function test(): RedirectResponse
    {
        $result = $this->settings->testConnection();

        Inertia::flash('toast', ['type' => $result['ok'] ? 'success' : 'error', 'message' => $result['message']]);

        return redirect()->back();
    }
}
