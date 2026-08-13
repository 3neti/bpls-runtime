<?php

use App\Http\Controllers\Staff\PermitApplicationAssessmentController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::prefix('staff')->name('staff.')->group(function () {
        Route::get('permit-applications/assessments', [PermitApplicationAssessmentController::class, 'index'])
            ->name('permit-applications.assessments.index');
        Route::post('permit-applications/{permitApplication}/assessments', [PermitApplicationAssessmentController::class, 'store'])
            ->name('permit-applications.assessments.store');
        Route::get('assessments/{assessment}', [PermitApplicationAssessmentController::class, 'show'])
            ->name('permit-applications.assessments.show');
    });
});

require __DIR__.'/settings.php';
