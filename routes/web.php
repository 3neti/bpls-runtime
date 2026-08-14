<?php

use App\Http\Controllers\Citizen\PermitApplicationController as CitizenPermitApplicationController;
use App\Http\Controllers\Citizen\PermitApplicationDocumentController as CitizenPermitApplicationDocumentController;
use App\Http\Controllers\PublicPermitVerificationController;
use App\Http\Controllers\PublicPermitVerificationPageController;
use App\Http\Controllers\Staff\AssessmentPaymentScheduleController;
use App\Http\Controllers\Staff\CollectionReceiptController;
use App\Http\Controllers\Staff\DailyCollectionReportController;
use App\Http\Controllers\Staff\FeeRuleController;
use App\Http\Controllers\Staff\PaidEstablishmentReportController;
use App\Http\Controllers\Staff\PaymentScheduleCollectionController;
use App\Http\Controllers\Staff\PermitApplicationAssessmentController;
use App\Http\Controllers\Staff\PermitApplicationController;
use App\Http\Controllers\Staff\PermitApplicationDocumentController;
use App\Http\Controllers\Staff\ReceiptController;
use App\Http\Controllers\Staff\RevenueSourceReportController;
use App\Http\Controllers\Staff\StoryboardController;
use App\Http\Controllers\Staff\TopEstablishmentTaxDueReportController;
use App\Http\Controllers\Staff\UnpaidEstablishmentReportController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('permits/verify/{permitApplication}/{verificationCode}/view', PublicPermitVerificationPageController::class)
    ->name('public.permits.verify.view');
Route::get('permits/verify/{permitApplication}/{verificationCode}', PublicPermitVerificationController::class)
    ->name('public.permits.verify');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::prefix('citizen')->name('citizen.')->middleware('can:citizen.access')->group(function () {
        Route::resource('permit-applications', CitizenPermitApplicationController::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        Route::post('permit-applications/{permitApplication}/documents', [CitizenPermitApplicationDocumentController::class, 'store'])
            ->name('permit-applications.documents.store');
        Route::get('permit-applications/{permitApplication}/documents/{document}/download', [CitizenPermitApplicationDocumentController::class, 'download'])
            ->name('permit-applications.documents.download');
    });

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
        Route::post('permit-applications/{permitApplication}/cancel', [PermitApplicationController::class, 'cancel'])
            ->name('permit-applications.cancel');
        Route::post('permit-applications/{permitApplication}/release', [PermitApplicationController::class, 'release'])
            ->name('permit-applications.release');
        Route::post('permit-applications/{permitApplication}/clearances/{clearance}/complete', [PermitApplicationController::class, 'completeClearance'])
            ->name('permit-applications.clearances.complete');
        Route::post('permit-applications/{permitApplication}/documents', [PermitApplicationDocumentController::class, 'store'])
            ->name('permit-applications.documents.store');
        Route::get('permit-applications/{permitApplication}/documents/{document}/download', [PermitApplicationDocumentController::class, 'download'])
            ->name('permit-applications.documents.download');
        Route::get('fee-rules', [FeeRuleController::class, 'index'])
            ->name('fee-rules.index');
        Route::get('fee-rules/{feeRule}', [FeeRuleController::class, 'show'])
            ->name('fee-rules.show');
        Route::get('assessments/{assessment}', [PermitApplicationAssessmentController::class, 'show'])
            ->name('permit-applications.assessments.show');
        Route::get('assessments/{assessment}/pdf', [PermitApplicationAssessmentController::class, 'pdf'])
            ->name('permit-applications.assessments.pdf');
        Route::post('assessments/{assessment}/payment-schedule', [AssessmentPaymentScheduleController::class, 'store'])
            ->name('assessments.payment-schedule.store');
        Route::get('payment-schedules', [AssessmentPaymentScheduleController::class, 'index'])
            ->name('payment-schedules.index');
        Route::get('payment-schedules/{paymentSchedule}', [AssessmentPaymentScheduleController::class, 'show'])
            ->name('payment-schedules.show');
        Route::post('payment-schedules/{paymentSchedule}/collections', [PaymentScheduleCollectionController::class, 'store'])
            ->name('payment-schedules.collections.store');
        Route::post('collections/{collection}/receipt', [CollectionReceiptController::class, 'store'])
            ->name('collections.receipt.store');
        Route::get('receipts', [ReceiptController::class, 'index'])
            ->name('receipts.index');
        Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])
            ->name('receipts.show');
        Route::get('receipts/{receipt}/pdf', [ReceiptController::class, 'pdf'])
            ->name('receipts.pdf');
        Route::post('receipts/{receipt}/void', [ReceiptController::class, 'voidReceipt'])
            ->name('receipts.void');
        Route::get('reports/daily-collections', [DailyCollectionReportController::class, 'index'])
            ->name('reports.daily-collections.index');
        Route::get('reports/daily-collections/download', [DailyCollectionReportController::class, 'download'])
            ->name('reports.daily-collections.download');
        Route::get('reports/revenue-sources', [RevenueSourceReportController::class, 'index'])
            ->name('reports.revenue-sources.index');
        Route::get('reports/revenue-sources/download', [RevenueSourceReportController::class, 'download'])
            ->name('reports.revenue-sources.download');
        Route::get('reports/paid-establishments', [PaidEstablishmentReportController::class, 'index'])
            ->name('reports.paid-establishments.index');
        Route::get('reports/paid-establishments/download', [PaidEstablishmentReportController::class, 'download'])
            ->name('reports.paid-establishments.download');
        Route::get('reports/unpaid-establishments', [UnpaidEstablishmentReportController::class, 'index'])
            ->name('reports.unpaid-establishments.index');
        Route::get('reports/unpaid-establishments/download', [UnpaidEstablishmentReportController::class, 'download'])
            ->name('reports.unpaid-establishments.download');
        Route::get('reports/top-establishments-tax-due', [TopEstablishmentTaxDueReportController::class, 'index'])
            ->name('reports.top-establishments-tax-due.index');
        Route::get('reports/top-establishments-tax-due/download', [TopEstablishmentTaxDueReportController::class, 'download'])
            ->name('reports.top-establishments-tax-due.download');
        Route::post('storyboards/{storyboard}/exports/pdf', [StoryboardController::class, 'exportPdf'])
            ->name('storyboards.exports.pdf');
        Route::post('storyboards/{storyboard}/exports/video', [StoryboardController::class, 'exportVideo'])
            ->name('storyboards.exports.video');
        Route::resource('storyboards', StoryboardController::class);
    });
});

require __DIR__.'/settings.php';
