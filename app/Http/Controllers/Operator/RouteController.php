<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Services\RouteListingService;
use Inertia\Inertia;
use Inertia\Response;

class RouteController extends Controller
{
    public function __construct(private RouteListingService $routeListings) {}

    public function index(): Response
    {
        return Inertia::render('operator/routes/index', [
            'routes' => $this->routeListings->listActiveForMembers(),
        ]);
    }
}
