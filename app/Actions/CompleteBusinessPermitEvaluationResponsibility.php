<?php

namespace App\Actions;

use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationRevisionAction;
use App\Enums\BusinessPermitEvaluationSource;
use App\Enums\UserRole;
use App\Models\BusinessPermitEvaluationItem;
use App\Models\BusinessPermitEvaluationItemRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class CompleteBusinessPermitEvaluationResponsibility
{
    public function __construct(
        private readonly ReviseBusinessPermitEvaluationItem $reviseItem,
        private readonly IssuePaperlessPaymentOrder $issuePaymentOrder,
    ) {}

    /** @param array<string, mixed>|null $value */
    public function handle(
        BusinessPermitEvaluationItem $item,
        User $actor,
        BusinessPermitEvaluationApplicability $applicability,
        ?array $value,
        BusinessPermitEvaluationSource $source,
        ?string $reason,
        int $expectedVersionSequence,
        string $expectedFingerprint,
        string $idempotencyKey,
        ?string $authority = null,
    ): BusinessPermitEvaluationItemRevision {
        $authorizedActorId = data_get($item->metadata, 'authorized_actor_id');
        if (! $actor->hasRole(UserRole::Admin)
            && $authorizedActorId !== $actor->id
            && ! $actor->hasRole($item->responsible_party)) {
            throw new LogicException("This Evaluation responsibility belongs to [{$item->responsible_party}].");
        }

        $defaultRevision = $item->revisions()->with('version')->get()->first(
            fn (BusinessPermitEvaluationItemRevision $revision): bool => $revision->action === BusinessPermitEvaluationRevisionAction::Proposal,
        );
        $defaultAmount = data_get($defaultRevision?->value, 'amount_cents');
        $resolvedAmount = data_get($value, 'amount_cents');
        $isChangedCharge = $defaultAmount !== null && $resolvedAmount !== null && $defaultAmount !== $resolvedAmount;

        if ($isChangedCharge && blank($reason)) {
            throw new LogicException('Changing the proposed amount requires a reason.');
        }

        if ($isChangedCharge && blank($authority) && $source !== BusinessPermitEvaluationSource::ProvisionalUat) {
            throw new LogicException('A case override requires its authority or policy basis.');
        }

        if ($item->item_type->value === 'charge') {
            $determinationType = match (true) {
                $applicability === BusinessPermitEvaluationApplicability::NotApplicable => 'not_applicable',
                $defaultAmount === null => 'office_determination',
                $isChangedCharge => 'override',
                default => 'confirm',
            };
            $value ??= [];
            $value['determination'] = [
                'type' => $determinationType,
                'currency' => 'PHP',
                'scheduled_amount_minor' => is_int($defaultAmount) ? $defaultAmount : null,
                'determined_amount_minor' => is_int($resolvedAmount) ? $resolvedAmount : null,
                'variance_minor' => is_int($defaultAmount) && is_int($resolvedAmount) ? $resolvedAmount - $defaultAmount : null,
                'reason' => $reason,
                'authority' => $authority ?? ($source === BusinessPermitEvaluationSource::ProvisionalUat
                    ? 'Synthetic provisional UAT specimen — not municipal policy'
                    : null),
                'actor_id' => $actor->id,
                'office' => $item->responsible_party,
                'occurred_at' => now()->toIso8601String(),
            ];
        }

        return DB::transaction(function () use ($item, $actor, $applicability, $value, $source, $reason, $expectedVersionSequence, $expectedFingerprint, $idempotencyKey, $isChangedCharge): BusinessPermitEvaluationItemRevision {
            $revision = $this->reviseItem->handle(
                $item,
                $isChangedCharge
                    ? BusinessPermitEvaluationRevisionAction::Correction
                    : BusinessPermitEvaluationRevisionAction::Confirmation,
                $applicability,
                $value,
                $source,
                $actor,
                $reason,
                $expectedVersionSequence,
                $expectedFingerprint,
                $idempotencyKey,
            );

            if ($item->item_type->value === 'charge'
                && $applicability === BusinessPermitEvaluationApplicability::Applicable
                && data_get($item->metadata, 'bplo_routing_work_id') !== null) {
                $this->issuePaymentOrder->handle($item, $revision, $actor);
            }

            return $revision;
        });
    }
}
