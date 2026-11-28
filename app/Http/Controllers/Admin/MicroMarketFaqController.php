<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MicroMarketFaq\StoreMicroMarketFaqRequest;
use App\Http\Requests\MicroMarketFaq\UpdateMicroMarketFaqRequest;
use App\Models\MicroMarketFaq;
use App\Services\MicroMarketFaqService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MicroMarketFaqController extends Controller
{
    public function __construct(private MicroMarketFaqService $faqs) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('admin/micro-market-faqs/index', [
            'faqs' => $this->faqs->paginate($search),
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/micro-market-faqs/create', [
            'defaultSortOrder' => $this->faqs->nextSortOrder(),
        ]);
    }

    public function store(StoreMicroMarketFaqRequest $request): RedirectResponse
    {
        $this->faqs->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Micro market FAQ created successfully.']);

        return redirect()->route('admin.micro-market-faqs.index');
    }

    public function edit(MicroMarketFaq $microMarketFaq): Response
    {
        return Inertia::render('admin/micro-market-faqs/edit', [
            'faq' => $microMarketFaq,
        ]);
    }

    public function update(
        UpdateMicroMarketFaqRequest $request,
        MicroMarketFaq $microMarketFaq,
    ): RedirectResponse {
        $this->faqs->update($microMarketFaq, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Micro market FAQ updated successfully.']);

        return redirect()->route('admin.micro-market-faqs.index');
    }

    public function destroy(MicroMarketFaq $microMarketFaq): RedirectResponse
    {
        $this->faqs->delete($microMarketFaq);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Micro market FAQ deleted successfully.']);

        return redirect()->back();
    }
}
