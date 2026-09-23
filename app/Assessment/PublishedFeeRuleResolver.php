<?php

namespace App\Assessment;

use App\Exceptions\UnsupportedAssessmentPolicy;
use App\Models\FeeRule;
use App\Models\FeeRulePublication;
use App\Models\FeeRuleRange;
use App\Models\RevenueAccount;
use Illuminate\Database\Eloquent\Collection;

final class PublishedFeeRuleResolver
{
    public function __construct(private readonly PricingRuleReviewSnapshot $hashes, private readonly PricingPublicationSnapshot $snapshots) {}

    public function forYear(FeeRule $rule, int $year): FeeRule
    {
        $date = sprintf('%04d-01-01', $year);
        $publication = FeeRulePublication::query()->where('fee_rule_id', $rule->id)
            ->whereDate('effective_from', '<=', $date)->orderByDesc('effective_from')->first();
        if ($publication === null) {
            return $rule;
        }
        $snapshot = $publication->getAttribute('snapshot');
        $current = $rule->fresh();
        if ($current === null || ! $current->is_active
            || ($publication->effective_until !== null && $publication->effective_until->toDateString() < $date)
            || ! hash_equals($publication->snapshot_sha256, $this->hashes->hash($snapshot))
            || $this->hashes->hash($this->snapshots->capture($current)) !== $snapshot['revision']['snapshot']['publication_base_sha256']) {
            throw new UnsupportedAssessmentPolicy('The published price is expired or its source/evidence changed. Review pricing before continuing.');
        }
        $resolved = clone $current;
        $definition = $snapshot['definition'];
        if ($definition['account'] !== null && ! RevenueAccount::query()->whereKey($definition['account']['id'])->where('is_active', true)->exists()) {
            throw new UnsupportedAssessmentPolicy('The published revenue account is inactive or missing. Review its mapping before continuing.');
        }
        $resolved->amount_cents = $definition['amount_minor'];
        $metadata = $current->metadata ?? [];
        if ($definition['method'] === 'formula') {
            $metadata['unit_amount_minor'] = $definition['amount_minor'];
        }
        if ($definition['method'] === 'range') {
            $resolved->setRelation('ranges', new Collection(array_map(
                fn (array $range) => new FeeRuleRange([...$range, 'fee_rule_id' => $rule->id, 'rate_basis_points' => null]), $definition['ranges'],
            )));
        }
        $resolved->setAttribute('revenue_account_id', $definition['account']['id'] ?? null);
        $resolved->setRelation('revenueAccount', $definition['account'] === null ? null : (new RevenueAccount)->forceFill($definition['account']));
        $resolved->metadata = [...$metadata, 'pricing_publication' => [
            'id' => $publication->id, 'revision_id' => $publication->fee_rule_revision_id,
            'sha256' => $publication->snapshot_sha256, 'selection_date' => $date,
            'effective_from' => $publication->effective_from->toDateString(),
            'effective_until' => $publication->effective_until?->toDateString(),
            'authority' => $snapshot['revision']['authority'], 'account' => $definition['account'], 'group' => $definition['group'],
        ]];

        return $resolved;
    }
}
