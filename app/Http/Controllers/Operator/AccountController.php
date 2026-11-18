<?php

namespace App\Http\Controllers\Operator;

use App\Demo\OperatorDashboardData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\UpdateLocationRequest;
use App\Services\OperatorLocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $user->loadMissing('operatorProfile');

        return Inertia::render('operator/account/index', [
            'account' => OperatorDashboardData::account($user),
        ]);
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

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Location settings saved.',
        ]);

        return redirect()->back();
    }
}
