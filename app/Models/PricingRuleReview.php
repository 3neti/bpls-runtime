<?php

namespace App\Models;

use App\Assessment\PricingRuleReviewSnapshot;
use Database\Factories\PricingRuleReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

/** Immutable content binding; does not grant execution status or municipal authority. */
class PricingRuleReview extends Model
{
    /** @use HasFactory<PricingRuleReviewFactory> */
    use HasFactory;

    protected $fillable = ['fee_rule_id', 'fee_rule_reconciliation_id', 'recorded_by', 'review_reference'];

    protected static function booted(): void
    {
        static::creating(function (self $review): void {
            foreach (['fee_rule_id', 'fee_rule_reconciliation_id', 'recorded_by'] as $field) {
                $id = $review->getAttribute($field);
                if (! is_int($id) || $id < 1) {
                    throw new InvalidArgumentException('Review references require explicit positive integer identities.');
                }
            }
            $reference = $review->getAttribute('review_reference');
            if (! is_string($reference) || trim($reference) === '' || strlen($reference) > 1000) {
                throw new InvalidArgumentException('Review reference is required.');
            }
            $rule = FeeRule::query()->whereKey($review->getAttribute('fee_rule_id'))->firstOrFail();
            $decision = FeeRuleReconciliation::query()->whereKey($review->getAttribute('fee_rule_reconciliation_id'))->firstOrFail();
            User::query()->whereKey($review->getAttribute('recorded_by'))->firstOrFail();
            if ($decision->fee_rule_id !== $rule->id) {
                throw new InvalidArgumentException('Review decision belongs to a different fee rule.');
            }
            $builder = new PricingRuleReviewSnapshot;
            $snapshot = $builder->capture($rule, $decision);
            $review->setAttribute('snapshot', $snapshot);
            $review->setAttribute('snapshot_sha256', $builder->hash($snapshot));
        });
        static::updating(function (): never {
            throw new LogicException('Rule reviews are append-only; record a new review.');
        });
        static::deleting(function (): never {
            throw new LogicException('Rule reviews must be retained.');
        });
    }

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }
}
