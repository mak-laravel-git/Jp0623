<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::middleware('settings.staff')->group(function () {
        Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('settings.staff')->group(function () {
        Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');
    });

    // Also used by members, from their "Account info" page.
    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');
});
