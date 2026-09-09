<?php

use App\Actions\AssignTreasuryLinesOfBusiness;
use App\Actions\BuildExecutablePermitApplicationDocument;
use App\Actions\BuildScheduleOfPayment;
use App\Actions\CommissionPostPaymentOfficeCertifications;
use App\Actions\ConfirmOfficePaymentOrder;
use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\CreateCitizenPermitApplicationDraft;
use App\Actions\CreatePaymentScheduleForAssessment;
use App\Actions\IssueManualCollectionReceipt;
use App\Actions\IssueSyntheticLifecyclePermit;
use App\Actions\ProjectPermitReadiness;
use App\Actions\RecordAssessmentDecision;
use App\Actions\RecordBploRoutingDetermination;
use App\Actions\RecordPaymentScheduleCollection;
use App\Actions\RecordPostPaymentOfficeCertification;
use App\Actions\ReleaseSyntheticLifecyclePermit;
use App\Actions\RenderPermitPdf;
use App\Actions\StorePermitApplicationDocument;
use App\Actions\SubmitCitizenPermitApplication;
use App\Data\Application\ApplicationDataResolver;
use App\Enums\AssessmentDecisionAction;
use App\Enums\FeeDeterminationChannel;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Enums\PermitApplicationType;
use App\Enums\TreasuryCollectionStatus;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\FeeRule;
use App\Models\LifecycleCleanroomRun;
use App\Models\LineOfBusiness;
use App\Models\SignatureEvidence;
use App\Models\User;
use Database\Seeders\NelsonConcernedOfficeFeeCatalogSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;

