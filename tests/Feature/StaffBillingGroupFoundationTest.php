<?php

use App\Actions\CreateBillingGroupDraftRecord;
use App\Actions\DescribeBillingGroupFinancialReadiness;
use App\Actions\RequireBillingGroupFinancialReadiness;
use App\Enums\BillingGroupAcceptanceStatus;
use App\Enums\BillingGroupFieldType;
use App\Enums\BillingGroupRecordStatus;
use App\Enums\UserPermission;
use App\Exceptions\UnsupportedBillingGroupFinancialPolicy;
use App\Models\BillingGroup;
use App\Models\BillingGroupField;
use App\Models\BillingGroupRecord;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

test('authorized staff can record a provisional billing group definition and ordered fields', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewBillingGroups,
        UserPermission::ManageBillingGroups,
        UserPermission::ViewBillingGroupRecords,
    ]);

    $response = $this->actingAs($user)->post(route('staff.billing-groups.store'), [
        'name' => 'Local Service Records',
        'description' => 'A provisional non-permit record schema.',
        'fields' => [
            [
                'key' => 'account_name',
                'name' => 'Account Name',
                'field_type' => BillingGroupFieldType::Text->value,
                'is_required' => true,
                'is_unique' => false,
                'options' => [],
                'placeholder' => 'Name on record',
                'default_value' => null,
            ],
            [
                'key' => 'classification',
                'name' => 'Classification',
                'field_type' => BillingGroupFieldType::Dropdown->value,
                'is_required' => false,
                'is_unique' => false,
                'options' => ['Local', 'Other'],
                'placeholder' => null,
                'default_value' => 'Local',
            ],
        ],
    ]);

    $billingGroup = BillingGroup::query()->with('fields')->sole();

    $response->assertRedirect(route('staff.billing-groups.show', $billingGroup));
    expect($billingGroup->acceptance_status)->toBe(BillingGroupAcceptanceStatus::Provisional)
        ->and($billingGroup->metadata['policy_boundary'])->toBe('not_accepted_as_a_tor_treasury_module')
        ->and($billingGroup->fields->pluck('key')->all())->toBe(['account_name', 'classification'])
        ->and($billingGroup->fields->pluck('sort_order')->all())->toBe([1, 2]);
});

test('billing group definition validation rejects duplicate keys and dropdowns without options', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewBillingGroups,
        UserPermission::ManageBillingGroups,
    ]);

    $this->actingAs($user)->post(route('staff.billing-groups.store'), [
        'name' => 'Invalid Definition',
        'fields' => [
            ['key' => 'duplicate', 'name' => 'First', 'field_type' => 'text', 'is_required' => false, 'is_unique' => false],
            ['key' => 'duplicate', 'name' => 'Second', 'field_type' => 'dropdown', 'is_required' => false, 'is_unique' => false, 'options' => []],
        ],
    ])->assertSessionHasErrors(['fields.1.key', 'fields.1.options']);

    expect(BillingGroup::query()->count())->toBe(0);
});

test('authorized staff can prepare an incomplete draft record without financial effects', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewBillingGroups,
        UserPermission::ViewBillingGroupRecords,
        UserPermission::CreateBillingGroupRecords,
    ]);
    $billingGroup = BillingGroup::factory()->create();
    $requiredField = BillingGroupField::factory()->for($billingGroup)->create([
        'key' => 'permit_holder',
        'name' => 'Permit Holder',
        'field_type' => BillingGroupFieldType::Text,
        'is_required' => true,
        'sort_order' => 1,
    ]);
    BillingGroupField::factory()->for($billingGroup)->create([
        'key' => 'record_kind',
        'name' => 'Record Kind',
        'field_type' => BillingGroupFieldType::Dropdown,
        'options' => ['Certification', 'Inspection'],
        'sort_order' => 2,
    ]);

    $response = $this->actingAs($user)->post(route('staff.billing-groups.records.store', $billingGroup), [
        'description' => 'Prepared for later municipal review.',
        'record_date' => '2026-08-16',
        'payor_name' => 'Sample Payor',
        'field_values' => [
            'record_kind' => 'Certification',
        ],
    ]);

    $record = BillingGroupRecord::query()->sole();

    $response->assertRedirect(route('staff.billing-groups.show', $billingGroup));
    expect($record->status)->toBe(BillingGroupRecordStatus::Draft)
        ->and($record->draft_reference)->toStartWith('BGRD-')
        ->and($record->field_values)->toBe(['record_kind' => 'Certification'])
        ->and($record->schema_snapshot)->toHaveCount(2)
        ->and($record->schema_snapshot[0]['field_id'])->toBe($requiredField->id)
        ->and($record->source_snapshot['financial_effect'])->toBe('none')
        ->and(TreasuryCollection::query()->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe(0);
});

