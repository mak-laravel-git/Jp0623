<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RouteListingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RouteController extends Controller
{
    public function __construct(private RouteListingService $routeListings) {}

    public function index(Request $request): Response
    {
        return Inertia::render('operator/routes/index', [
            'routes' => $this->routeListings->listActiveForMembers(),
            'memberContact' => $this->memberContact($request->user()),
        ]);
    }

    /**
     * Contact details used to prefill the inquiry form (same sources as the lead purchase emails).
     *
     * @return array{name: string, email: string, phone: string}
     */
    private function memberContact(User $user): array
    {
        $profile = $user->operatorProfile;
        $name = trim(($profile?->first_name ?? '').' '.($profile?->last_name ?? ''));

        return [
            'name' => $name !== '' ? $name : $user->name,
            'email' => $user->email,
            'phone' => $profile?->phone ?? '',
        ];
    }
}
