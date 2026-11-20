<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\HeroSection\UpdateHeroSectionRequest;
use App\Services\HeroSectionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class HeroSectionController extends Controller
{
    public function __construct(private HeroSectionService $heroSection) {}

    public function edit(): Response
    {
        return Inertia::render('admin/hero-section/edit', [
            'heroSection' => $this->heroSection->current(),
        ]);
    }

    public function update(UpdateHeroSectionRequest $request): RedirectResponse
    {
        $this->heroSection->update($this->heroSection->current(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Hero section updated successfully.']);

        return redirect()->back();
    }
}
