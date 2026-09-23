<?php

use App\Assessment\PricingRuleReviewSnapshot;
use App\Assessment\ReconciledFixedFeePriceAdapter;
use App\Exceptions\UnsupportedAssessmentPolicy;
use App\Models\FeeRule;
use App\Models\FeeRuleReconciliation;
use App\Models\PermitApplication;
use App\Models\PricingRuleReview;
use App\Models\RevenueAccount;
use Illuminate\Support\Facades\DB;

function reviewBindingFixture(): array
{
    $rule = FeeRule::factory()->create(['amount_cents' => 2500, 'effective_from' => '2026-01-01']);
    $decision = FeeRuleReconciliation::factory()->create(['fee_rule_id' => $rule->id, 'effective_from' => '2026-01-01']);
    $review = PricingRuleReview::factory()->create(['fee_rule_id' => $rule->id, 'fee_rule_reconciliation_id' => $decision->id]);
    $application = PermitApplication::factory()->create();

    return [$rule, $decision, $review, $application];
}

test('requires unchanged reviewed rule and decision content', function (string $target, array $changes) {
    [$rule, $decision, $review, $application] = reviewBindingFixture();
    $adapter = app(ReconciledFixedFeePriceAdapter::class);
    expect($adapter->price($application->id, $rule->id, $decision->id, $review->id)->resolve()->totalMinor())->toBe(2500);
    ($target === 'rule' ? $rule : $decision)->update($changes);
    expect(fn () => $adapter->price($application->id, $rule->id, $decision->id, $review->id))
        ->toThrow(UnsupportedAssessmentPolicy::class);
})->with([
    ['rule', ['amount_cents' => 5000]], ['rule', ['legal_basis' => 'Changed']],
    ['rule', ['metadata' => ['display' => 'Changed evidence']]],
    ['decision', ['decision_reference' => 'Different decision']],
    ['decision', ['original_text' => 'Different ordinance text']],
]);

test('preserves reviews and rejects cross-rule substitution', function () {
    [$rule, $decision, $review, $application] = reviewBindingFixture();
    expect(fn () => $review->update(['review_reference' => 'Replacement']))->toThrow(LogicException::class);
    expect(fn () => $review->delete())->toThrow(LogicException::class);
    $other = PricingRuleReview::factory()->create();
    expect(fn () => app(ReconciledFixedFeePriceAdapter::class)->price($application->id, $rule->id, $decision->id, $other->id))
        ->toThrow(UnsupportedAssessmentPolicy::class);
});

test('rejects review snapshot tampering and account drift', function () {
    [$rule, $decision, $review, $application] = reviewBindingFixture();
    $account = RevenueAccount::query()->create(['code' => 'BIND', 'name' => 'Original', 'is_active' => true]);
    $rule->update(['revenue_account_id' => $account->id]);
    $bound = PricingRuleReview::factory()->create(['fee_rule_id' => $rule->id, 'fee_rule_reconciliation_id' => $decision->id]);
    $account->update(['code' => 'CHANGED']);
    expect(fn () => app(ReconciledFixedFeePriceAdapter::class)->price($application->id, $rule->id, $decision->id, $bound->id))
        ->toThrow(UnsupportedAssessmentPolicy::class);
    DB::table('pricing_rule_reviews')->where('id', $review->id)->update(['snapshot' => '{}']);
    expect(fn () => app(ReconciledFixedFeePriceAdapter::class)->price($application->id, $rule->id, $decision->id, $review->id))
        ->toThrow(UnsupportedAssessmentPolicy::class);
});

test('does not accept incompatible or unattributed reviews', function () {
    $rule = FeeRule::factory()->create();
    expect(fn () => PricingRuleReview::factory()->create(['fee_rule_id' => $rule->id]))->toThrow(InvalidArgumentException::class);
    expect(fn () => PricingRuleReview::factory()->create(['review_reference' => ' ']))->toThrow(InvalidArgumentException::class);
});

test('hash is insensitive to key order but retains display-named evidence', function () {
    $builder = new PricingRuleReviewSnapshot;
    expect($builder->hash(['b' => 2, 'a' => 1]))->toBe($builder->hash(['a' => 1, 'b' => 2]))
        ->and($builder->hash(['display' => 'a']))->not->toBe($builder->hash(['display' => 'b']));
});
