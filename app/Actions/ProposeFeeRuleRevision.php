<?php

namespace App\Actions;

use App\Models\FeeRule;
use App\Models\FeeRuleRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ProposeFeeRuleRevision
{
    public function handle(
        FeeRule $feeRule,
        int $proposedAmountMinor,
        string $effectiveFrom,
        ?string $effectiveUntil,
        string $reason,
        string $authority,
        User $actor,
    ): FeeRuleRevision {
        return DB::transaction(function () use ($feeRule, $proposedAmountMinor, $effectiveFrom, $effectiveUntil, $reason, $authority, $actor): FeeRuleRevision {
            $lockedRule = FeeRule::query()->whereKey($feeRule->id)->lockForUpdate()->firstOrFail();
            $version = (int) $lockedRule->revisions()->max('version') + 1;
            $proposedAt = now();
            $snapshot = [
                'fee_identity' => ['id' => $lockedRule->id, 'code' => $lockedRule->code, 'name' => $lockedRule->name],
                'version' => $version,
                'currency' => 'PHP',
                'previous_amount_minor' => $lockedRule->amount_cents,
                'proposed_amount_minor' => $proposedAmountMinor,
                'effective_from' => $effectiveFrom,
                'effective_until' => $effectiveUntil,
                'reason' => $reason,
                'authority' => $authority,
                'actor_id' => $actor->id,
                'proposed_at' => $proposedAt->toIso8601String(),
                'status' => 'proposed',
                'execution' => 'not_executable',
            ];
            $revision = $lockedRule->revisions()->create([
                'version' => $version,
                'status' => 'proposed',
                'currency' => 'PHP',
                'previous_amount_minor' => $lockedRule->amount_cents,
                'proposed_amount_minor' => $proposedAmountMinor,
                'effective_from' => $effectiveFrom,
                'effective_until' => $effectiveUntil,
                'reason' => $reason,
                'authority' => $authority,
                'proposed_by_id' => $actor->id,
                'proposed_at' => $proposedAt,
                'snapshot' => $snapshot,
            ]);
            $lockedRule->auditEvents()->create([
                'fee_rule_revision_id' => $revision->id,
                'event' => 'revision_proposed',
                'actor_id' => $actor->id,
                'occurred_at' => $proposedAt,
                'snapshot' => $snapshot,
            ]);

            return $revision;
        });
    }
}
