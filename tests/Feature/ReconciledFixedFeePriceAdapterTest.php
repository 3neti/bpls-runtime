<?php

use App\Assessment\Price\Price;
use App\Assessment\ReconciledFixedFeePriceAdapter;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleExecutionStatus;
use App\Enums\PermitApplicationType;
use App\Exceptions\UnsupportedAssessmentPolicy;
use App\Models\FeeRule;
use App\Models\FeeRuleReconciliation;
use App\Models\PermitApplication;
use App\Models\PricingRuleReview;
use App\Models\RevenueAccount;

function reviewedFixedPrice(int $applicationId, int $ruleId, int $decisionId): Price
{
    $current = FeeRule::query()->findOrFail($ruleId)->currentReconciliation;
    $review = PricingRuleReview::factory()->create($current === null ? [] : [
        'fee_rule_id' => $ruleId, 'fee_rule_reconciliation_id' => $current->id,
    ]);

    return app(ReconciledFixedFeePriceAdapter::class)->price($applicationId, $ruleId, $decisionId, $review->id);
}

function reconciledFixedFixture(): array
{
    $application = PermitApplication::factory()->create(['application_year' => 2026]);
    $rule = FeeRule::factory()->create(['amount_cents' => 2500, 'effective_from' => '2026-01-01', 'metadata' => ['reconciliation_required' => true]]);
    $reconciliation = FeeRuleReconciliation::factory()->create(['fee_rule_id' => $rule->id, 'effective_from' => '2026-01-01']);

    return [$application, $rule, $reconciliation];
}

test('adapts a reconciled fixed fee through the existing calculator and PriceReport without writing transactions', function () {
    [$application, $rule, $reconciliation] = reconciledFixedFixture();
    $account = RevenueAccount::query()->create(['code' => '001-02', 'name' => 'Test account', 'is_active' => true]);
    $rule->update(['revenue_account_id' => $account->id]);
    $price = reviewedFixedPrice($application->id, $rule->id, $reconciliation->id);
    $report = $price->report()->toArray();
    expect($price->resolve()->totalMinor())->toBe(2500)
        ->and($report['total']['minor'])->toBe(2500)
        ->and($report['components'][0]['exact_once_key'])->toBe("fee_rule:{$rule->id}:application")
        ->and($report['components'][0]['explanation']['revenue_account']['code'])->toBe('001-02');
    $account->update(['code' => 'CHANGED']);
    $rule->update(['amount_cents' => 9000]);
    expect($price->report()->toArray())->toBe($report);
    $this->assertDatabaseCount('assessments', 0);
});

test('preserves explicit zero after reconciliation', function () {
    [$application, $rule, $reconciliation] = reconciledFixedFixture();
    $rule->update(['amount_cents' => 0]);
    expect(reviewedFixedPrice($application->id, $rule->id, $reconciliation->id)->resolve()->totalMinor())->toBe(0);
});

test('refuses inactive inapplicable nonfixed and tax rules', function (array $changes) {
    [$application, $rule, $reconciliation] = reconciledFixedFixture();
    $rule->update($changes);
    expect(fn () => reviewedFixedPrice($application->id, $rule->id, $reconciliation->id))
        ->toThrow(UnsupportedAssessmentPolicy::class);
})->with([
    [['is_active' => false]], [['effective_from' => '2027-01-01']],
    [['effective_until' => '2025-12-31']], [['basis' => 'office_determination']],
    [['category' => FeeRuleCategory::Tax]], [['calculation_type' => FeeRuleCalculationType::Formula]],
    [['metadata' => ['application_types' => ['renewal']]]],
]);

test('requires exact current reconciliation and refuses a later blocked decision', function () {
    [$application, $rule, $reconciliation] = reconciledFixedFixture();
    $later = FeeRuleReconciliation::factory()->create([
        'fee_rule_id' => $rule->id, 'version' => 2, 'execution_status' => FeeRuleExecutionStatus::Blocked,
    ]);
    foreach ([$reconciliation->id, $later->id] as $id) {
        expect(fn () => reviewedFixedPrice($application->id, $rule->id, $id))
            ->toThrow(UnsupportedAssessmentPolicy::class);
    }
});

test('requires complete effective decision evidence', function (array $changes) {
    [$application, $rule, $reconciliation] = reconciledFixedFixture();
    $reconciliation->update($changes);
    expect(fn () => reviewedFixedPrice($application->id, $rule->id, $reconciliation->id))
        ->toThrow(UnsupportedAssessmentPolicy::class);
})->with([
    [['decision_reference' => null]], [['decision_authority' => ' ']], [['decided_at' => null]],
    [['decided_at' => now()->addYear()]], [['effective_from' => '2027-01-01']],
    [['effective_until' => '2025-12-31']],
]);

test('does not authorize renewal through this adapter', function () {
    [$application, $rule, $reconciliation] = reconciledFixedFixture();
    $application->update(['type' => PermitApplicationType::Renewal]);
    expect(fn () => reviewedFixedPrice($application->id, $rule->id, $reconciliation->id))
        ->toThrow(UnsupportedAssessmentPolicy::class);
});

test('does not use the legacy reconciliation-optional shortcut', function () {
    $application = PermitApplication::factory()->create();
    $rule = FeeRule::factory()->create(['metadata' => [], 'effective_from' => '2026-01-01']);
    expect(fn () => reviewedFixedPrice($application->id, $rule->id, 999999))
        ->toThrow(UnsupportedAssessmentPolicy::class);
});

test('refuses a reconciliation belonging to a different rule', function () {
    [$application, $rule] = reconciledFixedFixture();
    $unrelated = FeeRuleReconciliation::factory()->create();
    expect(fn () => reviewedFixedPrice($application->id, $rule->id, $unrelated->id))
        ->toThrow(UnsupportedAssessmentPolicy::class);
});
