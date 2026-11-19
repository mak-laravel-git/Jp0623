<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RouteInquiryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RouteInquiry\UpdateRouteInquiryStatusRequest;
use App\Models\RouteListingInquiry;
use App\Services\RouteListingInquiryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RouteInquiryController extends Controller
{
    public function __construct(private RouteListingInquiryService $inquiries) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = RouteInquiryStatus::tryFrom((string) $request->query('status', ''));

        return Inertia::render('admin/route-inquiries/index', [
            'inquiries' => $this->inquiries->paginate($search, $status),
            'filters' => [
                'search' => $search,
                'status' => $status?->value,
            ],
        ]);
    }

    public function show(RouteListingInquiry $routeInquiry): Response
    {
        return Inertia::render('admin/route-inquiries/show', [
            'inquiry' => $routeInquiry->load([
                'routeListing:id,city,about,asking_price,is_active',
                'user:id,name,email',
            ]),
        ]);
    }

    public function update(UpdateRouteInquiryStatusRequest $request, RouteListingInquiry $routeInquiry): RedirectResponse
    {
        $this->inquiries->updateStatus($routeInquiry, RouteInquiryStatus::from($request->validated('status')));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inquiry status updated.']);

        return redirect()->back();
    }
}
