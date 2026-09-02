<?php

namespace App\Actions;

use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationItemType;
use App\Enums\BusinessPermitEvaluationRevisionAction;
use App\Enums\BusinessPermitEvaluationSource;
use App\Evaluation\BusinessPermitEvaluationVersioner;
use App\Models\BusinessPermitEvaluation;
use App\Models\BusinessPermitEvaluationItem;
use App\Models\BusinessPermitEvaluationVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class DefineBusinessPermitEvaluationItem
{
    public function __construct(private readonly BusinessPermitEvaluationVersioner $versioner) {}

    /**
     * @param  array<string, mixed>|null  $value
     * @param  array<string, mixed>|null  $metadata
     */
    public function handle(
        BusinessPermitEvaluation $evaluation,
        string $key,
        BusinessPermitEvaluationItemType $type,
        string $responsibleParty,
        bool $isRequired,
        bool $requiresConfirmation,
        BusinessPermitEvaluationApplicability $applicability,
        ?array $value,
        BusinessPermitEvaluationSource $source,
        ?User $actor,
        ?string $reason = null,
        ?array $metadata = null,
    ): BusinessPermitEvaluationItem {
        return DB::transaction(function () use ($evaluation, $key, $type, $responsibleParty, $isRequired, $requiresConfirmation, $applicability, $value, $source, $actor, $reason, $metadata): BusinessPermitEvaluationItem {
            if ($evaluation->items()->where('key', $key)->exists()) {
                throw new LogicException("Evaluation item [{$key}] is already defined; append a revision instead of overwriting it.");
            }

            $this->assertChargeValue($type, $applicability, $value, $source);
            if ($type === BusinessPermitEvaluationItemType::Charge
                && $applicability !== BusinessPermitEvaluationApplicability::NotApplicable
                && data_get($metadata, 'fee_rule_id') !== null) {
                throw new LogicException('A governed FeeRule charge must remain on the canonical AssessmentCalculator path and cannot also be defined as a human Evaluation charge.');
            }

            if ($type === BusinessPermitEvaluationItemType::Charge
                && data_get($metadata, 'bplo_routing_work_id') === null) {
                $matchingWork = $evaluation->permitApplication->bploRoutingDetermination?->works()
                    ->where('office_code', $responsibleParty)
                    ->get();
                if ($matchingWork?->count() === 1) {
                    $metadata = [...($metadata ?? []), 'bplo_routing_work_id' => $matchingWork->sole()->id];
                }
            }
            $item = $evaluation->items()->create([
                'key' => $key,
                'item_type' => $type,
                'responsible_party' => $responsibleParty,
                'is_required' => $isRequired,
                'requires_confirmation' => $requiresConfirmation,
                'metadata' => $metadata,
            ]);

            $this->versioner->create($evaluation, $actor, 'evaluation_item_defined', function (BusinessPermitEvaluationVersion $version) use ($item, $applicability, $value, $source, $actor, $reason): void {
                $item->revisions()->create([
                    'business_permit_evaluation_version_id' => $version->id,
                    'action' => BusinessPermitEvaluationRevisionAction::Proposal,
                    'applicability' => $applicability,
                    'value' => $value,
                    'source_classification' => $source,
                    'actor_id' => $actor?->id,
                    'reason' => $reason,
                    'occurred_at' => now(),
                ]);
            });

            return $item->fresh('revisions');
        });
    }

    /** @param array<string, mixed>|null $value */
    private function assertChargeValue(
        BusinessPermitEvaluationItemType $type,
        BusinessPermitEvaluationApplicability $applicability,
        ?array $value,
        BusinessPermitEvaluationSource $source,
    ): void {
        if ($type !== BusinessPermitEvaluationItemType::Charge
            || $applicability !== BusinessPermitEvaluationApplicability::Applicable) {
            return;
        }

        $amount = $value['amount_cents'] ?? null;
        if (! is_int($amount) || $amount < 0) {
            throw new LogicException('An applicable charge requires an explicit non-negative amount. Undefined is not zero.');
        }

        if (! $source->isCommissionedChargeSource() && $source !== BusinessPermitEvaluationSource::ProvisionalUat) {
            throw new LogicException('A charge proposal requires a governed default/procedure or explicit provisional_uat source.');
        }
    }
}
