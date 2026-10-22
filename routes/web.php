<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Controllers\UpdateProfileOfficeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::patch('profile/office', UpdateProfileOfficeController::class)->name('profile.office.update');

    Route::get('service-requests', [ServiceRequestController::class, 'index'])
        ->name('service-requests.index');
    Route::get('service-requests/create', [ServiceRequestController::class, 'create'])
        ->name('service-requests.create');
    Route::post('service-requests', [ServiceRequestController::class, 'store'])
        ->name('service-requests.store');
    Route::get('service-requests/{serviceRequest}', [ServiceRequestController::class, 'show'])
        ->name('service-requests.show');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
