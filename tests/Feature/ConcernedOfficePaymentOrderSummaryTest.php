<?php

use App\Actions\BuildBploRoutingTask;
use App\Actions\BuildConcernedOfficePaymentOrderSummary;
use App\Enums\UserPermission;
use App\Models\BploRoutingDetermination;
use App\Models\BploRoutingWork;
use App\Models\PaperlessPaymentOrder;
use App\Models\PaperlessPaymentOrderLine;
use App\Models\PermitApplication;
use App\Models\SignatureEvidence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('issued concerned-office Payment Orders finalize independently of Treasury LOB classification', function () {
    Storage::fake('local');
    $application = PermitApplication::factory()->create([
        'type' => 'new',
        'application_year' => 2025,
        'metadata' => ['nelson_reconciliation_v1' => [
            'commissioned_path' => true,
            'applicant_selects_official_lob' => false,
            'inspection_in_scope' => false,
        ]],
    ]);
    $determination = BploRoutingDetermination::factory()->for($application)->create();
    $amounts = [
        'engineering' => ['Municipal Engineering Office', 12_500],
        'health' => ['Municipal Health Office', 9_500],
        'menro' => ['MENRO', 4_000],
        'assessor' => ['Municipal Assessor', 6_000],
    ];

    foreach ($amounts as $officeCode => [$officeLabel, $amount]) {
        $work = BploRoutingWork::factory()->for($determination, 'determination')->create([
            'office_code' => $officeCode,
            'office_label' => $officeLabel,
        ]);
        $order = PaperlessPaymentOrder::factory()->for($work, 'routingWork')->create([
            'permit_application_id' => $application->id,
            'total_amount_cents' => $amount,
        ]);
        PaperlessPaymentOrderLine::factory()->for($order, 'paymentOrder')->create([
            'code' => 'TEST-'.str($officeCode)->upper(),
            'name' => $officeLabel.' fee',
            'amount_cents' => $amount,
        ]);
        $signature = SignatureEvidence::factory()->create([
            'signer_id' => $order->issued_by_id,
            'signable_type' => $order->getMorphClass(),
            'signable_id' => $order->id,
            'purpose' => 'concerned_office_payment_order_confirmation',
        ]);
        $signature->addMedia(UploadedFile::fake()->image($officeCode.'-signature.png'))
            ->toMediaCollection(SignatureEvidence::FacsimileCollection, 'local');
    }

    $summary = app(BuildConcernedOfficePaymentOrderSummary::class)->handle($application->fresh());
    $treasury = userWithPermissions([UserPermission::CorrectEvaluationLinesOfBusiness]);
    $routingTask = app(BuildBploRoutingTask::class)->handle($application->fresh(), $treasury)->toArray();
    $restrictedTask = app(BuildBploRoutingTask::class)->handle($application->fresh(), null)->toArray();

    expect($application->treasuryLineOfBusinessAssignments()->count())->toBe(0)
        ->and($summary['status'])->toBe('finalized')
        ->and($summary['all_finalized'])->toBeTrue()
        ->and($summary['required_office_count'])->toBe(4)
        ->and($summary['finalized_office_count'])->toBe(4)
        ->and($summary['recorded_subtotal_amount_cents'])->toBe(32_000)
        ->and($summary['finalized_subtotal_amount_cents'])->toBe(32_000)
        ->and($summary['assessment_total_amount_cents'])->toBeNull()
        ->and($summary['next_stage'])->toBe('treasury_lob_classification')
        ->and(data_get($routingTask, 'financial_editor.concerned_office_payment_orders'))->toBe($summary)
        ->and(collect(data_get($routingTask, 'routing.works'))->pluck('payment_orders')->flatten(1)->pluck('signature_facsimile_data_url')->every(
            fn (?string $facsimile): bool => str_starts_with((string) $facsimile, 'data:image/png;base64,'),
        ))->toBeTrue()
        ->and(collect(data_get($restrictedTask, 'routing.works'))->pluck('payment_orders')->flatten(1)->pluck('signature_facsimile_data_url')->filter())->toBeEmpty()
        ->and(collect($summary['offices'])->pluck('total_amount_cents')->all())->toBe([12_500, 9_500, 4_000, 6_000])
        ->and(collect($summary['offices'])->flatMap(fn (array $office): array => $office['lines'])->pluck('code')->all())->toBe([
            'TEST-ENGINEERING',
            'TEST-HEALTH',
            'TEST-MENRO',
            'TEST-ASSESSOR',
        ]);
});

