<?php

use App\Http\Controllers\Staff\AssessmentPaymentScheduleController;
use App\Http\Controllers\Staff\PermitApplicationAssessmentController;
use App\Http\Controllers\Staff\PermitApplicationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::prefix('staff')->name('staff.')->middleware('can:staff.access')->group(function () {
        Route::get('permit-applications/assessments', [PermitApplicationAssessmentController::class, 'index'])
            ->name('permit-applications.assessments.index');
        Route::resource('permit-applications', PermitApplicationController::class)
            ->only(['index', 'create', 'store', 'show']);
        Route::post('permit-applications/{permitApplication}/assessments', [PermitApplicationAssessmentController::class, 'store'])
            ->name('permit-applications.assessments.store');
        Route::get('assessments/{assessment}', [PermitApplicationAssessmentController::class, 'show'])
            ->name('permit-applications.assessments.show');
        Route::post('assessments/{assessment}/payment-schedule', [AssessmentPaymentScheduleController::class, 'store'])
            ->name('assessments.payment-schedule.store');
        Route::get('payment-schedules/{paymentSchedule}', [AssessmentPaymentScheduleController::class, 'show'])
            ->name('payment-schedules.show');
    });
});

require __DIR__.'/settings.php';
