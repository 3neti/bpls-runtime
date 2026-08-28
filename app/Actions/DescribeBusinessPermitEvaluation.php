<?php

namespace App\Actions;

use App\Enums\AssessmentDecisionAction;
use App\Evaluation\BusinessPermitEvaluationReadiness;
use App\Evaluation\BusinessPermitEvaluationResolver;
use App\Models\Assessment;
use App\Models\BusinessPermitEvaluation;
use App\Models\LineOfBusiness;
use App\Models\User;

class DescribeBusinessPermitEvaluation
{
    public function __construct(
        private readonly BusinessPermitEvaluationResolver $resolver,
        private readonly BusinessPermitEvaluationReadiness $readiness,
    ) {}

    /** @return array<string, mixed> */
    public function handle(BusinessPermitEvaluation $evaluation, User $viewer, string $lens): array
    {
        $evaluation->load([
            'permitApplication.business.owner',
            'permitApplication.lines.lineOfBusiness',
            'permitApplication.paymentSchedules',
            'permitApplication.assessments.decision',
            'currentVersion.counterCheck.checkedBy',
        ]);
        $projection = $this->resolver->resolve($evaluation);
        $commissionedReadiness = $this->readiness->forAssessment($evaluation, 'commissioned');
        $uatReadiness = $this->readiness->forAssessment($evaluation, 'provisional_uat');
        $lineNames = LineOfBusiness::query()
            ->whereIn('id', collect($projection['resolved_line_of_business_ids'])
                ->merge(data_get($projection, 'application.declared_lines.*.line_of_business_id'))
                ->filter()->unique())
            ->get()
            ->keyBy('id');
        $latestAssessment = $evaluation->permitApplication->assessments->sortByDesc('sequence')->first();
        $activeAssessment = $evaluation->permitApplication->assessments->firstWhere('superseded_at', null);
        $myItems = collect($projection['items'])->filter(function (array $item) use ($viewer): bool {
            return data_get($item, 'metadata.authorized_actor_id') === $viewer->id
                || $item['responsible_party'] === $viewer->role?->code;
        })->pluck('id')->all();

        return [
            'id' => $evaluation->id,
            'version' => [
                'id' => $projection['version_id'],
                'sequence' => $projection['version_sequence'],
                'fingerprint' => $projection['current_fingerprint'],
                'fingerprint_current' => $projection['fingerprint_current'],
                'treasury_counter_check' => $evaluation->currentVersion?->counterCheck === null ? null : [
                    'checked_at' => $evaluation->currentVersion->counterCheck->checked_at->toIso8601String(),
                    'checked_by' => $evaluation->currentVersion->counterCheck->checkedBy->name,
                    'reason' => $evaluation->currentVersion->counterCheck->reason,
                    'evidence_provenance' => $lens === 'internal'
                        ? $evaluation->currentVersion->counterCheck->evidence_provenance
                        : null,
                ],
            ],
            'status_label' => $this->statusLabel($evaluation, $projection, $uatReadiness, $activeAssessment),
            'application' => [
                'id' => $evaluation->permitApplication->id,
                'application_number' => $evaluation->permitApplication->application_number,
                'tracking_reference' => $evaluation->permitApplication->tracking_reference,
                'business_name' => $evaluation->permitApplication->business->name,
                'owner_name' => $evaluation->permitApplication->business->owner->name,
                'type' => $evaluation->permitApplication->type->value,
                'year' => $evaluation->permitApplication->application_year,
            ],
            'applicant_declaration' => collect(data_get($projection, 'application.declared_lines', []))->map(fn (array $line): array => [
                ...$line,
                'line_of_business_name' => $lineNames->get($line['line_of_business_id'])?->name,
            ])->all(),
            'municipal_resolved_lines' => collect($projection['resolved_line_of_business_ids'])->map(fn (int $id): array => [
                'id' => $id,
                'name' => $lineNames->get($id)?->name,
            ])->all(),
            'items' => collect($projection['items'])->map(fn (array $item): array => $this->itemPayload($item, $lens, in_array($item['id'], $myItems, true)))->all(),
            'projected_charges' => collect($projection['projected_charges'])->map(fn (array $charge): array => [
                'key' => $charge['key'],
                'fee_rule_id' => $charge['fee_rule_id'],
                'code' => $charge['code'],
                'name' => $charge['name'],
                'amount_cents' => $charge['amount_cents'],
                'basis' => $charge['basis'],
                'basis_amount_cents' => $charge['basis_amount_cents'],
                'legal_basis' => $charge['legal_basis'],
                'source_classification' => $charge['source_classification'],
            ])->all(),
            'current_evaluated_amount_cents' => $projection['total_amount_cents'],
            'pricing_issues' => $projection['pricing_issues'],
            'readiness' => [
                'commissioned' => ['ready' => $commissionedReadiness['ready'], 'issues' => $commissionedReadiness['issues']],
                'provisional_uat' => ['ready' => $uatReadiness['ready'], 'issues' => $uatReadiness['issues']],
            ],
            'my_item_ids' => $myItems,
            'latest_assessment' => $latestAssessment instanceof Assessment ? [
                'id' => $latestAssessment->id,
                'sequence' => $latestAssessment->sequence,
                'total_amount_cents' => $latestAssessment->total_amount_cents,
                'superseded' => $latestAssessment->superseded_at !== null,
                'decision' => $latestAssessment->decision?->action->value,
                'evaluation_version_id' => $latestAssessment->business_permit_evaluation_version_id,
                'evaluation_fingerprint' => $latestAssessment->business_permit_evaluation_fingerprint,
                'consumes_current_evaluation' => $latestAssessment->business_permit_evaluation_version_id === $projection['version_id']
                    && $latestAssessment->business_permit_evaluation_fingerprint === $projection['current_fingerprint'],
            ] : null,
            'financial_lock' => $evaluation->permitApplication->paymentSchedules->isNotEmpty(),
            'lens' => $lens,
        ];
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function itemPayload(array $item, string $lens, bool $isMine): array
    {
        $history = collect($item['revision_history'])->map(fn (array $revision): array => [
            'version_sequence' => $revision['version_sequence'],
            'action' => $revision['action'],
            'applicability' => $revision['applicability'],
            'value' => $revision['value'],
            'source_classification' => $revision['source_classification'],
            'actor_name' => $lens === 'internal' ? $revision['actor_name'] : null,
            'reason' => $revision['reason'],
            'occurred_at' => $revision['occurred_at'],
        ])->all();

        return [
            'id' => $item['id'],
            'key' => $item['key'],
            'label' => data_get($item, 'metadata.label', str($item['key'])->headline()->toString()),
            'item_type' => $item['item_type'],
            'responsible_party' => $item['responsible_party'],
            'is_required' => $item['is_required'],
            'requires_confirmation' => $item['requires_confirmation'],
            'is_mine' => $isMine,
            'applicability' => $item['applicability'],
            'resolution' => $item['resolution'],
            'action' => $item['action'],
            'default_value' => $item['default_value'],
            'default_source_classification' => $item['default_source_classification'],
            'resolved_value' => $item['value'],
            'source_classification' => $item['source_classification'],
            'reason' => $item['reason'],
            'occurred_at' => $item['occurred_at'],
            'inspection_required' => data_get($item, 'metadata.inspection_required', false),
            'history' => $history,
        ];
    }

    /** @param array<string, mixed> $projection @param array<string, mixed> $uatReadiness */
    private function statusLabel(
        BusinessPermitEvaluation $evaluation,
        array $projection,
        array $uatReadiness,
        ?Assessment $activeAssessment,
    ): string {
        if ($evaluation->permitApplication->paymentSchedules->isNotEmpty()) {
            return 'Payment Locked After Assessment';
        }
        if ($activeAssessment instanceof Assessment) {
            if ($activeAssessment->decision?->action === AssessmentDecisionAction::ReturnedForCorrection) {
                return 'Returned for Correction';
            }

            return $activeAssessment->decision === null ? 'Awaiting Treasurer Approval' : 'Assessment Prepared';
        }
        $awaiting = collect($projection['items'])->first(fn (array $item): bool => $item['is_required'] && $item['resolution'] !== 'resolved');
        if (is_array($awaiting)) {
            return 'Awaiting '.str($awaiting['responsible_party'])->headline()->toString();
        }
        if ($evaluation->currentVersion?->counterCheck === null) {
            return 'Awaiting Treasury Review';
        }

        return $uatReadiness['ready'] ? 'Ready for Assessment' : 'Evaluation In Progress';
    }
}