test('missing or conflicting current orders withhold the finalized subtotal and superseded orders are excluded', function () {
    $application = PermitApplication::factory()->create();
    $determination = BploRoutingDetermination::factory()->for($application)->create();
    $engineering = BploRoutingWork::factory()->for($determination, 'determination')->create();
    BploRoutingWork::factory()->for($determination, 'determination')->create([
        'office_code' => 'health',
        'office_label' => 'Municipal Health Office',
    ]);
    PaperlessPaymentOrder::factory()->for($engineering, 'routingWork')->create([
        'permit_application_id' => $application->id,
        'sequence' => 1,
        'total_amount_cents' => 99_900,
        'superseded_at' => now(),
    ]);
    $currentEngineeringOrder = PaperlessPaymentOrder::factory()->for($engineering, 'routingWork')->create([
        'permit_application_id' => $application->id,
        'sequence' => 2,
        'total_amount_cents' => 12_500,
    ]);
    PaperlessPaymentOrderLine::factory()->for($currentEngineeringOrder, 'paymentOrder')->create([
        'amount_cents' => 12_500,
    ]);

    $incomplete = app(BuildConcernedOfficePaymentOrderSummary::class)->handle($application->fresh());
    expect($incomplete['status'])->toBe('in_progress')
        ->and($incomplete['finalized_office_count'])->toBe(1)
        ->and($incomplete['recorded_subtotal_amount_cents'])->toBe(12_500)
        ->and($incomplete['finalized_subtotal_amount_cents'])->toBeNull()
        ->and(data_get($incomplete, 'offices.1.status'))->toBe('awaiting_payment_order');

    PaperlessPaymentOrder::factory()->for($engineering, 'routingWork')->create([
        'permit_application_id' => $application->id,
        'sequence' => 3,
        'total_amount_cents' => 13_000,
    ]);
    $conflicted = app(BuildConcernedOfficePaymentOrderSummary::class)->handle($application->fresh());

    expect(data_get($conflicted, 'offices.0.status'))->toBe('conflict')
        ->and(data_get($conflicted, 'offices.0.total_amount_cents'))->toBeNull()
        ->and($conflicted['recorded_subtotal_amount_cents'])->toBe(0)
        ->and($conflicted['finalized_subtotal_amount_cents'])->toBeNull();
});

test('Nelson assessment presentation uses the Payment Order stage and honest Treasury boundary copy', function () {
    $page = file_get_contents(resource_path('js/pages/business-permit-evaluations/Show.vue'));
    $summary = file_get_contents(resource_path('js/components/permit-applications/ConcernedOfficePaymentOrderSummary.vue'));
    $routingTask = file_get_contents(resource_path('js/components/permit-applications/BploRoutingTaskSheet.vue'));
    $documentReference = file_get_contents(resource_path('js/components/permit-applications/ApplicantDocumentReference.vue'));

    expect($page)->toContain('!officeWorkspace && !isNelsonPath')
        ->and($page)->toContain("isNelsonPath ? 'Payment Orders' : 'Evaluation'")
        ->and($page)->toContain('concerned-office-executable-workspace')
        ->and($page)->toContain('initial-task="bplo-routing"')
        ->and($page)->toContain('Office basis')
        ->and($page)->toContain('Application details')
        ->and($page)->toContain('Municipal facts')
        ->and($page)->toContain('assessment-action-rail')
        ->and($page)->toContain('assessment-financial-sources')
        ->and($page)->toContain(':show-routing-task="!showAssessmentActionRail"')
        ->and($page)->toContain('Freeze the current total as the Assessment.')
        ->and($page)->toContain('Review financial sources')
        ->and($summary)->toContain('Payment Order summary')
        ->and($routingTask)->toContain('treasury-classification-workspace')
        ->and($routingTask)->toContain('Assign official Lines of Business')
        ->and($routingTask)->toContain('Payment items for selected LOB')
        ->and($routingTask)->toContain('treasury-payment-order-reference')
        ->and($routingTask)->toContain('treasury-routing-decision')
        ->and($routingTask)->toContain('Routing decision')
        ->and($routingTask)->toContain('signature_facsimile_data_url')
        ->and($routingTask)->toContain('Captured signature facsimile')
        ->and($routingTask)->not->toContain('treasury-routing-evidence')
        ->and($routingTask)->not->toContain('BPLO routing details')
        ->and($documentReference)->toContain('Applicant documents')
        ->and($documentReference)->toContain('View ${document.label}')
        ->and($summary)->toContain('Office Payment Order subtotal')
        ->and($summary)->not->toContain('Assessment total')
        ->and($summary)->toContain("'TBD'")
        ->and($summary)->not->toContain('Evaluated total');
});
