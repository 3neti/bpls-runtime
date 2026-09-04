<?php

namespace App\Actions;

use App\Assessment\AssessmentPriceInputResolver;
use App\Assessment\Price\CanonicalFinancialFingerprint;
use App\Assessment\Price\Price;
use App\Data\Assessment\AssessmentPriceComponentInput;
use App\Data\Assessment\AssessmentPriceInput;
use App\Enums\UserRole;
use App\Evaluation\BusinessPermitEvaluationResolver;
use App\Models\BusinessPermitEvaluationItem;
use App\Models\PermitApplication;
use App\Models\User;
use LogicException;

final class BuildBusinessPermitEvaluationPricePreview
{
    public function __construct(
        private readonly BusinessPermitEvaluationResolver $evaluationResolver,
        private readonly AssessmentPriceInputResolver $priceInputResolver,
        private readonly CanonicalFinancialFingerprint $fingerprint,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        PermitApplication $application,
        BusinessPermitEvaluationItem $item,
        User $viewer,
        int $unitAmountMinor,
        int $quantity,
        ?string $serviceLabel = null,
        ?string $basis = null,
        ?string $scheduleReference = null,
    ): array {
        $item->loadMissing('evaluation.permitApplication.lines.lineOfBusiness');

        if ($item->evaluation->permit_application_id !== $application->id) {
            throw new LogicException('The calculator responsibility does not belong to this Application.');
        }

        $authorizedActorId = data_get($item->metadata, 'authorized_actor_id');
        if (! $viewer->hasRole(UserRole::Admin)
            && $authorizedActorId !== $viewer->id
            && $item->responsible_party !== $viewer->role?->code) {
            throw new LogicException("This Evaluation responsibility belongs to [{$item->responsible_party}].");
        }

        if ($item->item_type->value !== 'charge') {
            throw new LogicException('Only an open office charge may be previewed in the calculator.');
        }

        $evaluationProjection = $this->evaluationResolver->resolve($item->evaluation);
        $projectedItem = collect($evaluationProjection['items'])->firstWhere('id', $item->id);
        if (! is_array($projectedItem) || $projectedItem['resolution'] === 'resolved') {
            throw new LogicException('Only an open office charge may be previewed in the calculator.');
        }
        $baseInput = $this->priceInputResolver->resolve($application, $evaluationProjection);
        $label = filled($serviceLabel)
            ? $serviceLabel
            : (string) data_get($item->metadata, 'label', str($item->key)->headline()->toString());
        $lineOfBusinessId = data_get($item->metadata, 'line_of_business_id');
        $permitApplicationLineId = data_get($item->metadata, 'permit_application_line_id');
        $lineOfBusinessName = $application->lines
            ->firstWhere('line_of_business_id', $lineOfBusinessId)
            ?->lineOfBusiness?->name;
        $previewComponents = [];

        for ($unit = 1; $unit <= $quantity; $unit++) {
            $previewComponents[] = new AssessmentPriceComponentInput(
                key: "pro-forma:{$item->id}:{$unit}",
                type: 'office_determination_preview',
                label: $quantity === 1 ? $label : "{$label} · Unit {$unit}",
                scope: is_int($lineOfBusinessId) ? 'line_of_business' : 'application',
                permit_application_line_id: is_int($permitApplicationLineId) ? $permitApplicationLineId : null,
                line_of_business_id: is_int($lineOfBusinessId) ? $lineOfBusinessId : null,
                line_of_business_name: $lineOfBusinessName,
                responsible_office: $item->responsible_party,
                currency: 'PHP',
                amount_minor: $unitAmountMinor,
                source_type: 'pro_forma_office_input',
                source_identity: $scheduleReference ?? "evaluation_item:{$item->id}",
                source_version: "evaluation:{$evaluationProjection['current_fingerprint']}",
                exact_once_key: "pro_forma:evaluation_item:{$item->id}:unit:{$unit}",
                legal_basis: null,
                explanation: [
                    'official' => false,
                    'evaluation_item_id' => $item->id,
                    'schedule_reference' => $scheduleReference,
                    'basis' => $basis,
                    'unit' => $unit,
                    'quantity' => $quantity,
                ],
            );
        }

        $input = new AssessmentPriceInput(
            schema_version: AssessmentPriceInput::Schema,
            currency: $baseInput->currency,
            assessment_context: $baseInput->assessment_context,
            components: [...$baseInput->components, ...$previewComponents],
            modifiers: $baseInput->modifiers,
            taxes: $baseInput->taxes,
            composition_policy_version: $baseInput->composition_policy_version,
        );
        $inputSnapshot = $input->toArray();
        $report = Price::fromInput($input)->report()->toArray();
        $pending = collect($evaluationProjection['items'])
            ->filter(fn (array $candidate): bool => $candidate['item_type'] === 'charge'
                && $candidate['resolution'] !== 'resolved'
                && $candidate['id'] !== $item->id)
            ->map(fn (array $candidate): array => [
                'id' => $candidate['id'],
                'label' => data_get($candidate, 'metadata.label', str($candidate['key'])->headline()->toString()),
                'office' => $candidate['responsible_party'],
            ])
            ->values()
            ->all();

        return [
            'schema_version' => 'bpls.evaluation-price-preview.v1',
            'official' => false,
            'currency' => 'PHP',
            'evaluation_item_id' => $item->id,
            'selection' => [
                'service_label' => $label,
                'basis' => $basis,
                'schedule_reference' => $scheduleReference,
                'unit_amount_minor' => $unitAmountMinor,
                'quantity' => $quantity,
                'line_total_minor' => $unitAmountMinor * $quantity,
            ],
            'pending_charges' => $pending,
            'input_snapshot' => $inputSnapshot,
            'input_fingerprint' => $this->fingerprint->hash($inputSnapshot),
            'price_report' => $report,
            'report_fingerprint' => $this->fingerprint->hash($report),
        ];
    }
}