test('Nelson cleanroom ceremony preserves applicant truth and reconciles one collection to every canonical OR', function () {
    Storage::fake('local');
    $this->seed(NelsonConcernedOfficeFeeCatalogSeeder::class);
    expect(config('ipil_references.barangays.items'))->toHaveCount(28);
    $citizen = userWithPermissions([UserPermission::AccessCitizen, UserPermission::CreateOwnPermitApplications], UserRole::Citizen);
    $staffPermissions = [
        UserPermission::AccessStaff,
        UserPermission::DetermineBploRouting,
        UserPermission::ContributeBusinessPermitEvaluations,
        UserPermission::AssessPermitApplications,
        UserPermission::CorrectEvaluationLinesOfBusiness,
        UserPermission::ApproveAssessments,
        UserPermission::RecordCollections,
        UserPermission::IssueReceipts,
    ];
    $bplo = userWithPermissions($staffPermissions, UserRole::Bplo);
    $engineering = User::factory()->for($bplo->role)->create();
    $health = User::factory()->for($bplo->role)->create();
    $assessor = User::factory()->for($bplo->role)->create();
    $treasurer = User::factory()->for($bplo->role)->create();
    $cashier = User::factory()->for($bplo->role)->create();
    $permitIssuer = User::factory()->for($bplo->role)->create();
    $releasingOfficer = User::factory()->for($bplo->role)->create();
    $run = LifecycleCleanroomRun::factory()->for($bplo, 'startedBy')->create([
        'actor_manifest' => [
            'actors' => [
                'engineering' => ['label' => 'Engineering', 'user_id' => $engineering->id, 'role_id' => $engineering->role_id],
                'health' => ['label' => 'Health', 'user_id' => $health->id, 'role_id' => $health->role_id],
                'permit_issuer' => ['label' => 'Mayor\'s Office', 'user_id' => $permitIssuer->id, 'role_id' => $permitIssuer->role_id],
                'releasing_officer' => ['label' => 'Releasing Officer', 'user_id' => $releasingOfficer->id, 'role_id' => $releasingOfficer->role_id],
            ],
            'semantic_classification' => 'synthetic_only',
            'production_liability' => false,
        ],
    ]);

    $application = app(CreateCitizenPermitApplicationDraft::class)->handle([
        'application_year' => now()->year,
        'owner_name' => 'Nelson Cleanroom Applicant',
        'business_name' => 'Nelson General Merchandise, Liquor and Coffee',
        'business_activity_description' => 'General merchandise store selling household goods and liquor, with a small coffee shop.',
        'business_address' => 'Purok 1, Poblacion, Ipil',
        'barangay' => 'Poblacion',
        'business_barangay_psgc_code' => '0908305023',
        'type' => 'new',
        'date_of_application' => now()->toDateString(),
        'mode_of_payment' => 'annually',
        'undertaking_accepted' => true,
        'applicant_printed_name' => 'Nelson Cleanroom Applicant',
        'position_title' => 'Owner',
    ], $citizen);
    $metadata = $application->metadata;
    $metadata['lifecycle_cleanroom'] = [
        'run_id' => $run->public_id,
        'semantic_classification' => 'synthetic_only',
        'production_liability' => false,
    ];
    $application->update(['metadata' => $metadata]);
    $run->update(['new_application_id' => $application->id]);
    expect($application->lines)->toBeEmpty()
        ->and($application->business->barangay_psgc_code)->toBe('0908305023');

    $document = app(StorePermitApplicationDocument::class)->handle($application, [
        'label' => 'DTI registration specimen',
        'document_type' => 'dti_registration',
        'file' => UploadedFile::fake()->create('dti.pdf', 24, 'application/pdf'),
        'source' => 'citizen_draft',
    ], $citizen);
    $application = app(SubmitCitizenPermitApplication::class)->handle(
        $application,
        $citizen,
        true,
        UploadedFile::fake()->image('applicant-signature.png'),
    );
    $frozen = $application->declaration()->sole();
    $manifest = data_get($frozen->snapshot, 'applicant_documents_manifest');
    $signature = SignatureEvidence::query()
        ->where('signable_type', $frozen->getMorphClass())
        ->where('signable_id', $frozen->id)
        ->where('purpose', 'applicant_lodging')
        ->sole();
    $applicationData = app(ApplicationDataResolver::class)->resolve($application, $citizen)->toArray();
    $executableDocument = app(BuildExecutablePermitApplicationDocument::class)->handle($application, $citizen);
    expect(data_get($frozen->snapshot, 'applicant_business_activity_description'))->toBe($application->business_activity_description)
        ->and(data_get($frozen->snapshot, 'lines_of_business'))->toBe([])
        ->and(data_get($manifest, 'documents.0.document_id'))->toBe($document->id)
        ->and(data_get($manifest, 'documents.0.media_id'))->toBeInt()
        ->and(data_get($manifest, 'documents.0.checksum_sha256'))->toHaveLength(64)
        ->and(data_get($manifest, 'digest'))->toHaveLength(64)
        ->and($signature->method)->toBe('captured_facsimile')
        ->and($signature->signer_id)->toBe($citizen->id)
        ->and($signature->evidence_digest)->toHaveLength(64)
        ->and($signature->getFirstMedia(SignatureEvidence::FacsimileCollection))->not->toBeNull()
        ->and(data_get($applicationData, 'signature_evidence.0.purpose'))->toBe('applicant_lodging')
        ->and(data_get($applicationData, 'signature_evidence.0.facsimile_data_url'))->toStartWith('data:image/png;base64,')
        ->and(data_get($executableDocument, 'commissioned_path'))->toBeTrue()
        ->and(data_get($executableDocument, 'page_2_assessment.processing_summary'))->toBe('Awaiting BPLO routing')
        ->and(data_get($executableDocument, 'page_2_assessment.total_label'))->toBe('Current total')
        ->and(data_get($executableDocument, 'page_2_assessment.total_source'))->toBe('pending_canonical_inputs')
        ->and(data_get($executableDocument, 'page_2_assessment.emerging_total_amount_cents'))->toBeNull()
        ->and(data_get($executableDocument, 'signature_evidence.0.evidence_digest'))->toBe($signature->evidence_digest)
        ->and(data_get($executableDocument, 'signature_evidence.0.facsimile_data_url'))->toBe(data_get($applicationData, 'signature_evidence.0.facsimile_data_url'));

    app(StorePermitApplicationDocument::class)->handle($application, [
        'label' => 'Later supplementary specimen',
        'document_type' => 'other',
        'file' => UploadedFile::fake()->create('later.pdf', 12, 'application/pdf'),
        'source' => 'post_lodging_supplement',
    ], $citizen);
    expect($application->declaration()->sole()->snapshot)->toBe($frozen->snapshot);

    $routing = app(RecordBploRoutingDetermination::class)->handle($application, $bplo, '', [
        ['office_code' => 'engineering', 'office_label' => 'Browser supplied label is not authoritative', 'situational_reason' => '', 'required_work' => ''],
        ['office_code' => 'health', 'office_label' => 'Browser supplied label is not authoritative', 'situational_reason' => '', 'required_work' => ''],
    ]);
    expect($routing->works)->toHaveCount(2)
        ->and($routing->works->pluck('office_label')->all())->toBe(['Municipal Engineering Office', 'Municipal Health Office'])
        ->and(data_get($routing->application_facts_snapshot, 'concerned_office_reference.schema_version'))->toBe('ipil.concerned-offices.preview.v1')
        ->and($application->bploRoutingSuggestion()->exists())->toBeFalse()
        ->and(collect($routing->works)->pluck('context_snapshot')->flatten()->contains('inspection'))->toBeFalse();

    foreach ($routing->works as $officeIndex => $work) {
        $fee = FeeRule::query()
            ->where('metadata->responsible_office_code', $work->office_code)
            ->where('metadata->application_year', now()->year)
            ->firstOrFail();
        app(ConfirmOfficePaymentOrder::class)->handle($work, [[
            'fee_rule_id' => $fee->id,
            'amount_cents' => $fee->amount_cents + ($officeIndex === 0 ? 100 : 0),
        ]], User::query()->findOrFail(data_get($run->actor_manifest, 'actors.'.$work->office_code.'.user_id')), UploadedFile::fake()->image("{$work->office_code}-signature.png"));
        if ($officeIndex === 0) {
            $partialDocument = app(BuildExecutablePermitApplicationDocument::class)->handle($application->fresh(), $treasurer);
            expect(data_get($partialDocument, 'page_2_assessment.processing_summary'))->toBe('Payment Orders · 1 of 2');
        }
    }
    $editedOfficeLine = $application->paperlessPaymentOrders()->with('lines')->oldest('id')->firstOrFail()->lines->sole();
    expect(data_get($editedOfficeLine->source_snapshot, 'variance_minor'))->toBe(100)
        ->and(data_get($editedOfficeLine->source_snapshot, 'reason'))->toBe('Amount edited in the Nelson financial line-item editor.')
        ->and(data_get($editedOfficeLine->source_snapshot, 'authority'))->toContain('business_permit_evaluations.contribute');
    $paymentOrdersCompleteDocument = app(BuildExecutablePermitApplicationDocument::class)->handle($application->fresh(), $treasurer);
    expect(data_get($paymentOrdersCompleteDocument, 'page_2_assessment.processing_summary'))
        ->toBe('Awaiting Treasury classification · 2 Payment Orders');

    $lobSelections = [];
    foreach ([
        ['General Merchandise', 15_000],
        ['Retail Sale of Liquor', 17_500],
        ['Coffee Shop', 20_000],
    ] as [$name, $amount]) {
        $lob = LineOfBusiness::factory()->create(['name' => $name, 'metadata' => []]);
        $fee = FeeRule::factory()->create([
            'line_of_business_id' => $lob->id,
            'code' => 'NELSON-LOB-'.$lob->id,
            'name' => $name.' permit component',
            'scope' => FeeRuleScope::LineOfBusiness,
            'determination_channel' => FeeDeterminationChannel::TreasuryLineOfBusiness,
            'calculation_type' => FeeRuleCalculationType::Fixed,
            'category' => FeeRuleCategory::Fee,
            'amount_cents' => $amount,
            'metadata' => ['classification' => 'synthetic_preview'],
        ]);
        $lobSelections[] = ['line_of_business_id' => $lob->id, 'items' => [['fee_rule_id' => $fee->id, 'amount_cents' => $fee->amount_cents]]];
    }
    $taxLob = LineOfBusiness::factory()->create(['name' => 'Forbidden Tax Test']);
    $tax = FeeRule::factory()->create([
        'line_of_business_id' => $taxLob->id,
        'scope' => FeeRuleScope::LineOfBusiness,
        'category' => FeeRuleCategory::Tax,
        'amount_cents' => 99_999,
    ]);
    expect(fn () => app(AssignTreasuryLinesOfBusiness::class)->handle($application, [[
        'line_of_business_id' => $taxLob->id,
        'items' => [['fee_rule_id' => $tax->id, 'amount_cents' => $tax->amount_cents]],
    ]], $treasurer))->toThrow(LogicException::class, 'Business Tax is prohibited');

    $lobSelections[0]['items'][0]['amount_cents'] += 200;
    $assignments = app(AssignTreasuryLinesOfBusiness::class)->handle($application, $lobSelections, $treasurer);
    expect($assignments)->toHaveCount(3)
        ->and($assignments[0]->items->sole()->variance_cents)->toBe(200)
        ->and(data_get($assignments[0]->items->sole()->source_snapshot, 'reason'))->toBe('Amount edited in the Nelson financial line-item editor.')
        ->and(data_get($assignments[0]->items->sole()->source_snapshot, 'authority'))->toContain('business_permit_evaluations.correct_lines_of_business')
        ->and($application->refresh()->business_activity_description)->toBe('General merchandise store selling household goods and liquor, with a small coffee shop.');

    $renewal = $application->replicate();
    $renewal->forceFill([
        'type' => PermitApplicationType::Renewal,
        'status' => 'draft',
        'application_year' => $application->application_year,
        'application_number' => null,
        'tracking_reference' => null,
        'assessed_at' => null,
    ])->save();
    $renewalRouting = app(RecordBploRoutingDetermination::class)->handle($renewal, $bplo, '', [
        ['office_code' => 'engineering', 'office_label' => 'Engineering', 'situational_reason' => '', 'required_work' => ''],
        ['office_code' => 'health', 'office_label' => 'Health', 'situational_reason' => '', 'required_work' => ''],
    ]);
    foreach ($renewalRouting->works as $work) {
        $fee = FeeRule::query()
            ->where('metadata->responsible_office_code', $work->office_code)
            ->where('metadata->application_year', now()->year)
            ->firstOrFail();
        app(ConfirmOfficePaymentOrder::class)->handle($work, [[
            'fee_rule_id' => $fee->id,
            'amount_cents' => $fee->amount_cents,
        ]], User::query()->findOrFail(data_get($run->actor_manifest, 'actors.'.$work->office_code.'.user_id')), UploadedFile::fake()->image("renewal-{$work->office_code}-signature.png"));
    }
    expect(app(AssignTreasuryLinesOfBusiness::class)->handle($renewal, $lobSelections, $treasurer))->toHaveCount(3);

    $paymentOrderSubtotal = (int) $application->paperlessPaymentOrders()->whereNull('superseded_at')->sum('total_amount_cents');
    $treasurySubtotal = (int) collect($assignments)->flatMap->items->sum('determined_amount_cents');
    $currentDocument = app(BuildExecutablePermitApplicationDocument::class)->handle($application->fresh(), $treasurer);
    $currentTotal = $paymentOrderSubtotal + $treasurySubtotal;
    expect(data_get($currentDocument, 'page_2_assessment.total_label'))->toBe('Current total')
        ->and(data_get($currentDocument, 'page_2_assessment.processing_summary'))->toBe('Ready for Assessment · 2 Payment Orders · 3 Lines of Business')
        ->and(data_get($currentDocument, 'page_2_assessment.total_source'))->toBe('canonical_price_projection')
        ->and(data_get($currentDocument, 'page_2_assessment.emerging_total_amount_cents'))->toBe($currentTotal)
        ->and($application->assessments()->count())->toBe(0);

    $assessment = app(CreateAssessmentForPermitApplication::class)->handle($application->fresh(), $assessor);
    $assessedDocument = app(BuildExecutablePermitApplicationDocument::class)->handle($application->fresh(), $treasurer);
    $report = $assessment->price_report_snapshot;
    $scheduleOfPayment = app(BuildScheduleOfPayment::class)->handle($assessment, $report)->toArray();
    expect($assessment->total_amount_cents)->toBe($currentTotal)
        ->and(data_get($assessedDocument, 'page_2_assessment.processing_summary'))->toBe('Assessment prepared · ₱'.number_format($currentTotal / 100, 2))
        ->and(data_get($assessedDocument, 'page_2_assessment.total_label'))->toBe('Assessment total')
        ->and(data_get($assessedDocument, 'page_2_assessment.total_source'))->toBe('assessment')
        ->and(data_get($assessedDocument, 'page_2_assessment.emerging_total_amount_cents'))->toBe($assessment->total_amount_cents)
        ->and(collect($report['components'])->pluck('type'))->not->toContain('business_tax')
        ->and($scheduleOfPayment['groups'])->toHaveCount(5)
        ->and($scheduleOfPayment['grand_total_minor'])->toBe($report['total']['minor'])
        ->and($scheduleOfPayment['assessment_total_minor'])->toBe($assessment->total_amount_cents);

    app(RecordAssessmentDecision::class)->handle($assessment, $treasurer, AssessmentDecisionAction::Approved);
    $approvedDocument = app(BuildExecutablePermitApplicationDocument::class)->handle($application->fresh(), $treasurer);
    expect(data_get($approvedDocument, 'page_2_assessment.processing_summary'))
        ->toBe('Treasurer approved · ₱'.number_format($currentTotal / 100, 2));
    $paymentSchedule = app(CreatePaymentScheduleForAssessment::class)->handle($assessment->fresh(), $treasurer);
    $collection = app(RecordPaymentScheduleCollection::class)->handle($paymentSchedule, [
        'amount_cents' => $paymentSchedule->total_amount_cents,
        'method' => 'cash',
        'payer_name' => 'Nelson Cleanroom Applicant',
    ], $cashier);
    $paidDocument = app(BuildExecutablePermitApplicationDocument::class)->handle($application->fresh(), $treasurer);
    expect(data_get($paidDocument, 'page_2_assessment.processing_summary'))
        ->toBe('Payment complete · ₱'.number_format($currentTotal / 100, 2));
    $groups = $collection->allocations->pluck('receipt_group_key')->unique()->sort()->values();
    expect($groups)->toHaveCount(5);

    foreach ($groups as $index => $group) {
        app(IssueManualCollectionReceipt::class)->handle($collection->fresh(), [
            'receipt_group_key' => $group,
            'receipt_number' => str_pad((string) (7000001 + $index), 7, '0', STR_PAD_LEFT),
            'series' => (string) now()->year,
            'numbering_authority' => 'synthetic_nelson_cleanroom',
        ], $cashier);
        expect($collection->fresh()->status)->toBe($index === 4 ? TreasuryCollectionStatus::Receipted : TreasuryCollectionStatus::PendingReceipt);
    }

    $certifications = app(CommissionPostPaymentOfficeCertifications::class)->handle($application->fresh());
    foreach ($certifications as $certification) {
        app(RecordPostPaymentOfficeCertification::class)->handle(
            $certification,
            User::query()->findOrFail(data_get($run->actor_manifest, 'actors.'.$certification->office_code.'.user_id')),
        );
    }
    expect(app(ProjectPermitReadiness::class)->handle($application->fresh())['ready'])->toBeTrue();
    $issued = app(IssueSyntheticLifecyclePermit::class)->handle($application->fresh(), $permitIssuer);
    app(ReleaseSyntheticLifecyclePermit::class)->handle($application->fresh(), $releasingOfficer);

    $applicationData = app(ApplicationDataResolver::class)->resolve($application->fresh(), $cashier)->toArray();
    expect($applicationData['official_receipts'])->toHaveCount(5)
        ->and(collect($applicationData['official_receipts'])->sum('total_amount_minor'))->toBe($collection->amount_cents)
        ->and($applicationData['permit']['official_receipts'])->toHaveCount(5)
        ->and($applicationData['permit']['official_receipt_bound'])->toBeTrue()
        ->and($applicationData['schedule_of_payment']['grand_total_minor'])->toBe($assessment->total_amount_cents)
        ->and($applicationData['applicant_documents'])->toHaveCount(2)
        ->and($applicationData['permit']['issued'])->toBeTrue()
        ->and($applicationData['permit']['released'])->toBeTrue()
        ->and(json_encode($applicationData))->not->toContain('Illuminate\\Database\\Eloquent');

    $permitPdf = app(RenderPermitPdf::class)->handle($application->fresh());
    expect($permitPdf)
        ->toContain('GENERAL MERCHANDISE / RETAIL SALE OF LIQUOR / COFFEE SHOP')
        ->not->toContain('NELSON-LOB-');
    foreach (range(7000001, 7000005) as $receiptNumber) {
        expect($permitPdf)->toContain((string) $receiptNumber);
    }
    $this->get($applicationData['permit']['verification']['url'])
        ->assertSuccessful()
        ->assertJsonPath('permit.permit_number', $issued->permit_number)
        ->assertJsonPath('permit.lines_of_business', [
            'General Merchandise',
            'Retail Sale of Liquor',
            'Coffee Shop',
        ])
        ->assertJsonMissingPath('permit.official_receipts');
});
