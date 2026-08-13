<?php

use App\Http\Controllers\Staff\AssessmentPaymentScheduleController;
use App\Http\Controllers\Staff\CollectionReceiptController;
use App\Http\Controllers\Staff\PaymentScheduleCollectionController;
use App\Http\Controllers\Staff\PermitApplicationAssessmentController;
use App\Http\Controllers\Staff\PermitApplicationController;
use App\Http\Controllers\Staff\ReceiptController;
use App\Http\Controllers\Staff\StoryboardController;
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
        Route::get('permit-applications/{permitApplication}/application-form.pdf', [PermitApplicationController::class, 'applicationFormPdf'])
            ->name('permit-applications.application-form.pdf');
        Route::get('permit-applications/{permitApplication}/permit.pdf', [PermitApplicationController::class, 'permitPdf'])
            ->name('permit-applications.permit.pdf');
        Route::get('assessments/{assessment}', [PermitApplicationAssessmentController::class, 'show'])
            ->name('permit-applications.assessments.show');
        Route::get('assessments/{assessment}/pdf', [PermitApplicationAssessmentController::class, 'pdf'])
            ->name('permit-applications.assessments.pdf');
        Route::post('assessments/{assessment}/payment-schedule', [AssessmentPaymentScheduleController::class, 'store'])
            ->name('assessments.payment-schedule.store');
        Route::get('payment-schedules/{paymentSchedule}', [AssessmentPaymentScheduleController::class, 'show'])
            ->name('payment-schedules.show');
        Route::post('payment-schedules/{paymentSchedule}/collections', [PaymentScheduleCollectionController::class, 'store'])
            ->name('payment-schedules.collections.store');
        Route::post('collections/{collection}/receipt', [CollectionReceiptController::class, 'store'])
            ->name('collections.receipt.store');
        Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])
            ->name('receipts.show');
        Route::get('receipts/{receipt}/pdf', [ReceiptController::class, 'pdf'])
            ->name('receipts.pdf');
        Route::post('receipts/{receipt}/void', [ReceiptController::class, 'voidReceipt'])
            ->name('receipts.void');
        Route::post('storyboards/{storyboard}/exports/pdf', [StoryboardController::class, 'exportPdf'])
            ->name('storyboards.exports.pdf');
        Route::post('storyboards/{storyboard}/exports/video', [StoryboardController::class, 'exportVideo'])
            ->name('storyboards.exports.video');
        Route::resource('storyboards', StoryboardController::class);
    });
});

require __DIR__.'/settings.php';
