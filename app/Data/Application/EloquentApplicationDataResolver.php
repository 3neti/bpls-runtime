<?php

namespace App\Data\Application;

use App\Actions\DescribePermitReleaseReadiness;
use App\Actions\DescribePermitVerificationBoundary;
use App\Assessment\Price\HistoricalPriceReport;
use App\Enums\ReceiptStatus;
use App\Enums\UserPermission;
use App\Evaluation\BusinessPermitEvaluationResolver;
use App\Models\Assessment;
use App\Models\BusinessPermitEvaluation;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDocument;
use App\Models\PermitClearance;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use App\Models\User;
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
        private readonly DescribePermitVerificationBoundary $verificationBoundary,
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
            'assessments' => fn ($query) => $query->whereNull('superseded_at')->latest('sequence'),
            'assessments.lines.lineOfBusiness',
            'assessments.decision.decidedBy',
            'assessments.treasuryCounterCheck.checkedBy',
            'paymentSchedules.lines',
            'paymentSchedules.treasuryCollections.allocations.paymentScheduleLine',
            'paymentSchedules.treasuryCollections.assessment',
            'paymentSchedules.treasuryCollections.receipt.issuedBy',
            'bploRoutingDetermination.works.paymentOrders.lines',
            'businessPermitEvaluation.currentVersion.counterCheck',
            'businessPermitEvaluation.items.revisions.version',
            'businessPermitEvaluation.items.revisions.actor',
            'provisionalUatPermitCompletion',
        ])->findOrFail($permitApplication->id);

        $assessment = $application->assessments->first();
        $evaluationProjection = $this->evaluationProjection($application, $assessment);
        $offices = $this->offices($application, $evaluationProjection);
        $receipts = array_values($application->paymentSchedules
            ->flatMap(fn ($schedule) => $schedule->treasuryCollections)
            ->map(fn (TreasuryCollection $collection): ?OfficialReceiptData => $this->officialReceipt($collection))
            ->filter()
            ->values()
            ->all());
        $permit = $this->permit($application, $receipts);
        $tasks = $this->tasks($application, $viewer, $evaluationProjection);
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
                'reason' => $application->bploRoutingDetermination?->situational_context,
            ],
            offices: $offices,
            financial: $this->financial($assessment, $evaluationProjection),
            payment: $this->payment($application),
            official_receipts: $receipts,
            post_payment: [
                'state' => $application->clearances->isEmpty() ? 'pending_payment' : 'in_progress',
                'certifications' => array_values($application->clearances->map(fn (PermitClearance $clearance): array => [
                    'id' => $clearance->id,
                    'code' => $clearance->code,
                    'label' => $clearance->label,
                    'status' => $clearance->status->value,
                    'completed_at' => $clearance->completed_at?->toIso8601String(),
                    'completed_by' => $clearance->completedBy?->getAttribute('name'),
                ])->values()->all()),
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
            actor_context: new ActorContextData(
                actor_id: $viewer?->id,
                actor_label: $viewer === null ? 'Laboratory observer' : $viewer->name,
                role_code: $viewer?->role?->code,
                current_tasks: $tasks,
                available_affordances: $tasks,
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
            ->map(function (Collection $works, string $officeCode) use ($items): ApplicationOfficeData {
                $workIds = $works->pluck('id');
                $officeItems = $items->filter(fn (array $item): bool => $workIds->contains(data_get($item, 'metadata.bplo_routing_work_id')))->values();
                $orders = $works->flatMap(fn ($work) => $work->paymentOrders)
                    ->filter(fn ($order): bool => $order->status === 'issued' && $order->superseded_at === null)
                    ->values();
                $resolved = $officeItems->where('resolution', 'resolved');
                $allResolved = $officeItems->isNotEmpty() && $resolved->count() === $officeItems->count();
                $latest = $resolved->sortByDesc('occurred_at')->first();

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
                    paperless_payment_order_count: $orders->count(),
                    total_amount_cents: (int) $orders->sum('total_amount_cents'),
                    certification: $allResolved && is_array($latest) ? [
                        'statement' => 'Electronically certified',
                        'officer_name' => $latest['actor_name'],
                        'certified_at' => $latest['occurred_at'],
                    ] : null,
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

    private function officialReceipt(TreasuryCollection $collection): ?OfficialReceiptData
    {
        $receipt = $collection->receipt;
        if (! $receipt instanceof Receipt || $receipt->status !== ReceiptStatus::Issued) {
            return null;
        }
        $sourceAssessment = $collection->getRelation('assessment');

        return new OfficialReceiptData(
            schema_version: OfficialReceiptData::Schema,
            accountable_form_number: 51,
            form_revision: 'Revised June 2008',
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
                'account_code' => is_string(data_get($allocation->source_snapshot, 'account_code')) ? data_get($allocation->source_snapshot, 'account_code') : null,
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
            collecting_officer: $receipt->issuedBy?->getAttribute('name'),
            source: [
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
        $readiness = $this->releaseReadiness->handle($application);
        $receipt = count($receipts) === 1 ? $receipts[0] : null;
        $receiptBound = $receipt instanceof OfficialReceiptData && filled($receipt->receipt_number);
        $ready = $readiness['ready_for_authority_review'] && $receiptBound;
        $completion = $application->provisionalUatPermitCompletion;

        return new BusinessPermitData(
            schema_version: BusinessPermitData::Schema,
            state: $ready ? 'ready_for_authority_review' : 'pending',
            ready: $ready,
            released: false,
            valid: false,
            permit_number: $completion?->permit_number,
            issued_on: null,
            valid_until: null,
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
                'authority_status' => 'unresolved',
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
            printable_artifact_url: route('staff.permit-applications.permit.pdf', $application, false),
            statement: $receiptBound
                ? 'Official Receipt identity is bound; issuance, signature, release, and legal validity remain uncommissioned.'
                : 'Permit cannot be valid or released without a canonical Official Receipt number bound to it.',
            blockers: array_values(array_unique([
                ...$readiness['blocked_by'],
                ...($receiptBound ? [] : ['official_receipt_number_binding']),
                ...(count($receipts) > 1 ? ['official_receipt_binding_selection_policy'] : []),
            ])),
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

        return $tasks;
    }

    /** @return array{key: string, label: string, section: string, href: ?string} */
    private function task(string $key, string $label, string $section, ?string $href): array
    {
        return compact('key', 'label', 'section', 'href');
    }
}
