<?php

use App\Actions\BuildBploRoutingTask;
use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\ProposeFeeRuleRevision;
use App\Actions\PublishFeeRuleRevision;
use App\Assessment\AssessmentCalculator;
use App\Enums\FeeDeterminationChannel;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Enums\UserPermission;
use App\Exceptions\UnsupportedAssessmentPolicy;
use App\Models\FeeRule;
use App\Models\FeeRulePublication;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDeclaration;
use App\Models\PermitApplicationLine;
use App\Models\PricingChargeGroup;
use App\Models\RevenueAccount;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

function publicationFixture(): array
{
    $actor = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewFeeRules, UserPermission::ManageFeeRules]);
    $rule = FeeRule::factory()->create(['amount_cents' => 2500, 'effective_from' => '2025-01-01']);
    $revision = app(ProposeFeeRuleRevision::class)->handle($rule, 3000, '2026-01-01', null, 'Test change', 'Test authority', $actor);

    return [$actor, $rule, $revision];
}

test('publisher can publish their own proposal once without editing the source rule', function () {
    [$actor, $rule, $revision] = publicationFixture();
    $publish = app(PublishFeeRuleRevision::class);
    $first = $publish->handle($revision, $actor);
    expect($publish->handle($revision, $actor)->id)->toBe($first->id)
        ->and($rule->fresh()->amount_cents)->toBe(2500)
        ->and($revision->fresh()->status)->toBe('proposed');
    $this->assertDatabaseCount('fee_rule_publications', 1);
    $calculator = app(AssessmentCalculator::class);
    $old = PermitApplication::factory()->create(['application_year' => 2025]);
    $new = PermitApplication::factory()->create(['application_year' => 2026]);
    expect($calculator->calculate($rule, null, $old)['amount_cents'])->toBe(2500)
        ->and($calculator->calculate($rule, null, $new)['amount_cents'])->toBe(3000)
        ->and($calculator->calculate($rule, null, $new)['rule_snapshot']['pricing_publication']['id'])->toBe($first->id);
});

test('publication refuses stale source content', function () {
    [$actor, $rule, $revision] = publicationFixture();
    $rule->update(['amount_cents' => 2600]);
    expect(fn () => app(PublishFeeRuleRevision::class)->handle($revision, $actor))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('fee_rule_publications', 0);
});

test('publication requires management permission even when called outside HTTP', function () {
    [$actor, $rule, $revision] = publicationFixture();
    $actor->syncRoles([]);
    expect(fn () => app(PublishFeeRuleRevision::class)->handle($revision, $actor))->toThrow(AuthorizationException::class);
});

test('publication evidence is append only', function () {
    [$actor, $rule, $revision] = publicationFixture();
    $publication = app(PublishFeeRuleRevision::class)->handle($revision, $actor);
    expect(fn () => $publication->update(['snapshot_sha256' => str_repeat('0', 64)]))->toThrow(LogicException::class)
        ->and(fn () => $publication->delete())->toThrow(LogicException::class);
});

test('expired publication does not silently fall back to the old price', function () {
    [$actor, $rule] = publicationFixture();
    $revision = app(ProposeFeeRuleRevision::class)->handle($rule, 3000, '2026-01-01', '2026-12-31', 'Test', 'Test', $actor);
    app(PublishFeeRuleRevision::class)->handle($revision, $actor);
    $application = PermitApplication::factory()->create(['application_year' => 2027]);
    expect(fn () => app(AssessmentCalculator::class)->calculate($rule, null, $application))->toThrow(UnsupportedAssessmentPolicy::class);
});

test('conflicting start dates are rejected and future successors are selected by tax year', function () {
    [$actor, $rule, $revision] = publicationFixture();
    app(PublishFeeRuleRevision::class)->handle($revision, $actor);
    $conflict = app(ProposeFeeRuleRevision::class)->handle($rule, 3500, '2026-01-01', null, 'Test', 'Test', $actor);
    expect(fn () => app(PublishFeeRuleRevision::class)->handle($conflict, $actor))->toThrow(ValidationException::class);
    $next = app(ProposeFeeRuleRevision::class)->handle($rule, 4000, '2027-01-01', null, 'Test', 'Test', $actor);
    app(PublishFeeRuleRevision::class)->handle($next, $actor);
    $application = PermitApplication::factory()->create(['application_year' => 2027]);
    expect(app(AssessmentCalculator::class)->calculate($rule, null, $application)['amount_cents'])->toBe(4000);
});

test('tampered evidence and drifted rules fail closed during lookup', function () {
    [$actor, $rule, $revision] = publicationFixture();
    $publication = app(PublishFeeRuleRevision::class)->handle($revision, $actor);
    FeeRulePublication::query()->whereKey($publication->id)->update(['snapshot_sha256' => str_repeat('0', 64)]);
    $application = PermitApplication::factory()->create(['application_year' => 2026]);
    expect(fn () => app(AssessmentCalculator::class)->calculate($rule, null, $application))->toThrow(UnsupportedAssessmentPolicy::class);
});

