<?php

use App\Actions\ProposeFeeRuleRevision;
use App\Assessment\PricingRuleReviewSnapshot;
use App\Enums\UserPermission;
use App\Models\FeeRule;
use App\Models\FeeRuleReconciliation;
use App\Models\FeeRuleRevision;
use App\Models\PricingRuleReview;
use Inertia\Testing\AssertableInertia as Assert;

test('pricing workspace requires permission', function () {
    $this->get(route('staff.pricing-maintenance.index'))->assertRedirect(route('login'));
    $this->actingAs(userWithPermissions([]))->get(route('staff.pricing-maintenance.index'))->assertForbidden();
});

test('workspace searches and shows current review content without claiming approval', function () {
    $actor = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewFeeRules]);
    $rule = FeeRule::factory()->create(['name' => 'Laminated ID', 'amount_cents' => 2500]);
    FeeRule::factory()->create(['name' => 'Other fee']);
    FeeRuleReconciliation::factory()->create(['fee_rule_id' => $rule->id]);
    $this->actingAs($actor)->get(route('staff.pricing-maintenance.index', ['search' => 'Laminated']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('pricing-maintenance/Index')
        ->has('rules.data', 1)->where('selected.id', $rule->id)->where('selected.review_status', 'Not recorded')
        ->where('canManage', false));
    $this->assertDatabaseCount('pricing_rule_reviews', 0);
});

test('authorized content review is idempotent and never edits the price', function () {
    $actor = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewFeeRules, UserPermission::ManageFeeRules]);
    $rule = FeeRule::factory()->create(['amount_cents' => 2500]);
    $decision = FeeRuleReconciliation::factory()->create(['fee_rule_id' => $rule->id]);
    $snapshots = app(PricingRuleReviewSnapshot::class);
    $payload = ['snapshot_sha256' => $snapshots->hash($snapshots->capture($rule->fresh(), $decision->fresh())), 'reconciliation_id' => $decision->id, 'review_reference' => 'Test review'];
    $url = route('staff.pricing-maintenance.reviews.store', $rule);
    $this->actingAs($actor)->post($url, $payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->post($url, $payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->assertDatabaseCount('pricing_rule_reviews', 1);
    expect(PricingRuleReview::query()->first()->recorded_by)->toBe($actor->id)
        ->and($rule->fresh()->amount_cents)->toBe(2500);
    $this->get(route('staff.pricing-maintenance.index', ['rule' => $rule->id]))
        ->assertInertia(fn (Assert $page) => $page->where('selected.review_status', 'Current content recorded'));
});

test('stale content is rejected and displayed as changed', function () {
    $actor = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewFeeRules, UserPermission::ManageFeeRules]);
    $rule = FeeRule::factory()->create();
    $decision = FeeRuleReconciliation::factory()->create(['fee_rule_id' => $rule->id]);
    $review = PricingRuleReview::factory()->create(['fee_rule_id' => $rule->id, 'fee_rule_reconciliation_id' => $decision->id]);
    $rule->update(['amount_cents' => $rule->amount_cents + 1]);
    $this->actingAs($actor)->post(route('staff.pricing-maintenance.reviews.store', $rule), [
        'snapshot_sha256' => $review->snapshot_sha256, 'reconciliation_id' => $decision->id, 'review_reference' => 'Stale',
    ])->assertSessionHasErrors('snapshot_sha256');
    $this->assertDatabaseCount('pricing_rule_reviews', 1);
    $this->get(route('staff.pricing-maintenance.index', ['rule' => $rule->id]))
        ->assertInertia(fn (Assert $page) => $page->where('selected.review_status', 'Changed since review'));
});

test('view permission cannot record a review and empty search is honest', function () {
    $rule = FeeRule::factory()->create();
    $actor = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewFeeRules]);
    $this->actingAs($actor)->post(route('staff.pricing-maintenance.reviews.store', $rule), [])->assertForbidden();
    $this->get(route('staff.pricing-maintenance.index', ['search' => 'no-such-fee']))
        ->assertInertia(fn (Assert $page) => $page->has('rules.data', 0)->where('selected', null));
});

