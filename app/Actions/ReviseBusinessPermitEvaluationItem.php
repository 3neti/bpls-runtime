<?php

namespace App\Actions;

use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationItemType;
use App\Enums\BusinessPermitEvaluationRevisionAction;
use App\Enums\BusinessPermitEvaluationSource;
use App\Evaluation\BusinessPermitEvaluationVersioner;
use App\Models\BusinessPermitEvaluationItem;
use App\Models\BusinessPermitEvaluationItemRevision;
use App\Models\BusinessPermitEvaluationVersion;
use App\Models\User;
use LogicException;

class ReviseBusinessPermitEvaluationItem
{
    public function __construct(private readonly BusinessPermitEvaluationVersioner $versioner) {}

    /** @param array<string, mixed>|null $value */
    public function handle(
        BusinessPermitEvaluationItem $item,
        BusinessPermitEvaluationRevisionAction $action,
        BusinessPermitEvaluationApplicability $applicability,
        ?array $value,
        BusinessPermitEvaluationSource $source,
        User $actor,
        ?string $reason = null,
        ?int $expectedVersionSequence = null,
        ?string $expectedFingerprint = null,
        ?string $idempotencyKey = null,
    ): BusinessPermitEvaluationItemRevision {
        if ($idempotencyKey !== null) {
            $existing = BusinessPermitEvaluationItemRevision::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing instanceof BusinessPermitEvaluationItemRevision) {
                if ($existing->business_permit_evaluation_item_id !== $item->id) {
                    throw new LogicException('The idempotency key is already bound to different Evaluation work.');
                }

                return $existing;
            }
        }
        if ($action === BusinessPermitEvaluationRevisionAction::AuthorizedDetermination && blank($reason)) {
            throw new LogicException('An authorized determination requires a reason.');
        }

        if ($item->item_type === BusinessPermitEvaluationItemType::Charge
            && $applicability === BusinessPermitEvaluationApplicability::Applicable) {
            $amount = $value['amount_cents'] ?? null;
            if (! is_int($amount) || $amount < 0) {
                throw new LogicException('An applicable charge revision requires an explicit non-negative amount. Undefined is not zero.');
            }
            if (! $source->isCommissionedChargeSource() && $source !== BusinessPermitEvaluationSource::ProvisionalUat) {
                throw new LogicException('A charge revision requires a governed default/procedure or explicit provisional_uat source.');
            }
        }

        $revisionId = null;
        $this->versioner->create(
            $item->evaluation,
            $actor,
            'evaluation_item_revision_recorded',
            function (BusinessPermitEvaluationVersion $version) use ($item, $action, $applicability, $value, $source, $actor, $reason, $idempotencyKey, $expectedFingerprint, &$revisionId): void {
                $revisionId = $item->revisions()->create([
                    'business_permit_evaluation_version_id' => $version->id,
                    'action' => $action,
                    'applicability' => $applicability,
                    'value' => $value,
                    'source_classification' => $source,
                    'idempotency_key' => $idempotencyKey,
                    'dependency_fingerprint' => $expectedFingerprint,
                    'actor_id' => $actor->id,
                    'reason' => $reason,
                    'occurred_at' => now(),
                ])->id;
            },
            $expectedVersionSequence,
            $expectedFingerprint,
        );

        return BusinessPermitEvaluationItemRevision::query()->findOrFail($revisionId);
    }
}
