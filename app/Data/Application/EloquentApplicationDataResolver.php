<?php

namespace App\Data\Application;

use App\Actions\BuildMunicipalScheduleOfFees;
use App\Actions\DescribePermitReleaseReadiness;
use App\Actions\DescribePermitVerificationBoundary;
use App\Actions\ProjectPermitReadiness;
use App\Actions\ResolveOfficialReceiptProfile;
use App\Assessment\Price\HistoricalPriceReport;
use App\Enums\ReceiptStatus;
use App\Enums\UserPermission;
use App\Evaluation\BusinessPermitEvaluationResolver;
use App\Integrations\QrPhPaymentArtifactCache;
use App\Models\Assessment;
use App\Models\BusinessPermitEvaluation;
use App\Models\LifecycleCleanroomRun;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDocument;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class EloquentApplicationDataResolver implements ApplicationDataResolver
{
    private const array PermitConditions = [
        'This permit is non-transferable.',
        'This permit shall be conspicuously displayed at the place of business.',
        'The business shall comply with all municipal ordinances, national laws, and regulations.',
        'Failure to comply with the above conditions shall be sufficient cause for revocation of this permit.',
    ];

    public function __construct(
        private readonly BusinessPermitEvaluationResolver $evaluationResolver,
        private readonly HistoricalPriceReport $historicalPriceReport,
        private readonly DescribePermitReleaseReadiness $releaseReadiness,
        private readonly ProjectPermitReadiness $permitReadiness,
        private readonly DescribePermitVerificationBoundary $verificationBoundary,
        private readonly QrPhPaymentArtifactCache $qrPhArtifactCache,
        private readonly ResolveOfficialReceiptProfile $resolveOfficialReceiptProfile,
        private readonly BuildMunicipalScheduleOfFees $buildScheduleOfFees,
    ) {}

    public function resolve(PermitApplication $permitApplication, ?User $viewer = null): ApplicationData
    {
        $application = PermitApplication::query()->with([
            'declaration',
            'business.owner',
            'submittedBy.role.permissions',
            'lines.lineOfBusiness',
            'documents',
            'clearances.completedBy',
            'postPaymentOfficeCertifications.certifiedBy',
            'postPaymentOfficeCertifications.receipt',
            'assessments' => fn ($query) => $query->whereNull('superseded_at')->latest('sequence'),
            'assessments.lines.lineOfBusiness',
            'assessments.decision.decidedBy',
            'assessments.treasuryCounterCheck.checkedBy',
            'paymentSchedules.lines',
            'paymentSchedules.treasuryCollections.allocations.paymentScheduleLine',
            'paymentSchedules.treasuryCollections.assessment',
            'paymentSchedules.treasuryCollections.receipt.issuedBy',
            'paymentSchedules.xChangePayment.attempts',
            'paymentSchedules.xChangePayment.treasuryCollection.receipt',
            'bploRoutingDetermination.determinedBy',
            'bploRoutingDetermination.works.lineOfBusiness',
            'bploRoutingDetermination.works.paymentOrders.lines',
            'businessPermitEvaluation.currentVersion.counterCheck',
            'businessPermitEvaluation.items.revisions.version',
            'businessPermitEvaluation.items.revisions.actor',
            'provisionalUatPermitCompletion.issuedBy',
            'provisionalUatPermitCompletion.releasedBy',
        ])->findOrFail($permitApplication->id);

        $assessment = $application->assessments->first();
        $evaluationProjection = $this->evaluationProjection($application, $assessment);
        $offices = $this->offices($application, $evaluationProjection);
        $receipts = array_values($application->paymentSchedules
            ->flatMap(fn ($schedule) => $schedule->treasuryCollections)
            ->map(fn (TreasuryCollection $collection): ?OfficialReceiptData => $this->officialReceipt($collection, $viewer))
            ->filter()
            ->values()
            ->all());
        $permit = $this->permit($application, $receipts);
        $scheduleOfFees = $this->scheduleOfFees($application);
        $attachments = $this->attachments($application, $assessment, $receipts, $permit);
        $tasks = $this->tasks($application, $viewer, $evaluationProjection);
        $affordances = $this->affordances($application, $viewer, $tasks);
        $workNotes = $this->workNotes($application, $evaluationProjection, $offices, $tasks, $receipts, $permit);
        $declaration = $application->declaration;

        return new ApplicationData(
            schema_version: ApplicationData::Schema,
            identity: new ApplicationIdentityData(
                application_id: $application->id,
                application_number: $application->application_number,
                tracking_reference: $application->tracking_reference,
                application_year: $application->application_year,
                type: $application->type->value,
                status: $application->status->value,
            ),
            applicant: [
                'business_owner_id' => $application->business->owner->id,
                'name' => $application->business->owner->name,
                'email' => $application->business->owner->email,
                'phone' => $application->business->owner->phone,
                'address' => $application->business->owner->address,
                'submitted_by' => $application->submittedBy === null ? null : [
                    'id' => $application->submittedBy->id,
                    'name' => $application->submittedBy->name,
                ],
            ],
            business: [
                'id' => $application->business->id,
                'name' => $application->business->name,
                'trade_name' => $application->business->trade_name,
                'registration_number' => $application->business->registration_number,
                'address' => $application->business->address,
                'barangay' => $application->business->barangay,
                'ownership_type' => $application->business->ownership_type,
                'lines_of_business' => $application->lines->map(function ($line): array {
                    return [
                        'application_line_id' => $line->id,
                        'line_of_business_id' => $line->line_of_business_id,
                        'code' => $line->line_of_business_id === null
                            ? data_get($line->metadata, 'line_of_business_code')
                            : $line->lineOfBusiness->code,
                        'name' => $line->line_of_business_id === null
                            ? data_get($line->metadata, 'line_of_business_name', 'Unresolved line of business')
                            : $line->lineOfBusiness->name,
                    ];
                })->values()->all(),
            ],
            declaration: new ApplicationDeclarationData(
                page: 'page_1',
                semantics: 'Frozen applicant declaration',
                state: $declaration === null ? 'draft' : 'frozen',
                declared_at: $declaration?->declared_at?->toIso8601String(),
                snapshot_hash: $declaration?->snapshot_hash,
                snapshot: $declaration === null ? data_get($application->metadata, 'applicant_declaration_draft') : $declaration->snapshot,
            ),
            routing: [
                'page' => 'page_2',
                'semantics' => 'Living municipal processing projection; canonical Actions remain authoritative',
                'status' => $application->bploRoutingDetermination === null ? 'pending' : 'determined',
                'determination_id' => $application->bploRoutingDetermination?->id,
                'determined_at' => $application->bploRoutingDetermination?->determined_at?->toIso8601String(),
                'determined_by' => $application->bploRoutingDetermination?->determinedBy?->name,
                'reason' => $application->bploRoutingDetermination?->situational_context,
                'works' => $application->bploRoutingDetermination?->works->map(fn ($work): array => [
                    'id' => $work->id,
                    'office_code' => $work->office_code,
                    'office_label' => $work->office_label,
                    'line_of_business_name' => $work->lineOfBusiness?->name,
                    'situational_reason' => $work->situational_reason,
                    'required_work' => $work->required_work,
                ])->values()->all() ?? [],
            ],
            offices: $offices,
            financial: $this->financial($assessment, $evaluationProjection),
            payment: $this->payment($application),
            official_receipts: $receipts,
            post_payment: [
                'state' => $application->postPaymentOfficeCertifications->isEmpty()
                    ? 'pending_official_receipt'
                    : ($application->postPaymentOfficeCertifications->every(fn ($certification): bool => $certification->status === 'completed') ? 'completed' : 'in_progress'),
                'certifications' => array_values($application->postPaymentOfficeCertifications->map(fn ($certification): array => [
                    'id' => $certification->id,
                    'code' => $certification->office_code,
                    'label' => $certification->office_label,
                    'routing_determination_id' => $certification->bplo_routing_determination_id,
                    'routing_work_ids' => $certification->routing_work_ids,
                    'receipt_id' => $certification->receipt_id,
                    'receipt_number' => $certification->receipt->receipt_number,
                    'receipt_reviewed' => (bool) data_get($certification->evidence, 'result.reviewed', false),
                    'status' => $certification->status,
                    'result' => $certification->result,
                    'remarks' => $certification->remarks,
                    'completed_at' => $certification->certified_at?->toIso8601String(),
                    'completed_by' => $certification->certifiedBy?->name,
                    'semantic_classification' => $certification->semantic_classification,
                    'production_authority' => $certification->production_authority,
                ])->values()->all()),
                'readiness' => $this->permitReadiness->handle($application),
            ],
            permit: $permit,
            documents: array_values($application->documents->map(fn (PermitApplicationDocument $document): array => [
                'id' => $document->id,
                'label' => $document->label,
                'original_name' => $document->original_name,
                'mime_type' => $document->mime_type,
                'size_bytes' => $document->size_bytes,
                'uploaded_at' => $document->uploaded_at->toIso8601String(),
            ])->values()->all()),
            schedule_of_fees: $scheduleOfFees,
            attachments: $attachments,
            actor_context: new ActorContextData(
                actor_id: $viewer?->id,
                actor_label: $viewer === null ? 'Laboratory observer' : $viewer->name,
                role_code: $viewer?->role?->code,
                current_tasks: $tasks,
                available_affordances: $affordances,
                work_notes: $workNotes,
            ),
            tabs: [
                ['key' => 'application', 'label' => 'Application'],
                ['key' => 'processing', 'label' => 'Processing'],
                ['key' => 'assessment', 'label' => 'Assessment'],
                ['key' => 'payment', 'label' => 'Payment'],
                ['key' => 'permit', 'label' => 'Permit'],
            ],
        );
    }

    private function scheduleOfFees(PermitApplication $application): MunicipalScheduleOfFeesData
    {
        $asOf = Carbon::create($application->application_year, 1, 1)->startOfDay();
        $schedule = $this->buildScheduleOfFees->handle(asOf: $asOf);

        return new MunicipalScheduleOfFeesData(
            schema_version: MunicipalScheduleOfFeesData::Schema,
            title: (string) data_get($schedule, 'title'),
            scope: (string) data_get($schedule, 'scope'),
            as_of_date: (string) data_get($schedule, 'as_of_date'),
            application_year: (int) data_get($schedule, 'application_year'),
            currency: (string) data_get($schedule, 'currency'),
            categories: array_values(data_get($schedule, 'categories', [])),
        );
    }

    /**
     * @param  list<OfficialReceiptData>  $receipts
     * @return list<ApplicationAttachmentData>
     */
    private function attachments(
        PermitApplication $application,
        ?Assessment $assessment,
        array $receipts,
        BusinessPermitData $permit,
    ): array {
        $paymentOrderCount = $application->bploRoutingDetermination?->works
            ->sum(fn ($work): int => $work->paymentOrders->count()) ?? 0;
        $schedule = $application->paymentSchedules->sortByDesc('sequence')->first();
        $paymentRequest = $schedule?->xChangePayment;
        $requiredCertificationCount = $application->bploRoutingDetermination?->works->pluck('office_code')->unique()->count() ?? 0;
        $certifiedCertificationCount = $application->postPaymentOfficeCertifications
            ->where('status', 'completed')
            ->where('result', 'certified')
            ->pluck('office_code')
            ->unique()
            ->count();
        $permitAttachmentState = $permit->released
            ? 'released_synthetic'
            : ($permit->issued
                ? 'issued_synthetic'
                : ($permit->ready
                    ? 'ready'
                    : ($requiredCertificationCount > 0
                        ? $certifiedCertificationCount.'_of_'.$requiredCertificationCount.'_certified'
                        : 'pending')));

        return [
            $this->attachment('schedule_of_fees', 1, 'Municipal Schedule of Fees', 'Schedule of Fees', 'price_list', 'assessment', 'schedule_of_fees', 'attached', true, 'amber'),
            $this->attachment('payment_orders', 2, 'Office Payment Orders', 'Payment Orders', 'office_evidence', 'processing', 'payment_orders', $paymentOrderCount > 0 ? 'attached' : 'pending', $paymentOrderCount > 0, 'sky'),
            $this->attachment('assessment', 3, 'Computation / Assessment Slip', 'Assessment', 'frozen_financial_artifact', 'assessment', 'assessment', $assessment instanceof Assessment ? 'frozen' : 'pending', $assessment instanceof Assessment, 'violet'),
            $this->attachment('qr_ph', 4, 'QR Ph Payment Slip', 'QR Ph', 'payment_instrument', 'payment', 'payment', filled($paymentRequest?->pay_code) ? 'generated' : 'pending', filled($paymentRequest?->pay_code), 'emerald'),
            $this->attachment('official_receipt', 5, 'Official Receipt · AF No. 51', 'Official Receipt', 'accountable_form', 'payment', 'payment', $receipts === [] ? 'pending' : 'issued', $receipts !== [], 'rose'),
            $this->attachment('permit', 6, 'Business Permit', 'Permit', 'final_authority_artifact', 'permit', 'permit', $permitAttachmentState, $receipts !== [] || $permit->issued, 'stone'),
        ];
    }

    private function attachment(
        string $key,
        int $sequence,
        string $label,
        string $shortLabel,
        string $documentKind,
        string $section,
        string $target,
        string $state,
        bool $available,
        string $tone,
    ): ApplicationAttachmentData {
        return new ApplicationAttachmentData(
            schema_version: ApplicationAttachmentData::Schema,
            key: $key,
            sequence: $sequence,
            label: $label,
            short_label: $shortLabel,
            document_kind: $documentKind,
            section: $section,
            target: $target,
            state: $state,
            available: $available,
            tone: $tone,
        );
    }

    /** @return array<string, mixed>|null */
    private function evaluationProjection(PermitApplication $application, ?Assessment $assessment): ?array
    {
        if ($assessment instanceof Assessment) {
            return null;
        }

        $evaluation = $application->businessPermitEvaluation;

        return $evaluation instanceof BusinessPermitEvaluation && $evaluation->currentVersion !== null
            ? $this->evaluationResolver->resolve($evaluation)
            : null;
    }

    /**
     * @param  array<string, mixed>|null  $projection
     * @return list<ApplicationOfficeData>
     */
    private function offices(PermitApplication $application, ?array $projection): array
    {
        if ($application->bploRoutingDetermination === null) {
            return [];
        }

        $itemPayloads = is_array($projection['items'] ?? null)
            ? $projection['items']
            : $this->frozenEvaluationItems($application);
        $items = collect($itemPayloads);

        return array_values($application->bploRoutingDetermination->works
            ->groupBy('office_code')
            ->map(function (Collection $works, string $officeCode) use ($items, $application): ApplicationOfficeData {
                $workIds = $works->pluck('id');
                $officeItems = $items->filter(fn (array $item): bool => $workIds->contains(data_get($item, 'metadata.bplo_routing_work_id')))->values();
                $orders = $works->flatMap(fn ($work) => $work->paymentOrders)
                    ->filter(fn ($order): bool => $order->status === 'issued' && $order->superseded_at === null)
                    ->values();
                $resolved = $officeItems->where('resolution', 'resolved');
                $allResolved = $officeItems->isNotEmpty() && $resolved->count() === $officeItems->count();
                $latest = $resolved->sortByDesc('occurred_at')->first();
                $postPaymentCertification = $application->postPaymentOfficeCertifications->firstWhere('office_code', $officeCode);

                return new ApplicationOfficeData(
                    code: $officeCode,
                    label: match ($officeCode) {
                        'assessor' => 'Municipal Assessor',
                        'menro' => 'MENRO',
                        default => $works->first()->office_label,
                    },
                    status: $allResolved ? 'certified' : ($resolved->isNotEmpty() ? 'in_progress' : 'awaiting_determination'),
                    responsibilities: array_values($officeItems->map(fn (array $item): array => [
                        'id' => $item['id'],
                        'label' => data_get($item, 'metadata.label', str($item['key'])->headline()->toString()),
                        'responsible_party' => $item['responsible_party'],
                        'resolution' => $item['resolution'],
                        'applicability' => $item['applicability'],
                        'amount_cents' => data_get($item, 'value.amount_cents') ?? data_get($item, 'default_value.amount_cents'),
                    ])->all()),
                    payment_orders: array_values($orders->map(fn ($order): array => [
                        'id' => $order->id,
                        'sequence' => $order->sequence,
                        'status' => $order->status,
                        'issued_at' => $order->issued_at->toIso8601String(),
                        'total_amount_cents' => $order->total_amount_cents,
                        'lines' => array_values($order->lines->map(fn ($line): array => [
                            'id' => $line->id,
                            'code' => $line->code,
                            'name' => $line->name,
                            'amount_cents' => $line->amount_cents,
                        ])->all()),
                    ])->all()),
                    paperless_payment_order_count: $orders->count(),
                    total_amount_cents: (int) $orders->sum('total_amount_cents'),
                    certification: $allResolved && is_array($latest) ? [
                        'statement' => 'Electronically certified',
                        'officer_name' => $latest['actor_name'],
                        'certified_at' => $latest['occurred_at'],
                    ] : null,
                    post_payment_certification: $postPaymentCertification === null ? null : [
                        'id' => $postPaymentCertification->id,
                        'status' => $postPaymentCertification->status,
                        'result' => $postPaymentCertification->result,
                        'receipt_number' => $postPaymentCertification->receipt->receipt_number,
                        'receipt_reviewed' => (bool) data_get($postPaymentCertification->evidence, 'result.reviewed', false),
                        'remarks' => $postPaymentCertification->remarks,
                        'certified_by' => $postPaymentCertification->certifiedBy?->name,
                        'certified_at' => $postPaymentCertification->certified_at?->toIso8601String(),
                        'semantic_classification' => $postPaymentCertification->semantic_classification,
                        'production_authority' => $postPaymentCertification->production_authority,
                    ],
                );
            })
            ->values()
            ->all());
    }

    /** @return list<array<string, mixed>> */
    private function frozenEvaluationItems(PermitApplication $application): array
    {
        $evaluation = $application->businessPermitEvaluation;
        $version = $evaluation?->currentVersion;
        if (! $evaluation instanceof BusinessPermitEvaluation || $version === null) {
            return [];
        }

        return array_values($evaluation->items->map(function ($item) use ($version): array {
            $revisions = $item->revisions
                ->filter(fn ($revision): bool => $revision->version->sequence <= $version->sequence)
                ->sortBy(fn ($revision): int => $revision->version->sequence)
                ->values();
            $revision = $revisions->last();
            $proposal = $revisions->first(fn ($candidate): bool => $candidate->action->value === 'proposal');

            return [
                'id' => $item->id,
                'key' => $item->key,
                'responsible_party' => $item->responsible_party,
                'is_required' => $item->is_required,
                'metadata' => $item->metadata,
                'resolution' => $revision !== null && $revision->applicability->value !== 'undetermined'
                    && (! $item->requires_confirmation || in_array($revision->action->value, ['confirmation', 'correction', 'authorized_determination'], true))
                        ? 'resolved'
                        : 'unresolved',
                'applicability' => $revision?->applicability->value ?? 'undetermined',
                'value' => $revision?->value,
                'default_value' => $proposal?->value,
                'actor_name' => $revision?->actor?->name,
                'occurred_at' => $revision?->occurred_at?->toIso8601String(),
            ];
        })->values()->all());
    }

    /**
     * @param  array<string, mixed>|null  $projection
     * @return array<string, mixed>
     */
    private function financial(?Assessment $assessment, ?array $projection): array
    {
        if ($assessment instanceof Assessment) {
            return [
                'state' => 'frozen_assessment',
                'evaluation' => null,
                'price_report' => is_array($assessment->price_report_snapshot)
                    ? $this->historicalPriceReport->forAssessment($assessment)
                    : $this->legacyAssessmentLinesReport($assessment),
                'assessment' => [
                    'id' => $assessment->id,
                    'sequence' => $assessment->sequence,
                    'status' => $assessment->status->value,
                    'total_amount_cents' => $assessment->total_amount_cents,
                    'currency' => $assessment->currency,
                    'assessed_at' => $assessment->assessed_at?->toIso8601String(),
                    'price_report_fingerprint' => $assessment->price_report_fingerprint,
                ],
                'treasurer_decision' => $assessment->decision === null ? null : [
                    'action' => $assessment->decision->action->value,
                    'decided_at' => $assessment->decision->decided_at->toIso8601String(),
                    'assessment_snapshot_hash' => $assessment->decision->assessment_snapshot_hash,
                ],
                'treasury_counter_check' => $assessment->treasuryCounterCheck === null ? null : [
                    'result' => $assessment->treasuryCounterCheck->result?->value,
                    'checked_at' => $assessment->treasuryCounterCheck->checked_at->toIso8601String(),
                    'statement' => $assessment->treasuryCounterCheck->result?->value === 'no_correction'
                        ? 'Counter-check completed - no correction'
                        : 'Counter-check recorded against this Assessment',
                ],
            ];
        }

        return [
            'state' => is_array($projection) ? 'emerging_evaluation' : 'pending',
            'evaluation' => $projection === null ? null : [
                'id' => $projection['evaluation_id'],
                'version' => $projection['version_sequence'],
                'fingerprint' => $projection['current_fingerprint'],
                'working_paper' => $projection['financial_working_paper'],
            ],
            'price_report' => null,
            'assessment' => null,
            'treasurer_decision' => null,
            'treasury_counter_check' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function legacyAssessmentLinesReport(Assessment $assessment): array
    {
        $components = $assessment->lines->map(fn ($line): array => [
            'exact_once_key' => "legacy_assessment_line:{$line->id}",
            'label' => $line->name,
            'scheduled_minor' => $line->amount_cents,
            'resolved_minor' => $line->amount_cents,
            'source' => [
                'kind' => 'immutable_assessment_line',
                'assessment_line_id' => $line->id,
                'rule_snapshot' => $line->rule_snapshot,
            ],
        ])->values()->all();

        return [
            'schema_version' => 'bpls.price-report.legacy-assessment-lines.v1',
            'currency' => $assessment->currency,
            'groups' => [],
            'components' => $components,
            'modifiers' => [],
            'taxes' => [],
            'total' => [
                'currency' => $assessment->currency,
                'minor' => $assessment->total_amount_cents,
            ],
            'explanation' => array_map(fn (array $component): array => [
                'exact_once_key' => $component['exact_once_key'],
                'label' => $component['label'],
                'scheduled_minor' => $component['scheduled_minor'],
                'resolved_minor' => $component['resolved_minor'],
            ], $components),
            'provenance' => array_column($components, 'source'),
            'historical_reconstruction' => 'immutable_assessment_lines',
        ];
    }

    /** @return array<string, mixed> */
    private function payment(PermitApplication $application): array
    {
        $schedule = $application->paymentSchedules->sortByDesc('sequence')->first();
        $onlinePayment = $schedule?->xChangePayment;
        $attempt = $onlinePayment?->attempts->sortByDesc('id')->first();
        $canonicalCollection = $onlinePayment?->treasuryCollection;

        return [
            'state' => $schedule === null ? 'pending_assessment_approval' : $schedule->status->value,
            'payable' => $schedule === null ? null : [
                'payment_schedule_id' => $schedule->id,
                'status' => $schedule->status->value,
                'payment_mode' => $schedule->payment_mode,
                'total_amount_cents' => $schedule->total_amount_cents,
                'paid_amount_cents' => $schedule->paid_amount_cents,
                'balance_amount_cents' => $schedule->total_amount_cents - $schedule->paid_amount_cents,
                'due_on' => $schedule->due_on?->toDateString(),
            ],
            'payment_request' => $onlinePayment === null ? null : [
                'state' => $canonicalCollection instanceof TreasuryCollection ? 'collected' : $onlinePayment->status,
                'pay_code' => $onlinePayment->pay_code,
                'external_reference' => $onlinePayment->external_reference,
                'currency' => $onlinePayment->currency,
                'target_amount_cents' => $onlinePayment->target_amount_cents ?? $onlinePayment->amount_cents,
                'collected_total_cents' => $canonicalCollection instanceof TreasuryCollection
                    ? $canonicalCollection->amount_cents
                    : $onlinePayment->collected_total_cents,
                'consumer_status' => $onlinePayment->consumer_status,
                'provider_status' => $onlinePayment->provider_status,
                'confirmed_at' => $canonicalCollection?->received_at?->toIso8601String(),
                'collection_id' => $canonicalCollection?->id,
                'collection_reference' => $canonicalCollection?->reference_number,
                'official_receipt_id' => $canonicalCollection?->receipt?->id,
                'active_attempt' => $attempt === null ? null : [
                    'reference' => $attempt->reference,
                    'status' => $attempt->status,
                    'provider' => $attempt->provider,
                    'amount_cents' => $attempt->amount_cents,
                    'expires_at' => $attempt->expires_at?->toIso8601String(),
                    'qr_data_url' => $this->qrPhArtifactCache->dataUrl($attempt),
                ],
            ],
            'collections' => $application->paymentSchedules->flatMap(fn ($candidate) => $candidate->treasuryCollections)->map(fn ($collection): array => [
                'id' => $collection->id,
                'status' => $collection->status->value,
                'method' => $collection->method->value,
                'channel' => $collection->channel->value,
                'amount_cents' => $collection->amount_cents,
                'payer_name' => $collection->payer_name,
                'reference_number' => $collection->reference_number,
                'received_at' => $collection->received_at->toIso8601String(),
                'receipt_id' => $collection->receipt?->id,
            ])->values()->all(),
        ];
    }

    /**
     * @param  list<array{key: string, label: string, section: string, href: ?string}>  $tasks
     * @return list<array{key: string, label: string, section: string, href: ?string}>
     */
    private function affordances(PermitApplication $application, ?User $viewer, array $tasks): array
    {
        if ($viewer === null) {
            return $tasks;
        }

        $schedule = $application->paymentSchedules->sortByDesc('sequence')->first();
        if ($schedule?->xChangePayment === null) {
            return $tasks;
        }

        if ($viewer->hasPermission(UserPermission::ViewPaymentSchedules)) {
            $tasks[] = $this->task(
                'check_payment_status',
                'Check QR Ph payment status',
                'payment',
                route('staff.payment-schedules.qr-ph.status', $schedule, false),
            );
        } elseif ($viewer->hasPermission(UserPermission::ViewOwnPermitApplicationFinancials)
            && $application->submitted_by_id === $viewer->id) {
            $tasks[] = $this->task(
                'check_payment_status',
                'Check QR Ph payment status',
                'payment',
                route('citizen.payment-schedules.qr-ph.status', $schedule, false),
            );
        }

        return $tasks;
    }

    private function officialReceipt(TreasuryCollection $collection, ?User $viewer): ?OfficialReceiptData
    {
        $receipt = $collection->receipt;
        if (! $receipt instanceof Receipt || $receipt->status !== ReceiptStatus::Issued) {
            return null;
        }
        $sourceAssessment = $collection->getRelation('assessment');
        $profile = data_get($receipt->source_snapshot, 'official_receipt_profile');
        $profile = is_array($profile) && filled($profile)
            ? $profile
            : $this->resolveOfficialReceiptProfile->handle();
        $canOpenReceipt = $viewer?->can(UserPermission::ViewReceipts->value) ?? false;

        return new OfficialReceiptData(
            schema_version: OfficialReceiptData::Schema,
            accountable_form_number: (int) data_get($receipt->source_snapshot, 'official_receipt_profile.form.accountable_form_number', 51),
            form_revision: (string) data_get($receipt->source_snapshot, 'official_receipt_profile.form.revision', 'Revised June 2008'),
            copy_designation: (string) data_get($receipt->source_snapshot, 'af51.copy_designation', 'ORIGINAL'),
            receipt_number: $receipt->receipt_number,
            series: data_get($receipt->source_snapshot, 'af51.series'),
            numbering_authority: $receipt->numbering_authority,
            synthetic_number: str_contains(strtolower($receipt->numbering_authority), 'synthetic'),
            issued_on: $receipt->issued_at->toDateString(),
            agency: data_get($receipt->source_snapshot, 'af51.agency'),
            fund: data_get($receipt->source_snapshot, 'af51.fund'),
            payor: $collection->payer_name,
            collection_rows: array_values($collection->allocations->map(fn ($allocation): array => [
                'nature_of_collection' => (string) $allocation->paymentScheduleLine->name,
                'account_code' => is_string(data_get($allocation->source_snapshot, 'account_code'))
                    ? data_get($allocation->source_snapshot, 'account_code')
                    : (is_string(data_get($allocation->source_snapshot, 'code')) ? data_get($allocation->source_snapshot, 'code') : null),
                'currency' => 'PHP',
                'amount_minor' => (int) $allocation->amount_cents,
            ])->values()->all()),
            currency: 'PHP',
            total_amount_minor: $receipt->amount_cents,
            amount_in_words: data_get($receipt->source_snapshot, 'af51.amount_in_words'),
            payment_instrument: [
                'type' => $collection->method->value,
                'drawee_bank' => data_get($collection->source_snapshot, 'payment_instrument.drawee_bank'),
                'number' => $collection->reference_number,
                'date' => data_get($collection->source_snapshot, 'payment_instrument.date'),
            ],
            collecting_officer: data_get($receipt->source_snapshot, 'issuer.printed_name') ?? $receipt->issuedBy?->getAttribute('name'),
            presentation_profile: $profile,
            links: [
                'view' => $canOpenReceipt ? route('staff.receipts.show', $receipt, false) : null,
                'pdf' => $canOpenReceipt ? route('staff.receipts.pdf', $receipt, false) : null,
            ],
            source: [
                'receipt_id' => $receipt->id,
                'treasury_collection_id' => $collection->id,
                'payment_schedule_id' => $collection->payment_schedule_id,
                'assessment_id' => $collection->assessment_id,
                'assessment_price_report_fingerprint' => $sourceAssessment instanceof Assessment
                    ? $sourceAssessment->price_report_fingerprint
                    : null,
            ],
        );
    }

    /** @param list<OfficialReceiptData> $receipts */
    private function permit(PermitApplication $application, array $receipts): BusinessPermitData
    {
        $verification = $this->verificationBoundary->handle($application);
        $syntheticLifecycle = data_get($application->metadata, 'lifecycle_cleanroom.semantic_classification') === 'synthetic_only';
        $readiness = $this->permitReadiness->handle($application);
        $legacyReadiness = $this->releaseReadiness->handle($application);
        $receipt = count($receipts) === 1 ? $receipts[0] : null;
        $receiptBound = $receipt instanceof OfficialReceiptData && filled($receipt->receipt_number);
        $ready = ($syntheticLifecycle ? $readiness['ready'] : $legacyReadiness['ready_for_authority_review']) && $receiptBound;
        $completion = $application->provisionalUatPermitCompletion;
        $issued = $completion?->issued_at !== null;
        $released = $completion?->released_at !== null;

        return new BusinessPermitData(
            schema_version: BusinessPermitData::Schema,
            state: $released ? 'released_synthetic' : ($issued ? 'issued_synthetic' : ($ready ? 'ready' : 'blocked')),
            ready: $ready,
            issued: $issued,
            released: $released,
            valid: false,
            semantic_classification: $completion === null
                ? ($syntheticLifecycle ? $readiness['semantic_classification'] : 'production_pending')
                : $completion->semantic_classification,
            production_authority: false,
            permit_number: $completion?->permit_number,
            issued_on: $completion?->issued_at?->toDateString(),
            valid_until: $completion?->valid_until?->toDateString(),
            business_name: $application->business->name,
            owner_operator: $application->business->owner->name,
            business_address: $application->business->address,
            lines_of_business: array_values($application->lines->map(function ($line): string {
                return $line->line_of_business_id === null
                    ? (string) data_get($line->metadata, 'line_of_business_name', 'Unresolved line of business')
                    : $line->lineOfBusiness->name;
            })->values()->all()),
            conditions: self::PermitConditions,
            issuing_authority: [
                'office' => 'Municipal Mayor',
                'name' => null,
                'authority_status' => $issued ? 'synthetic_only' : ($syntheticLifecycle ? 'production_pending' : 'unresolved'),
                'signature_reference' => $completion?->synthetic_signature_reference,
                'real_mayor_login_or_signature_used' => false,
                'production_authority' => false,
            ],
            official_receipt_number: $receipt?->receipt_number,
            official_receipt_series: $receipt?->series,
            official_receipt_bound: $receiptBound,
            verification: [
                'reference' => $verification['reference'],
                'status' => $verification['status'],
                'url' => $verification['url'],
                'view_url' => $verification['view_url'],
            ],
            printable_artifact_url: ! $syntheticLifecycle || $issued
                ? route('staff.permit-applications.permit.pdf', $application, false)
                : null,
            statement: $released
                ? 'Released synthetic cleanroom specimen. The bound QR proves this exact Permit identity only; production authority and legal validity remain false.'
                : ($issued
                    ? 'Issued synthetic cleanroom specimen awaiting separate BPLO release. Production authority and legal validity remain false.'
                    : ($receiptBound
                        ? 'Official Receipt identity is bound. Every routing-derived post-payment certification must pass before synthetic issuance.'
                        : 'Permit cannot be issued or released without a canonical Official Receipt number bound to it.')),
            blockers: array_values(array_unique([
                ...($syntheticLifecycle ? $readiness['blocked_by'] : $legacyReadiness['blocked_by']),
                ...($receiptBound ? [] : ['official_receipt_number_binding']),
                ...(count($receipts) > 1 ? ['official_receipt_binding_selection_policy'] : []),
            ])),
        );
    }

    /**
     * @param  array<string, mixed>|null  $projection
     * @param  list<ApplicationOfficeData>  $offices
     * @param  list<array{key: string, label: string, section: string, href: ?string}>  $tasks
     * @param  list<OfficialReceiptData>  $receipts
     * @return list<ApplicationWorkNoteData>
     */
    private function workNotes(
        PermitApplication $application,
        ?array $projection,
        array $offices,
        array $tasks,
        array $receipts,
        BusinessPermitData $permit,
    ): array {
        $notes = [];
        $taskIndex = collect($tasks)->keyBy('key');
        $submitted = $application->submitted_at !== null;
        $routing = $application->bploRoutingDetermination;
        $assessment = $application->assessments->first();
        $schedule = $application->paymentSchedules->sortByDesc('sequence')->first();
        $officeIndex = collect($offices)->keyBy(fn (ApplicationOfficeData $office): string => $office->code);

        $notes[] = $this->workNote(
            id: 'applicant_submission',
            actorKey: 'citizen',
            actorLabel: 'Applicant',
            instruction: 'Submit Business Permit Application',
            section: 'application',
            anchor: 'applicant_declaration',
            state: $submitted ? 'completed' : 'ready',
            stateLabel: $submitted ? 'Completed' : 'Ready',
            tone: 'cream',
            affordance: $taskIndex->get('submit_application'),
            completedAt: $application->submitted_at?->toIso8601String(),
        );
        $notes[] = $this->workNote(
            id: 'bplo_routing',
            actorKey: 'intake',
            actorLabel: 'BPLO',
            instruction: 'Record concerned-office routing',
            section: 'processing',
            anchor: 'bplo_routing',
            state: $routing !== null ? 'completed' : ($submitted ? 'ready' : 'waiting'),
            stateLabel: $routing !== null ? 'Completed' : ($submitted ? 'Ready' : 'Awaiting application submission'),
            tone: 'yellow',
            affordance: $taskIndex->get('determine_routing'),
            completedAt: $routing?->determined_at?->toIso8601String(),
        );

        $officeDefinitions = $this->officeNoteDefinitions($application, $offices);
        foreach ($officeDefinitions as $definition) {
            $office = $officeIndex->get($definition['key']);
            $officeState = match ($office?->status) {
                'certified' => 'completed',
                'in_progress' => 'in_progress',
                'awaiting_determination' => 'ready',
                default => $routing === null ? 'anticipated' : 'waiting',
            };
            $officeStateLabel = match ($officeState) {
                'completed' => 'Completed',
                'in_progress' => 'In progress',
                'ready' => 'Ready',
                'anticipated' => 'Awaiting BPLO routing',
                default => 'Awaiting responsibility creation',
            };
            $notes[] = $this->workNote(
                id: 'office_'.$definition['key'],
                actorKey: $definition['key'],
                actorLabel: $definition['label'],
                instruction: $definition['instruction'],
                section: 'processing',
                anchor: 'office_'.$definition['key'],
                state: $officeState,
                stateLabel: $officeStateLabel,
                tone: 'blue',
                affordance: $this->officeAffordance($tasks, $projection, $definition['key']),
                completedAt: $office?->certification['certified_at'] ?? null,
            );
        }

        $allOfficesCompleted = count($offices) > 0
            && collect($offices)->every(fn (ApplicationOfficeData $office): bool => $office->status === 'certified');
        $notes[] = $this->workNote(
            id: 'assessment_preparation',
            actorKey: 'assessment_officer',
            actorLabel: 'Assessment Officer',
            instruction: 'Prepare immutable Assessment',
            section: 'assessment',
            anchor: 'assessment_slip',
            state: $assessment !== null ? 'completed' : ($allOfficesCompleted ? 'ready' : 'waiting'),
            stateLabel: $assessment !== null ? 'Completed' : ($allOfficesCompleted ? 'Ready' : 'Awaiting office determinations'),
            tone: 'orange',
            affordance: $taskIndex->get('prepare_assessment'),
            completedAt: $assessment?->assessed_at?->toIso8601String(),
        );
        $notes[] = $this->workNote(
            id: 'treasury_counter_check',
            actorKey: 'treasury',
            actorLabel: 'Treasury',
            instruction: 'Counter-check frozen Assessment',
            section: 'assessment',
            anchor: 'treasury_counter_check',
            state: $assessment?->treasuryCounterCheck !== null ? 'completed' : ($assessment !== null ? 'ready' : 'waiting'),
            stateLabel: $assessment?->treasuryCounterCheck !== null ? 'Completed' : ($assessment !== null ? 'Ready' : 'Awaiting Assessment'),
            tone: 'green',
            affordance: $taskIndex->get('counter_check'),
            completedAt: $assessment?->treasuryCounterCheck?->checked_at?->toIso8601String(),
        );
        $notes[] = $this->workNote(
            id: 'treasurer_decision',
            actorKey: 'municipal_treasurer',
            actorLabel: 'Municipal Treasurer',
            instruction: 'Approve or return exact Assessment',
            section: 'assessment',
            anchor: 'treasurer_decision',
            state: $assessment?->decision !== null ? 'completed' : ($assessment?->treasuryCounterCheck !== null ? 'ready' : 'waiting'),
            stateLabel: $assessment?->decision !== null ? 'Completed' : ($assessment?->treasuryCounterCheck !== null ? 'Ready' : 'Awaiting Treasury counter-check'),
            tone: 'green',
            affordance: $taskIndex->get('treasurer_decision'),
            completedAt: $assessment?->decision?->decided_at?->toIso8601String(),
        );

        $balance = $schedule === null ? null : $schedule->total_amount_cents - $schedule->paid_amount_cents;
        $paymentState = $schedule === null ? 'waiting' : ($balance === 0 ? 'completed' : ($schedule->paid_amount_cents > 0 ? 'in_progress' : 'ready'));
        $paymentStateLabel = match ($paymentState) {
            'completed' => 'Completed',
            'in_progress' => 'Partially collected',
            'ready' => 'Ready',
            default => 'Awaiting approved Payable',
        };
        $notes[] = $this->workNote(
            id: 'applicant_payment',
            actorKey: 'citizen',
            actorLabel: 'Applicant',
            instruction: 'Pay assessed balance',
            section: 'payment',
            anchor: 'payable',
            state: $paymentState,
            stateLabel: $paymentStateLabel,
            tone: 'cream',
            affordance: $taskIndex->get('pay_balance'),
            completedAt: $balance === 0 ? $application->paymentSchedules->flatMap(fn ($candidate) => $candidate->treasuryCollections)->sortByDesc('received_at')->first()?->received_at?->toIso8601String() : null,
        );

        $unreceiptedCollection = $application->paymentSchedules
            ->flatMap(fn ($candidate) => $candidate->treasuryCollections)
            ->first(fn (TreasuryCollection $collection): bool => $collection->receipt === null);
        $receiptState = count($receipts) > 0 ? 'completed' : ($unreceiptedCollection instanceof TreasuryCollection ? 'ready' : 'waiting');
        $notes[] = $this->workNote(
            id: 'official_receipt',
            actorKey: 'cashier',
            actorLabel: 'Cashier',
            instruction: 'Issue Official Receipt',
            section: 'payment',
            anchor: 'official_receipt',
            state: $receiptState,
            stateLabel: $receiptState === 'completed' ? 'Completed' : ($receiptState === 'ready' ? 'Ready' : 'Awaiting Collection'),
            tone: 'green',
            affordance: $unreceiptedCollection instanceof TreasuryCollection ? $taskIndex->get('issue_receipt_'.$unreceiptedCollection->id) : null,
            completedAt: count($receipts) > 0 ? $receipts[0]->issued_on : null,
        );

        foreach ($application->postPaymentOfficeCertifications as $certification) {
            $completed = $certification->status === 'completed' && $certification->result === 'certified';
            $notes[] = $this->workNote(
                id: 'post_payment_'.$certification->office_code,
                actorKey: $certification->office_code,
                actorLabel: $certification->office_label,
                instruction: 'Review bound OR '.$certification->receipt->receipt_number.' and certify post-payment work',
                section: 'processing',
                anchor: 'office_'.$certification->office_code,
                state: $completed ? 'completed' : 'ready',
                stateLabel: $completed ? 'Synthetic certification completed' : 'Ready after Official Receipt',
                tone: 'blue',
                affordance: $taskIndex->get('post_payment_certification_'.$certification->id),
                completedAt: $certification->certified_at?->toIso8601String(),
                blockingReason: $completed ? null : 'Exact per-office production certification semantics remain unresolved; this action records synthetic-only cleanroom evidence.',
            );
        }

        $completion = $application->provisionalUatPermitCompletion;
        $notes[] = $this->workNote(
            id: 'permit_authority_review',
            actorKey: 'permit_issuer',
            actorLabel: 'Permit Issuance',
            instruction: 'Issue synthetic Business Permit specimen',
            section: 'permit',
            anchor: 'permit_authority',
            state: $permit->issued ? 'completed' : ($permit->ready ? 'ready' : 'waiting'),
            stateLabel: $permit->issued ? 'Synthetic specimen issued' : ($permit->ready ? 'Ready' : 'Awaiting PermitReadiness'),
            tone: 'violet',
            affordance: $taskIndex->get('issue_synthetic_permit'),
            completedAt: $completion?->issued_at?->toIso8601String(),
            blockingReason: $permit->issued ? null : 'Synthetic numbering and Mayor authority evidence cannot establish production authority.',
        );
        $notes[] = $this->workNote(
            id: 'permit_release',
            actorKey: 'releasing_officer',
            actorLabel: 'Releasing Officer',
            instruction: 'Release Business Permit',
            section: 'permit',
            anchor: 'permit_release',
            state: $permit->released ? 'completed' : ($permit->issued ? 'ready' : 'waiting'),
            stateLabel: $permit->released ? 'Synthetic specimen released' : ($permit->issued ? 'Ready after issuance' : 'Awaiting issuance'),
            tone: 'violet',
            affordance: $taskIndex->get('release_synthetic_permit'),
            completedAt: $completion?->released_at?->toIso8601String(),
            blockingReason: $permit->released ? null : 'Release remains distinct from issuance; production authority and legal effect remain false.',
        );

        $notes[] = $this->workNote(
            id: 'citizen_permit_ready',
            actorKey: 'citizen',
            actorLabel: 'Applicant',
            instruction: 'View, print, and verify released Permit specimen',
            section: 'permit',
            anchor: 'permit_verification',
            state: $permit->released ? 'ready' : 'waiting',
            stateLabel: $permit->released ? 'Permit specimen ready' : 'Awaiting BPLO release',
            tone: 'cream',
            affordance: $permit->released ? $this->task('view_permit_verification', 'Verify exact Permit identity', 'permit', $permit->verification['view_url']) : null,
            completedAt: $completion?->released_at?->toIso8601String(),
            blockingReason: 'Public verification proves identity only, not legal attestation or revocation status.',
        );

        return $notes;
    }

    /**
     * @param  list<ApplicationOfficeData>  $offices
     * @return list<array{key: string, label: string, instruction: string}>
     */
    private function officeNoteDefinitions(PermitApplication $application, array $offices): array
    {
        if (data_get($application->metadata, 'lifecycle_cleanroom.semantic_classification') === 'synthetic_only') {
            return [
                ['key' => 'assessor', 'label' => 'Municipal Assessor', 'instruction' => 'Record assessed-value determinations and Paperless Payment Orders'],
                ['key' => 'engineering', 'label' => 'Engineering', 'instruction' => 'Review premises and record determination'],
                ['key' => 'health', 'label' => 'Health', 'instruction' => 'Record health and sanitary determinations'],
                ['key' => 'menro', 'label' => 'MENRO', 'instruction' => 'Record environmental determination'],
            ];
        }

        return array_values(collect($offices)->map(fn (ApplicationOfficeData $office): array => [
            'key' => $office->code,
            'label' => $office->label,
            'instruction' => 'Complete required determinations and Paperless Payment Orders',
        ])->all());
    }

    /**
     * @param  list<array{key: string, label: string, section: string, href: ?string}>  $tasks
     * @param  array<string, mixed>|null  $projection
     * @return array{key: string, label: string, section: string, href: ?string}|null
     */
    private function officeAffordance(array $tasks, ?array $projection, string $officeCode): ?array
    {
        $itemIds = collect(is_array($projection['items'] ?? null) ? $projection['items'] : [])
            ->filter(fn (array $item): bool => ($item['responsible_party'] ?? null) === $officeCode)
            ->pluck('id')
            ->map(fn (mixed $id): string => 'responsibility_'.$id);

        $task = collect($tasks)->first(fn (array $candidate): bool => $itemIds->contains($candidate['key']));

        return is_array($task) ? $task : null;
    }

    /** @param array{key: string, label: string, section: string, href: ?string}|null $affordance */
    private function workNote(
        string $id,
        string $actorKey,
        string $actorLabel,
        string $instruction,
        string $section,
        string $anchor,
        string $state,
        string $stateLabel,
        string $tone,
        ?array $affordance,
        ?string $completedAt = null,
        ?string $blockingReason = null,
    ): ApplicationWorkNoteData {
        $actionable = $affordance !== null && in_array($state, ['ready', 'in_progress'], true);

        return new ApplicationWorkNoteData(
            id: $id,
            actor_key: $actorKey,
            actor_label: $actorLabel,
            instruction: $instruction,
            section: $section,
            anchor: $anchor,
            state: $state,
            state_label: $stateLabel,
            tone: $tone,
            actionable: $actionable,
            action_label: $actionable ? $affordance['label'] : null,
            action_url: $actionable ? $affordance['href'] : null,
            completed_at: $completedAt,
            blocking_reason: $blockingReason,
        );
    }

    /**
     * @param  array<string, mixed>|null  $projection
     * @return list<array{key: string, label: string, section: string, href: ?string}>
     */
    private function tasks(PermitApplication $application, ?User $viewer, ?array $projection): array
    {
        if ($viewer === null) {
            return [];
        }

        $tasks = [];
        $roleCode = $viewer->role?->code;
        $isApplicant = $viewer->business_owner_id !== null && $viewer->business_owner_id === $application->business->business_owner_id;
        $assessment = $application->assessments->first();
        $schedule = $application->paymentSchedules->sortByDesc('sequence')->first();

        if ($application->submitted_at === null && $isApplicant && $viewer->hasPermission(UserPermission::SubmitOwnPermitApplications)) {
            $tasks[] = $this->task('submit_application', 'Submit Business Permit Application', 'application', route('citizen.permit-applications.show', $application, false));
        }

        if ($application->bploRoutingDetermination === null && $viewer->hasPermission(UserPermission::DetermineBploRouting)) {
            $tasks[] = $this->task('determine_routing', 'Determine BPLO routing', 'processing', route('staff.permit-applications.show', $application, false));
        }

        if (is_array($projection)) {
            $projectionItems = is_array($projection['items'] ?? null) ? $projection['items'] : [];
            collect($projectionItems)
                ->filter(fn (array $item): bool => $item['resolution'] !== 'resolved'
                    && ($item['responsible_party'] === $roleCode || data_get($item, 'metadata.authorized_actor_id') === $viewer->id))
                ->each(function (array $item) use (&$tasks, $application): void {
                    $tasks[] = $this->task(
                        'responsibility_'.$item['id'],
                        'Complete '.data_get($item, 'metadata.label', str($item['key'])->headline()->toString()),
                        'processing',
                        route('staff.permit-applications.evaluation.show', $application, false),
                    );
                });
        }

        $projectedItems = is_array($projection['items'] ?? null) ? $projection['items'] : [];
        $allResolved = is_array($projection) && collect($projectedItems)->where('is_required', true)
            ->every(fn (array $item): bool => $item['resolution'] === 'resolved');
        if ($assessment === null && $allResolved && $viewer->hasPermission(UserPermission::AssessPermitApplications)) {
            $tasks[] = $this->task('prepare_assessment', 'Prepare immutable Assessment', 'assessment', route('staff.permit-applications.evaluation.show', $application, false));
        }
        if ($assessment instanceof Assessment && $assessment->treasuryCounterCheck === null && $viewer->hasPermission(UserPermission::CounterCheckBusinessPermitEvaluations)) {
            $tasks[] = $this->task('counter_check', 'Counter-check the frozen Assessment', 'assessment', route('staff.permit-applications.assessments.show', $assessment, false));
        }
        if ($assessment instanceof Assessment && $assessment->treasuryCounterCheck !== null && $assessment->decision === null && $viewer->hasPermission(UserPermission::ApproveAssessments)) {
            $tasks[] = $this->task('treasurer_decision', 'Approve or return the exact Assessment', 'assessment', route('staff.permit-applications.assessments.show', $assessment, false));
        }
        if ($isApplicant && $schedule !== null && $schedule->total_amount_cents > $schedule->paid_amount_cents) {
            $tasks[] = $this->task('pay_balance', 'Pay the assessed balance', 'payment', route('citizen.payment-schedules.show', $schedule, false));
        }

        if ($viewer->hasPermission(UserPermission::IssueReceipts)) {
            foreach ($application->paymentSchedules->flatMap(fn ($candidate) => $candidate->treasuryCollections) as $collection) {
                if ($collection->receipt === null) {
                    $tasks[] = $this->task('issue_receipt_'.$collection->id, 'Issue the Official Receipt', 'payment', route('staff.payment-schedules.show', $collection->payment_schedule_id, false));
                }
            }
        }

        $runId = data_get($application->metadata, 'lifecycle_cleanroom.run_id');
        $run = is_string($runId) ? LifecycleCleanroomRun::query()->where('public_id', $runId)->first() : null;
        if ($run instanceof LifecycleCleanroomRun && $run->status === 'active') {
            foreach ($application->postPaymentOfficeCertifications->where('status', '!=', 'completed') as $certification) {
                if (data_get($run->actor_manifest, 'actors.'.$certification->office_code.'.user_id') === $viewer->id) {
                    $tasks[] = $this->task(
                        'post_payment_certification_'.$certification->id,
                        'Certify post-payment '.$certification->office_label.' work',
                        'processing',
                        route('stakeholder-preview.lifecycle-cleanroom.post-payment-certifications.store', [$run, $certification], false),
                    );
                }
            }

            $readiness = $this->permitReadiness->handle($application);
            $completion = $application->provisionalUatPermitCompletion;
            if ($readiness['ready']
                && $completion?->issued_at === null
                && data_get($run->actor_manifest, 'actors.permit_issuer.user_id') === $viewer->id) {
                $tasks[] = $this->task(
                    'issue_synthetic_permit',
                    'Issue synthetic Business Permit specimen',
                    'permit',
                    route('stakeholder-preview.lifecycle-cleanroom.permit.issue', $run, false),
                );
            }
            if ($completion?->issued_at !== null
                && $completion->released_at === null
                && data_get($run->actor_manifest, 'actors.releasing_officer.user_id') === $viewer->id) {
                $tasks[] = $this->task(
                    'release_synthetic_permit',
                    'Release issued Business Permit specimen',
                    'permit',
                    route('stakeholder-preview.lifecycle-cleanroom.permit.release', $run, false),
                );
            }
        }

        return $tasks;
    }

    /** @return array{key: string, label: string, section: string, href: ?string} */
    private function task(string $key, string $label, string $section, ?string $href): array
    {
        return compact('key', 'label', 'section', 'href');
    }
}