test('publication changes a new assessment but preserves every frozen field of the prior assessment', function () {
    [$actor, $rule, $revision] = publicationFixture();
    $rule->update(['scope' => FeeRuleScope::Application]);
    $oldApplication = PermitApplication::factory()->create(['application_year' => 2026]);
    $old = app(CreateAssessmentForPermitApplication::class)->handle($oldApplication, $actor);
    $before = $old->fresh()->load('lines')->toArray();
    $revision = app(ProposeFeeRuleRevision::class)->handle($rule, 3000, '2026-01-01', null, 'Synthetic revision', 'Test authority', $actor);
    app(PublishFeeRuleRevision::class)->handle($revision, $actor);
    $newApplication = PermitApplication::factory()->create(['application_year' => 2026]);
    $new = app(CreateAssessmentForPermitApplication::class)->handle($newApplication, $actor);
    expect($old->fresh()->load('lines')->toArray())->toBe($before)
        ->and($old->total_amount_cents)->toBe(2500)
        ->and($new->total_amount_cents)->toBe(3000);
});

test('published unit rates use frozen employee counts and retain group and accounting identity', function () {
    [$actor, $rule] = publicationFixture();
    $rule->update(['calculation_type' => FeeRuleCalculationType::Formula, 'basis' => 'employee_count',
        'metadata' => ['basis_unit' => 'employee', 'unit_amount_minor' => 2500]]);
    $group = PricingChargeGroup::query()->create(['code' => 'EMPLOYEE', 'name' => 'Employee fees']);
    $account = RevenueAccount::query()->create(['code' => 'TEST-EMPLOYEE', 'name' => 'Test employee fees', 'is_active' => true]);
    $revision = app(ProposeFeeRuleRevision::class)->handle($rule, 3000, '2026-01-01', null, 'Test', 'Test', $actor,
        ['group_id' => $group->id, 'revenue_account_id' => $account->id]);
    app(PublishFeeRuleRevision::class)->handle($revision, $actor);
    $application = PermitApplication::factory()->create(['application_year' => 2026]);
    PermitApplicationDeclaration::factory()->for($application)->create(['snapshot' => ['establishment' => ['total_employees' => 7]]]);
    $result = app(AssessmentCalculator::class)->calculate($rule, null, $application);
    expect($result['amount_cents'])->toBe(21000)
        ->and($result['rule_snapshot']['pricing_publication']['account']['code'])->toBe($account->code)
        ->and($result['rule_snapshot']['pricing_publication']['group']['code'])->toBe('EMPLOYEE');
});

test('published fixed amount brackets are resolved through an application line without an explicit application', function () {
    [$actor, $rule] = publicationFixture();
    $rule->update(['calculation_type' => FeeRuleCalculationType::Range, 'basis' => 'capital_investment']);
    $revision = app(ProposeFeeRuleRevision::class)->handle($rule, 0, '2026-01-01', null, 'Test', 'Test', $actor,
        ['ranges' => [
            ['min_basis_cents' => 0, 'max_basis_cents' => 10000, 'amount_cents' => 500],
            ['min_basis_cents' => 10001, 'max_basis_cents' => null, 'amount_cents' => 1000],
        ]]);
    app(PublishFeeRuleRevision::class)->handle($revision, $actor);
    $application = PermitApplication::factory()->create(['application_year' => 2026]);
    $line = PermitApplicationLine::factory()->for($application)->create(['capital_investment_cents' => 10001]);
    expect(app(AssessmentCalculator::class)->calculate($rule, $line)['amount_cents'])->toBe(1000);
});

test('overlapping brackets cannot be published', function () {
    [$actor, $rule] = publicationFixture();
    $rule->update(['calculation_type' => FeeRuleCalculationType::Range, 'basis' => 'capital_investment']);
    $revision = app(ProposeFeeRuleRevision::class)->handle($rule, 0, '2026-01-01', null, 'Test', 'Test', $actor,
        ['ranges' => [
            ['min_basis_cents' => 0, 'max_basis_cents' => 10000, 'amount_cents' => 500],
            ['min_basis_cents' => 10000, 'max_basis_cents' => null, 'amount_cents' => 1000],
        ]]);
    expect(fn () => app(PublishFeeRuleRevision::class)->handle($revision, $actor))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('fee_rule_publications', 0);
});