test('draft record action rejects unknown fields and invalid configured values', function () {
    $user = userWithPermissions([UserPermission::AccessStaff]);
    $billingGroup = BillingGroup::factory()->create();
    BillingGroupField::factory()->for($billingGroup)->create([
        'key' => 'classification',
        'name' => 'Classification',
        'field_type' => BillingGroupFieldType::Dropdown,
        'options' => ['Known'],
        'sort_order' => 1,
    ]);
    $action = app(CreateBillingGroupDraftRecord::class);

    expect(fn () => $action->handle($billingGroup, $user, [
        'field_values' => ['unknown' => 'value'],
    ]))->toThrow(ValidationException::class);

    expect(fn () => $action->handle($billingGroup, $user, [
        'field_values' => ['classification' => 'Invented'],
    ]))->toThrow(ValidationException::class)
        ->and(BillingGroupRecord::query()->count())->toBe(0);
});

test('financial readiness identifies record facts and unresolved municipal policy separately', function () {
    $user = userWithPermissions([UserPermission::AccessStaff]);
    $billingGroup = BillingGroup::factory()->create();
    BillingGroupField::factory()->for($billingGroup)->create([
        'key' => 'account_name',
        'name' => 'Account Name',
        'is_required' => true,
        'sort_order' => 1,
    ]);
    BillingGroupField::factory()->for($billingGroup)->create([
        'key' => 'reference_number',
        'name' => 'Reference Number',
        'is_unique' => true,
        'sort_order' => 2,
    ]);
    $record = app(CreateBillingGroupDraftRecord::class)->handle($billingGroup, $user, [
        'payor_name' => 'Recorded text only',
        'field_values' => ['reference_number' => 'REF-001'],
    ]);

    $readiness = app(DescribeBillingGroupFinancialReadiness::class)->handle($record);

    expect($readiness)
        ->status->toBe('blocked')
        ->can_create_liability->toBeFalse()
        ->can_collect->toBeFalse()
        ->can_issue_receipt->toBeFalse()
        ->missing_required_fields->toBe(['account_name'])
        ->unique_fields_requiring_policy->toBe(['reference_number'])
        ->schema_matches_current_definition->toBeTrue()
        ->and($readiness['blocked_by'])->toContain(
            'billing_group_acceptance',
            'required_field_readiness',
            'unique_field_policy',
            'fee_amount_authority',
            'payor_identity_policy',
            'revenue_account_mapping',
            'fund_classification',
            'receipt_numbering_authority',
            'receipt_void_and_reversal_policy',
            'production_configuration_reconciliation',
        );
});

