<?php

use App\Assessment\PricingRuleReviewSnapshot;
use App\Enums\UserPermission;
use App\Models\FeeRule;
use App\Models\FeeRuleReconciliation;
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
