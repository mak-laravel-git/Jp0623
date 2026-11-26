<?php

namespace App\Http\Controllers\Operator;

use App\Demo\OperatorDashboardData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\SendAccountEmailCodeRequest;
use App\Http\Requests\Operator\SendAccountPhoneCodeRequest;
use App\Http\Requests\Operator\UpdateAccountEmailRequest;
use App\Http\Requests\Operator\UpdateAccountPhoneRequest;
use App\Http\Requests\Operator\UpdateAccountRequest;
use App\Http\Requests\Operator\UpdateLocationRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Services\OperatorAccountService;
use App\Services\OperatorLocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class AccountController extends Controller
{
    public function index(TwoFactorAuthenticationRequest $request, OperatorAccountService $accounts): Response
    {
        $user = $request->user();
        $user->loadMissing('operatorProfile');

        $props = [
            'account' => OperatorDashboardData::account($user),
            'smsVerification' => $accounts->usesSmsVerification(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $user->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
            $props['twoFactorLocked'] = $this->needsPasswordConfirmation($request);
        }

        return Inertia::render('operator/account/index', $props);
    }

    public function update(UpdateAccountRequest $request, OperatorAccountService $accounts): RedirectResponse
    {
        $accounts->updateProfile($request->user(), $request->validated());

        return $this->saved('Account updated.');
    }

    public function sendEmailCode(SendAccountEmailCodeRequest $request, OperatorAccountService $accounts): RedirectResponse
    {
        $email = $request->validated('email');

        $accounts->sendEmailCode($request->user(), $email);

        return $this->saved("We sent a 6-digit code to {$email}.");
    }

    public function updateEmail(UpdateAccountEmailRequest $request, OperatorAccountService $accounts): RedirectResponse
    {
        $accounts->changeEmail($request->user(), $request->validated('email'), $request->validated('code'));

        return $this->saved('Email updated.');
    }

    public function sendPhoneCode(SendAccountPhoneCodeRequest $request, OperatorAccountService $accounts): RedirectResponse
    {
        $phone = $request->validated('phone');

        $accounts->sendPhoneCode($request->user(), $phone);

        return $this->saved("We texted a 6-digit code to {$phone}.");
    }

    public function updatePhone(UpdateAccountPhoneRequest $request, OperatorAccountService $accounts): RedirectResponse
    {
        $accounts->changePhone($request->user(), $request->validated('phone'), $request->validated('code'));

        return $this->saved('Phone updated.');
    }

    /**
     * Reached after the password has been confirmed, which unlocks the two-factor section.
     */
    public function twoFactor(): RedirectResponse
    {
        return to_route('account.index');
    }

    public function location(Request $request, OperatorLocationService $locations): Response
    {
        $user = $request->user();
        $user->loadMissing('operatorProfile');

        return Inertia::render(
            'operator/account/location',
            $locations->settingsFor($user->operatorProfile),
        );
    }

    public function updateLocation(UpdateLocationRequest $request, OperatorLocationService $locations): RedirectResponse
    {
        $locations->update($request->user()->operatorProfile, $request->validated());

        return $this->saved('Location settings saved.');
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->back();
    }

    /**
     * Mirrors the check Laravel's "password.confirm" middleware makes.
     */
    private function needsPasswordConfirmation(Request $request): bool
    {
        if (! Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')) {
            return false;
        }

        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);

        return Date::now()->unix() - $confirmedAt > (int) config('auth.password_timeout', 10800);
    }
}
