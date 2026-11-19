<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\RouteListing\StoreRouteListingInquiryRequest;
use App\Models\RouteListing;
use App\Services\RouteListingInquiryService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class RouteInquiryController extends Controller
{
    public function __construct(private RouteListingInquiryService $inquiries) {}

    public function store(StoreRouteListingInquiryRequest $request, RouteListing $routeListing): RedirectResponse
    {
        abort_unless($routeListing->is_active, 404);

        $this->inquiries->create($request->user(), $routeListing, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inquiry sent. We will contact you soon.']);

        return redirect()->back();
    }
}