test('review validation rejects missing evidence and a superseded decision', function () {
    $actor = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewFeeRules, UserPermission::ManageFeeRules]);
    $rule = FeeRule::factory()->create();
    $decision = FeeRuleReconciliation::factory()->create(['fee_rule_id' => $rule->id]);
    $snapshots = app(PricingRuleReviewSnapshot::class);
    $hash = $snapshots->hash($snapshots->capture($rule->fresh(), $decision->fresh()));
    FeeRuleReconciliation::factory()->create(['fee_rule_id' => $rule->id, 'version' => 2]);
    $this->actingAs($actor)->post(route('staff.pricing-maintenance.reviews.store', $rule), [])->assertSessionHasErrors(['review_reference', 'snapshot_sha256', 'reconciliation_id']);
    $this->post(route('staff.pricing-maintenance.reviews.store', $rule), ['snapshot_sha256' => $hash, 'reconciliation_id' => $decision->id, 'review_reference' => 'Older version'])->assertSessionHasErrors('snapshot_sha256');
    $this->assertDatabaseCount('pricing_rule_reviews', 0);
});

test('workspace records an exact centavo proposal and displays it without changing current pricing', function () {
    $actor = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewFeeRules, UserPermission::ManageFeeRules]);
    $rule = FeeRule::factory()->create(['amount_cents' => 2500]);
    $workspace = route('staff.pricing-maintenance.index', ['rule' => $rule->id]);
    $this->actingAs($actor)->from($workspace)->post(route('staff.fee-rules.revisions.store', $rule), [
        'proposed_amount_minor' => 2915,
        'effective_from' => '2027-01-01', 'effective_until' => '2027-12-31',
        'reason' => 'Synthetic proposal test', 'authority' => 'Test reference only',
    ])->assertSessionHasNoErrors()->assertRedirect($workspace);
    expect($rule->fresh()->amount_cents)->toBe(2500)
        ->and(FeeRuleRevision::query()->sole()->proposed_by_id)->toBe($actor->id);
    $this->assertDatabaseCount('fee_rule_revisions', 1);
    $this->get($workspace)->assertInertia(fn (Assert $page) => $page
        ->has('selected.revisions', 1)
        ->where('selected.revisions.0.amount_display', '₱29.15')
        ->where('selected.revisions.0.status', 'proposed')
        ->where('selected.revisions.0.effective_until', '2027-12-31')
        ->where('selected.revisions.0.authority', 'Test reference only'));
});

test('proposal endpoint rejects readonly users', function () {
    $rule = FeeRule::factory()->create(['amount_cents' => 2500]);
    $url = route('staff.fee-rules.revisions.store', $rule);
    $this->actingAs(userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewFeeRules]))
        ->post($url, [])->assertForbidden();
    $this->assertDatabaseCount('fee_rule_revisions', 0);
});

test('workspace shows the newest five proposals for only the selected rule', function () {
    $actor = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewFeeRules, UserPermission::ManageFeeRules]);
    $rule = FeeRule::factory()->create();
    $other = FeeRule::factory()->create();
    $propose = app(ProposeFeeRuleRevision::class);
    foreach (range(1, 6) as $version) {
        $propose->handle($rule, $version * 100, '2027-01-01', null, 'Test', 'Test authority', $actor);
    }
    $propose->handle($other, 999, '2027-01-01', null, 'Other fee', 'Other reference', $actor);
    $this->actingAs($actor)->get(route('staff.pricing-maintenance.index', ['rule' => $rule->id]))
        ->assertInertia(fn (Assert $page) => $page->has('selected.revisions', 5)
            ->where('selected.revisions.0.version', 6)->where('selected.revisions.4.version', 2));
});

test('proposal endpoint rejects invalid input without changing pricing', function () {
    $rule = FeeRule::factory()->create(['amount_cents' => 2500]);
    $url = route('staff.fee-rules.revisions.store', $rule);
    $this->actingAs(userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewFeeRules, UserPermission::ManageFeeRules]))
        ->post($url, ['proposed_amount_minor' => -1, 'effective_from' => '2027-01-01', 'effective_until' => '2026-12-31'])
        ->assertSessionHasErrors(['proposed_amount_minor', 'effective_until', 'reason', 'authority']);
    $this->assertDatabaseCount('fee_rule_revisions', 0);
    expect($rule->fresh()->amount_cents)->toBe(2500);
});
