<?php

namespace App\Actions;

use App\Assessment\PricingRuleReviewSnapshot;
use App\Enums\UserPermission;
use App\Models\FeeRule;
use App\Models\PricingRuleReview;
use App\Models\RevenueAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RecordPricingRuleReview
{
    public function __construct(private readonly PricingRuleReviewSnapshot $snapshots) {}

    public function handle(FeeRule $rule, User $actor, int $decisionId, string $fingerprint, string $reference): PricingRuleReview
    {
        Gate::forUser($actor)->authorize(UserPermission::ManageFeeRules->value);
        Gate::forUser($actor)->authorize(UserPermission::ViewFeeRules->value);

        return DB::transaction(function () use ($rule, $actor, $decisionId, $fingerprint, $reference): PricingRuleReview {
            $locked = FeeRule::query()->whereKey($rule->id)->lockForUpdate()->firstOrFail();
            $decision = $locked->reconciliations()->orderByDesc('version')->lockForUpdate()->first();
            $account = $locked->revenue_account_id === null ? null : RevenueAccount::query()->whereKey($locked->revenue_account_id)->lockForUpdate()->firstOrFail();
            $locked->setRelation('revenueAccount', $account);
            if ($decision === null || $decision->id !== $decisionId
                || ! hash_equals($this->snapshots->hash($this->snapshots->capture($locked, $decision)), $fingerprint)) {
                throw ValidationException::withMessages(['snapshot_sha256' => 'This rule changed. Reload and review the current version.']);
            }
            if (trim($reference) === '' || mb_strlen($reference) > 1000) {
                throw ValidationException::withMessages(['review_reference' => 'Enter a review reference (up to 1,000 characters).']);
            }

            $existing = PricingRuleReview::query()->where([
                'fee_rule_id' => $locked->id,
                'fee_rule_reconciliation_id' => $decision->id,
                'recorded_by' => $actor->id,
                'review_reference' => trim($reference),
                'snapshot_sha256' => $fingerprint,
            ])->first();

            return $existing ?? PricingRuleReview::query()->create([
                'fee_rule_id' => $locked->id,
                'fee_rule_reconciliation_id' => $decision->id,
                'recorded_by' => $actor->id,
                'review_reference' => trim($reference),
            ]);
        });
    }
}
