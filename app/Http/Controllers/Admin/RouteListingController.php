<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RouteListing\StoreRouteListingRequest;
use App\Http\Requests\RouteListing\UpdateRouteListingRequest;
use App\Models\RouteListing;
use App\Services\RouteListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RouteListingController extends Controller
{
    public function __construct(private RouteListingService $routeListings) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('admin/route-listings/index', [
            'routeListings' => $this->routeListings->paginate($search),
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/route-listings/create');
    }

    public function store(StoreRouteListingRequest $request): RedirectResponse
    {
        $this->routeListings->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Listing created successfully.']);

        return redirect()->route('admin.route-listings.index');
    }

    public function edit(RouteListing $routeListing): Response
    {
        return Inertia::render('admin/route-listings/edit', [
            'routeListing' => $routeListing,
        ]);
    }

    public function update(UpdateRouteListingRequest $request, RouteListing $routeListing): RedirectResponse
    {
        $this->routeListings->update($routeListing, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Listing updated successfully.']);

        return redirect()->route('admin.route-listings.index');
    }

    public function destroy(RouteListing $routeListing): RedirectResponse
    {
        $this->routeListings->delete($routeListing);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Listing deleted successfully.']);

        return redirect()->back();
    }
}
