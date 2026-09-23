<?php

use App\Enums\FeeRuleCategory;
use App\Models\FeeCategory;
use App\Models\PricingChargeGroup;
use App\Models\PricingChargeItem;
use App\Models\PricingUnit;
use Illuminate\Database\QueryException;

test('hydrates browsing relationships without creating prices or account mappings', function () {
    $parent = PricingChargeGroup::factory()->create();
    $group = PricingChargeGroup::factory()->create(['parent_id' => $parent->id]);
    $unit = PricingUnit::factory()->create(['code' => 'square-metre', 'dimension' => 'area', 'decimal_places' => 4]);
    $category = FeeCategory::query()->create(['code' => 'test-fee', 'name' => 'Fee', 'fee_rule_category' => FeeRuleCategory::Fee, 'is_active' => true]);
    $item = PricingChargeItem::factory()->create([
        'pricing_charge_group_id' => $group->id,
        'pricing_unit_id' => $unit->id,
        'fee_category_id' => $category->id,
    ])->fresh();
    expect($item->group->parent->id)->toBe($parent->id)
        ->and($item->unit->dimension)->toBe('area')
        ->and($item->unit->decimal_places)->toBe(4)
        ->and($item->category->id)->toBe($category->id);
    $this->assertDatabaseCount('fee_rules', 0);
    $this->assertDatabaseCount('pricing_definition_drafts', 0);
    $this->assertDatabaseCount('revenue_accounts', 0);
});

test('leaves unresolved classification and units null', function () {
    $item = PricingChargeItem::factory()->create()->fresh();
    expect($item->group)->toBeNull()->and($item->unit)->toBeNull()->and($item->category)->toBeNull();
});

test('rejects malformed precision without coercion', function (mixed $precision) {
    expect(fn () => PricingUnit::factory()->create(['decimal_places' => $precision]))
        ->toThrow(InvalidArgumentException::class);
})->with([-1, 13, 1.5, '2', null]);

test('rejects empty identities', function (string $model) {
    expect(fn () => $model::factory()->create(['code' => ' ']))
        ->toThrow(InvalidArgumentException::class);
})->with([PricingUnit::class, PricingChargeGroup::class, PricingChargeItem::class]);

test('rejects duplicate stable identities', function (string $model) {
    $model::factory()->create(['code' => 'duplicate']);
    expect(fn () => $model::factory()->create(['code' => 'duplicate']))
        ->toThrow(QueryException::class);
})->with([PricingUnit::class, PricingChargeGroup::class, PricingChargeItem::class]);

test('retains recorded catalogue definitions', function (string $model) {
    $record = $model::factory()->create();
    expect(fn () => $record->update(['name' => 'Changed']))->toThrow(LogicException::class);
    expect(fn () => $record->delete())->toThrow(LogicException::class);
})->with([PricingUnit::class, PricingChargeGroup::class, PricingChargeItem::class]);

test('prevents group cycles by requiring an existing parent and refusing reparenting', function () {
    expect(fn () => PricingChargeGroup::factory()->create(['parent_id' => 999999]))
        ->toThrow(InvalidArgumentException::class);
    $root = PricingChargeGroup::factory()->create();
    $child = PricingChargeGroup::factory()->create(['parent_id' => $root->id]);
    expect(fn () => $root->update(['parent_id' => $child->id]))->toThrow(LogicException::class);
});

test('database rejects dangling structural references', function (string $field) {
    expect(fn () => PricingChargeItem::factory()->create([$field => 999999]))
        ->toThrow(QueryException::class);
})->with(['pricing_charge_group_id', 'pricing_unit_id', 'fee_category_id']);

test('database prevents deletion of referenced units even through bulk queries', function () {
    $unit = PricingUnit::factory()->create();
    PricingChargeItem::factory()->create(['pricing_unit_id' => $unit->id]);
    expect(fn () => PricingUnit::query()->whereKey($unit->id)->delete())->toThrow(QueryException::class);
});