test('financial execution guard refuses a provisional draft without side effects', function () {
    $user = userWithPermissions([UserPermission::AccessStaff]);
    $billingGroup = BillingGroup::factory()->create();
    BillingGroupField::factory()->for($billingGroup)->create([
        'key' => 'subject',
        'name' => 'Subject',
        'is_required' => true,
        'sort_order' => 1,
    ]);
    $record = app(CreateBillingGroupDraftRecord::class)->handle($billingGroup, $user, [
        'field_values' => ['subject' => 'Complete draft facts'],
    ]);

    try {
        app(RequireBillingGroupFinancialReadiness::class)->handle($record);
        $this->fail('Financial execution should have been refused.');
    } catch (UnsupportedBillingGroupFinancialPolicy $exception) {
        expect($exception->record->is($record))->toBeTrue()
            ->and($exception->readiness['blocked_by'])->toContain('billing_group_acceptance', 'fee_amount_authority')
            ->and($exception->readiness['blocked_by'])->not->toContain('required_field_readiness')
            ->and($exception->getMessage())->toContain($record->draft_reference);
    }

    expect($record->refresh()->status)->toBe(BillingGroupRecordStatus::Draft)
        ->and(TreasuryCollection::query()->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe(0);
});

test('financial readiness detects schema drift without mutating the draft snapshot', function () {
    $user = userWithPermissions([UserPermission::AccessStaff]);
    $billingGroup = BillingGroup::factory()->create();
    $field = BillingGroupField::factory()->for($billingGroup)->create([
        'key' => 'subject',
        'name' => 'Subject',
        'sort_order' => 1,
    ]);
    $record = app(CreateBillingGroupDraftRecord::class)->handle($billingGroup, $user, [
        'field_values' => ['subject' => 'Original schema'],
    ]);
    $originalSnapshot = $record->schema_snapshot;

    $field->update(['name' => 'Changed Subject']);
    $readiness = app(DescribeBillingGroupFinancialReadiness::class)->handle($record);

    expect($readiness['schema_matches_current_definition'])->toBeFalse()
        ->and($readiness['blocked_by'])->toContain('schema_reconciliation')
        ->and($record->refresh()->schema_snapshot)->toBe($originalSnapshot);
});

test('billing group surfaces require their explicit permissions', function () {
    $billingGroup = BillingGroup::factory()->create();
    $staff = userWithPermissions([UserPermission::AccessStaff]);

    $this->actingAs($staff)->get(route('staff.billing-groups.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('staff.billing-groups.show', $billingGroup))->assertForbidden();
    $this->actingAs($staff)->post(route('staff.billing-groups.store'), [])->assertForbidden();
    $this->actingAs($staff)->post(route('staff.billing-groups.records.store', $billingGroup), [])->assertForbidden();
});

test('billing group pages expose provisional definition and draft-only boundaries', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewBillingGroups,
        UserPermission::ViewBillingGroupRecords,
        UserPermission::ManageBillingGroups,
        UserPermission::CreateBillingGroupRecords,
    ]);
    $billingGroup = BillingGroup::factory()->create(['name' => 'Provisional Records']);
    BillingGroupField::factory()->for($billingGroup)->create([
        'key' => 'subject',
        'name' => 'Subject',
        'sort_order' => 1,
    ]);
    BillingGroupRecord::factory()->for($billingGroup)->for($user, 'createdBy')->create([
        'draft_reference' => 'BGRD-TEST-001',
        'field_values' => ['subject' => 'Sample'],
    ]);

    $this->actingAs($user)->get(route('staff.billing-groups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing-groups/Index')
            ->where('billingGroups.data.0.acceptance_status', 'provisional')
            ->where('can.manage', true)
            ->where('policyNote', fn (string $note): bool => str_contains($note, 'does not accept it as a TOR module')));

    $this->actingAs($user)->get(route('staff.billing-groups.show', $billingGroup))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing-groups/Show')
            ->where('billingGroup.records.0.draft_reference', 'BGRD-TEST-001')
            ->where('billingGroup.records.0.status', 'draft')
            ->where('billingGroup.records.0.financial_readiness.status', 'blocked')
            ->where('billingGroup.records.0.financial_readiness.can_collect', false)
            ->where('billingGroup.records.0.financial_readiness.can_issue_receipt', false)
            ->where('billingGroup.records.0.financial_readiness.blocked_by', fn (Collection $blockedBy): bool => $blockedBy->contains('billing_group_acceptance'))
            ->where('can.create_record', true)
            ->where('policyNote', fn (string $note): bool => str_contains($note, 'no amount, liability, collection, receipt')));
});
