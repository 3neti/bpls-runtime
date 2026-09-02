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
    ): BusinessPermitEvaluationItemRevision {
        $authorizedActorId = data_get($item->metadata, 'authorized_actor_id');
        $actorRole = $actor->role?->code;
        if (! $actor->hasRole(UserRole::Admin)
            && $authorizedActorId !== $actor->id
            && $item->responsible_party !== $actorRole) {
            throw new LogicException("This Evaluation responsibility belongs to [{$item->responsible_party}].");
        }

        $latest = $item->revisions()->with('version')->get()->sortByDesc('version.sequence')->first();
        $defaultAmount = data_get($latest?->value, 'amount_cents');
        $resolvedAmount = data_get($value, 'amount_cents');
        $isChangedCharge = $defaultAmount !== null && $resolvedAmount !== null && $defaultAmount !== $resolvedAmount;

        if ($isChangedCharge && blank($reason)) {
            throw new LogicException('Changing the proposed amount requires a reason.');
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
