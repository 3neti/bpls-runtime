<?php

use App\Models\PricingDefinitionDraft;
use App\Models\PricingDraftMapping;
use App\Models\RevenueAccount;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;

test('preserves explicit mapping identities while leaving prices disabled', function () {
    $draft = PricingDefinitionDraft::factory()->create(['revenue_account_code' => 'SOURCE-001']);
    $account = RevenueAccount::query()->create(['code' => 'ACCOUNT-009', 'name' => 'Recorded account', 'is_active' => true]);
    $mapping = PricingDraftMapping::factory()->create([
        'pricing_definition_draft_id' => $draft->id,
        'revenue_account_id' => $account->id,
    ])->fresh();
    expect($mapping->draft->id)->toBe($draft->id)
        ->and($mapping->account->id)->toBe($account->id)
        ->and($mapping->item)->not->toBeNull()
        ->and($mapping->recorder)->not->toBeNull()
        ->and($mapping->identity_snapshot['source_account_code'])->toBe('SOURCE-001')
        ->and($mapping->identity_snapshot['account_code'])->toBe('ACCOUNT-009')
        ->and($mapping->identity_snapshot['source_sha256'])->toBe($draft->source_sha256)
        ->and($mapping->identity_snapshot['executable'])->toBeFalse();
    expect(fn () => $mapping->assertExecutable())->toThrow(LogicException::class);
    expect(fn () => $mapping->draft->definition()->assertExecutable())->toThrow(LogicException::class);
    $account->update(['code' => 'ACCOUNT-NEW', 'name' => 'Renamed']);
    expect($mapping->fresh()->identity_snapshot['account_code'])->toBe('ACCOUNT-009')
        ->and($mapping->fresh()->identity_snapshot['account_name'])->toBe('Recorded account');
    $this->assertDatabaseCount('fee_rules', 0);
});

test('does not infer an account from matching source code', function () {
    RevenueAccount::query()->create(['code' => 'SOURCE-001', 'is_active' => true]);
    $draft = PricingDefinitionDraft::factory()->create(['revenue_account_code' => 'SOURCE-001']);
    $mapping = PricingDraftMapping::factory()->create(['pricing_definition_draft_id' => $draft->id]);
    expect($mapping->account)->toBeNull()->and($mapping->identity_snapshot['account_code'])->toBeNull();
});

test('refuses invalid mapping provenance', function (string $field, mixed $value) {
    expect(fn () => PricingDraftMapping::factory()->create([$field => $value]))
        ->toThrow(InvalidArgumentException::class);
})->with([
    ['revision', 0], ['revision', 1.2], ['revision', '1'],
    ['evidence_reference', ' '], ['rationale', ''], ['evidence_sha256', 'invalid'],
]);

test('retains mapping versions and prevents silent replacement', function () {
    $first = PricingDraftMapping::factory()->create();
    expect(fn () => $first->update(['rationale' => 'Replacement']))->toThrow(LogicException::class);
    expect(fn () => $first->delete())->toThrow(LogicException::class);
    $second = PricingDraftMapping::factory()->create([
        'pricing_definition_draft_id' => $first->pricing_definition_draft_id,
        'revision' => 2,
    ]);
    expect($second->draft->id)->toBe($first->draft->id);
    $this->assertDatabaseCount('pricing_draft_mappings', 2);
    expect(fn () => PricingDraftMapping::factory()->create([
        'pricing_definition_draft_id' => $first->pricing_definition_draft_id, 'revision' => 2,
    ]))->toThrow(QueryException::class);
});

test('refuses dangling mapping references', function (string $field) {
    expect(fn () => PricingDraftMapping::factory()->create([$field => 999999]))
        ->toThrow(ModelNotFoundException::class);
})->with(['pricing_definition_draft_id', 'pricing_charge_item_id', 'revenue_account_id', 'recorded_by']);

test('database retains accounts referenced by mapping evidence', function () {
    $account = RevenueAccount::query()->create(['code' => 'RETAIN', 'is_active' => true]);
    PricingDraftMapping::factory()->create(['revenue_account_id' => $account->id]);
    expect(fn () => RevenueAccount::query()->whereKey($account->id)->delete())->toThrow(QueryException::class);
});

test('rebuilds snapshots instead of trusting caller supplied identities', function () {
    $mapping = PricingDraftMapping::factory()->make();
    $mapping->identity_snapshot = ['executable' => true, 'account_code' => 'FORGED'];
    $mapping->save();
    expect($mapping->fresh()->identity_snapshot['executable'])->toBeFalse()
        ->and($mapping->fresh()->identity_snapshot['account_code'])->toBeNull();
});
