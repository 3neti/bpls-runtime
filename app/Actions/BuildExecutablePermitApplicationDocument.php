<?php

namespace App\Actions;

use App\Enums\AssessmentDecisionAction;
use App\Evaluation\BusinessPermitEvaluationResolver;
use App\Models\BusinessPermitEvaluation;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDeclaration;
use Illuminate\Support\Collection;

class BuildExecutablePermitApplicationDocument
{
    public function __construct(
        private readonly BusinessPermitEvaluationResolver $evaluationResolver,
    ) {}

    /** @return array<string, mixed> */
    public function handle(PermitApplication $permitApplication): array
    {
        $application = PermitApplication::query()->with([
            'declaration',
            'business.owner',
            'lines.lineOfBusiness',
            'clearances',
            'assessments' => fn ($query) => $query->whereNull('superseded_at')->latest('sequence'),
            'assessments.lines.lineOfBusiness',
            'assessments.decision',
            'assessments.treasuryCounterCheck',
            'bploRoutingDetermination.works.paymentOrders.issuedBy',
            'bploRoutingDetermination.works.paymentOrders.lines',
            'businessPermitEvaluation.currentVersion',
        ])->findOrFail($permitApplication->id);
        $declaration = $application->declaration;
        $assessment = $application->assessments->first();
        $page2Assessment = $this->page2Assessment($application, $assessment !== null);

        return [
            'identity' => [
                'application_id' => $application->id,
                'application_number' => $application->application_number,
                'tracking_reference' => $application->tracking_reference,
                'tax_year' => $application->application_year,
                'type' => $application->type->value,
                'status' => $application->status->value,
            ],
            'declaration' => [
                'state' => $declaration instanceof PermitApplicationDeclaration ? 'frozen' : 'draft',
                'declared_at' => $declaration?->declared_at->toIso8601String(),
                'snapshot_hash' => $declaration?->snapshot_hash,
                'snapshot' => $declaration instanceof PermitApplicationDeclaration
                    ? $declaration->snapshot
                    : data_get($application->metadata, 'applicant_declaration_draft'),
            ],
            'verification' => $this->verification($application),
            'page_2_assessment' => $page2Assessment,
            'computation_assessment_slip' => $assessment === null ? null : [
                'assessment_id' => $assessment->id,
                'sequence' => $assessment->sequence,
                'status' => $assessment->status->value,
                'total_amount_cents' => $assessment->total_amount_cents,
                'line_count' => $assessment->lines()->count(),
                'statement' => 'Authoritative financial artifact: separate Computation/Assessment Slip',
            ],
            'treasury_counter_check' => $assessment?->treasuryCounterCheck === null ? null : [
                'result' => $assessment->treasuryCounterCheck->result?->value,
                'checked_at' => $assessment->treasuryCounterCheck->checked_at->toIso8601String(),
                'statement' => $assessment->treasuryCounterCheck->result?->value === 'no_correction'
                    ? 'Counter-check completed - no correction'
                    : 'Counter-check recorded against this Assessment',
            ],
            'municipal_treasurer' => $assessment?->decision === null ? null : [
                'action' => $assessment->decision->action->value,
                'decided_at' => $assessment->decision->decided_at->toIso8601String(),
                'exact_approval' => $assessment->decision->action === AssessmentDecisionAction::Approved,
                'assessment_snapshot_hash' => $assessment->decision->assessment_snapshot_hash,
            ],
            'permit' => [
                'status' => 'not_issued',
                'statement' => 'Permit not yet issued',
                'mayor_signature_authority' => 'unresolved',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function page2Assessment(PermitApplication $application, bool $assessmentPrepared): array
    {
        $routing = $application->bploRoutingDetermination;
        $evaluation = $application->businessPermitEvaluation;

        if ($routing === null) {
            return [
                'status' => 'awaiting_bplo_routing',
                'statement' => 'Awaiting the mandatory BPLO routing determination.',
                'populated_from_canonical_assessment' => false,
                'emerging_total_amount_cents' => null,
                'required_unresolved_charge_count' => 0,
                'offices' => [],
            ];
        }

        $projection = $evaluation instanceof BusinessPermitEvaluation
            && $evaluation->currentVersion !== null
                ? $this->evaluationResolver->resolve($evaluation)
                : null;
        $items = collect(is_array($projection) ? $projection['items'] : []);
        $offices = $routing->works
            ->groupBy('office_code')
            ->map(function (Collection $works, string $officeCode) use ($items): array {
                $workIds = $works->pluck('id');
                $officeItems = $items
                    ->filter(fn (array $item): bool => $item['item_type'] === 'charge'
                        && $workIds->contains(data_get($item, 'metadata.bplo_routing_work_id')))
                    ->values();
                $activeOrders = $works
                    ->flatMap(fn ($work) => $work->paymentOrders)
                    ->filter(fn ($order): bool => $order->status === 'issued' && $order->superseded_at === null)
                    ->values();
                $ordersByRevision = $activeOrders->keyBy('business_permit_evaluation_item_revision_id');
                $resolvedItems = $officeItems->where('resolution', 'resolved');
                $allResolved = $officeItems->isNotEmpty() && $resolvedItems->count() === $officeItems->count();
                $latestCertification = $allResolved
                    ? $resolvedItems->sortByDesc('occurred_at')->first()
                    : null;

                return [
                    'code' => $officeCode,
                    'label' => match ($officeCode) {
                        'assessor' => 'Municipal Assessor',
                        'menro' => 'MENRO',
                        default => $works->first()->office_label,
                    },
                    'status' => match (true) {
                        $allResolved => 'certified',
                        $resolvedItems->isNotEmpty() => 'in_progress',
                        default => 'awaiting_determination',
                    },
                    'required_determination_count' => $officeItems->count(),
                    'resolved_determination_count' => $resolvedItems->count(),
                    'payment_order_count' => $activeOrders->count(),
                    'total_amount_cents' => (int) $activeOrders->sum('total_amount_cents'),
                    'certification' => $allResolved && is_array($latestCertification) ? [
                        'officer_name' => $latestCertification['actor_name'],
                        'certified_at' => $latestCertification['occurred_at'],
                        'statement' => 'Electronically certified',
                    ] : null,
                    'lines' => $officeItems->map(function (array $item) use ($ordersByRevision): array {
                        $order = $ordersByRevision->get($item['revision_id']);
                        $proposal = data_get($item, 'default_value.amount_cents');
                        $determined = data_get($item, 'value.amount_cents');
                        $isApplicable = $item['applicability'] === 'applicable';

                        return [
                            'evaluation_item_id' => $item['id'],
                            'name' => data_get($item, 'metadata.label', str($item['key'])->headline()->toString()),
                            'status' => match (true) {
                                $item['resolution'] !== 'resolved' => 'awaiting_determination',
                                ! $isApplicable => 'not_applicable',
                                $item['action'] === 'confirmation' => 'confirmed',
                                $item['action'] === 'correction' => 'changed',
                                default => 'determined',
                            },
                            'proposal_amount_cents' => is_int($proposal) ? $proposal : null,
                            'determined_amount_cents' => $isApplicable && is_int($determined) ? $determined : null,
                            'display_amount_cents' => $item['resolution'] === 'resolved' && $isApplicable && is_int($determined)
                                ? $determined
                                : (is_int($proposal) ? $proposal : null),
                            'source_classification' => $item['resolution'] === 'resolved'
                                ? $item['source_classification']
                                : $item['default_source_classification'],
                            'paperless_payment_order' => $order === null ? null : [
                                'id' => $order->id,
                                'sequence' => $order->sequence,
                                'issued_at' => $order->issued_at->toIso8601String(),
                            ],
                        ];
                    })->all(),
                ];
            })
            ->sortBy(fn (array $office): int => match ($office['code']) {
                'assessor' => 0,
                'engineering' => 1,
                'health' => 2,
                'menro' => 3,
                default => 4,
            })
            ->values();
        $allCertified = $offices->isNotEmpty() && $offices->every(fn (array $office): bool => $office['status'] === 'certified');
        $unresolvedCount = is_array($projection)
            ? (int) data_get($projection, 'financial_working_paper.required_unresolved_charge_count', 0)
            : 0;

        return [
            'status' => match (true) {
                $assessmentPrepared => 'assessment_prepared',
                $allCertified => 'office_determinations_complete',
                default => 'office_determinations_in_progress',
            },
            'statement' => $assessmentPrepared
                ? 'The canonical Assessment has been prepared from the completed municipal determinations.'
                : ($allCertified
                    ? 'All concerned offices have completed their determinations. The Application is ready for Assessment preparation.'
                    : 'Concerned offices may complete their determinations asynchronously.'),
            'populated_from_canonical_assessment' => $assessmentPrepared,
            'emerging_total_amount_cents' => is_array($projection) ? $projection['total_amount_cents'] : null,
            'required_unresolved_charge_count' => $unresolvedCount,
            'offices' => $offices->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function verification(PermitApplication $application): array
    {
        $requirements = [
            ['description' => 'Barangay Clearance', 'issuing_office' => 'Barangay'],
            ['description' => 'Zoning Clearance (New/Renew)', 'issuing_office' => 'MPDO/Zoning'],
            ['description' => 'Building Permit/Occupancy Permit (New/Renew)', 'issuing_office' => 'Mun. Engineering'],
        ];

        return array_map(function (array $requirement) use ($application): array {
            $needle = mb_strtolower(strtok($requirement['description'], ' ('));
            $clearance = $application->clearances->first(fn ($candidate): bool => str_contains(mb_strtolower($candidate->label), $needle));

            return [
                ...$requirement,
                'canonical_clearance_id' => $clearance?->id,
                'status' => $clearance?->status->value ?? 'not_available',
                'date_issued' => $clearance?->completed_at?->toDateString(),
                'verified_by' => $clearance?->completed_by_id,
                'recommending_approval' => null,
            ];
        }, $requirements);
    }
}