test('publication HTTP action and maintenance projection use the published revision', function () {
    $this->withoutVite();
    [$actor, $rule, $revision] = publicationFixture();
    $this->actingAs($actor)->post(route('staff.pricing-maintenance.publish', $revision))
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->get(route('staff.pricing-maintenance.index', ['rule' => $rule->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('selected.amount_display', '₱30.00')
            ->where('selected.revisions.0.status', 'published')
            ->where('selected.revisions.0.can_publish', false));
    $this->get(route('staff.fee-rules.index', ['q' => $rule->code, 'year' => 2026]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('feeRules.data.0.amount_display', '₱30.00'));
    $actor->syncRoles([]);
    $this->post(route('staff.pricing-maintenance.publish', $revision))->assertForbidden();
    $this->assertDatabaseCount('fee_rule_publications', 1);
});

test('fee group creation requires management and rejects duplicate identity', function () {
    [$actor] = publicationFixture();
    $this->actingAs($actor)->post(route('staff.pricing-maintenance.groups.store'), ['code' => 'TEST', 'name' => 'Test group'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('staff.pricing-maintenance.groups.store'), ['code' => 'TEST', 'name' => 'Duplicate'])
        ->assertSessionHasErrors('code');
    $actor->syncRoles([]);
    $this->post(route('staff.pricing-maintenance.groups.store'), ['code' => 'OTHER', 'name' => 'Other'])->assertForbidden();
    $this->assertDatabaseCount('pricing_charge_groups', 1);
});

test('Payment Order and Treasury selectors use their effective published defaults', function () {
    [$actor, $office] = publicationFixture();
    $office->update(['determination_channel' => FeeDeterminationChannel::ConcernedOfficePaymentOrder,
        'scope' => FeeRuleScope::Application, 'metadata' => ['responsible_office_code' => 'engineering']]);
    $lob = LineOfBusiness::factory()->create(['metadata' => []]);
    $treasury = FeeRule::factory()->create(['line_of_business_id' => $lob->id,
        'scope' => FeeRuleScope::LineOfBusiness, 'effective_from' => '2025-01-01', 'amount_cents' => 1000,
        'determination_channel' => FeeDeterminationChannel::TreasuryLineOfBusiness]);
    foreach ([[$office, 3000], [$treasury, 2000]] as [$rule, $amount]) {
        $revision = app(ProposeFeeRuleRevision::class)->handle($rule, $amount, '2026-01-01', null, 'Test', 'Test', $actor);
        app(PublishFeeRuleRevision::class)->handle($revision, $actor);
    }
    $application = PermitApplication::factory()->create(['application_year' => 2026]);
    $editor = app(BuildBploRoutingTask::class)->handle($application, null)->toArray()['financial_editor'];
    expect(collect($editor['office_fee_options']['engineering'])->firstWhere('id', $office->id)['default_amount_cents'])->toBe(3000)
        ->and(collect($editor['line_of_business_options'])->firstWhere('id', $lob->id)['default_items'][0]['amount_cents'])->toBe(2000);
});

test('other municipal employee charges publish without changing their source category', function () {
    $this->withoutVite();
    [$actor, $rule] = publicationFixture();
    $rule->update(['category' => FeeRuleCategory::Other, 'calculation_type' => FeeRuleCalculationType::Formula,
        'basis' => 'employee_count', 'scope' => FeeRuleScope::Application,
        'determination_channel' => FeeDeterminationChannel::TreasuryLineOfBusiness,
        'metadata' => ['basis_unit' => 'employee', 'unit_amount_minor' => 10000]]);
    $revision = app(ProposeFeeRuleRevision::class)->handle($rule, 11000, '2026-01-01', null, 'Test only', 'Test authorization', $actor);
    $this->actingAs($actor)->post(route('staff.pricing-maintenance.publish', $revision))
        ->assertRedirect()->assertSessionHasNoErrors();
    $application = PermitApplication::factory()->create(['application_year' => 2026]);
    PermitApplicationDeclaration::factory()->for($application)->create(['snapshot' => ['establishment' => ['total_employees' => 1]]]);
    expect(app(AssessmentCalculator::class)->calculate($rule, null, $application)['amount_cents'])->toBe(11000)
        ->and($rule->fresh()->category)->toBe(FeeRuleCategory::Other)
        ->and(data_get($rule->fresh()->metadata, 'unit_amount_minor'))->toBe(10000);
    $this->assertDatabaseCount('fee_rule_publications', 1);
});

test('publication still rejects taxes and uncharacterized other charges', function (string $category, string $method, string $basis, ?string $unit) {
    $this->withoutVite();
    [$actor, $rule] = publicationFixture();
    $rule->update(['category' => $category, 'calculation_type' => $method, 'basis' => $basis,
        'metadata' => ['basis_unit' => $unit, 'unit_amount_minor' => 10000]]);
    $revision = app(ProposeFeeRuleRevision::class)->handle($rule, 11000, '2026-01-01', null, 'Test', 'Test', $actor);
    expect(fn () => app(PublishFeeRuleRevision::class)->handle($revision, $actor))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('fee_rule_publications', 0);
    $this->actingAs($actor)->get(route('staff.pricing-maintenance.index', ['rule' => $rule->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('selected.revisions.0.can_publish', false));
})->with([
    'tax employee formula' => ['tax', 'formula', 'employee_count', 'employee'],
    'other unresolved formula' => ['other', 'formula', 'none', null],
    'other wrong unit' => ['other', 'formula', 'employee_count', 'square_meter'],
    'other fixed charge' => ['other', 'fixed', 'none', null],
]);
