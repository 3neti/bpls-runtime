<?php

use App\Models\FeeRule;
use App\Models\PricingDefinitionDraft;
use Illuminate\Database\QueryException;

it('persists and hydrates a non-executable draft without creating active fees', function () {
    $before = FeeRule::count();
    $draft = PricingDefinitionDraft::factory()->create([
        'method' => 'quantity_rate', 'basis' => 'employee_count', 'unit_code' => 'ID',
        'amount_minor' => 2500, 'revenue_account_code' => '001-02',
        'source_evidence' => ['rate' => '25.00', 'original_formula' => 'employees * 25'],
    ]);
    $loaded = $draft->fresh()->definition();
    expect($loaded->amountMinor)->toBe(2500)
        ->and($loaded->revenueAccountCode)->toBe('001-02')
        ->and($loaded->sourceEvidence['rate'])->toBe('25.00')
        ->and(FeeRule::count())->toBe($before);
    expect(fn () => $loaded->assertExecutable())->toThrow(LogicException::class);
});

it('preserves null amounts and distinct revisions', function () {
    $one = PricingDefinitionDraft::factory()->create(['code' => 'TEST']);
    PricingDefinitionDraft::factory()->create(['code' => 'TEST', 'revision' => 2]);
    expect($one->fresh()->amount_minor)->toBeNull()
        ->and(PricingDefinitionDraft::where('code', 'TEST')->count())->toBe(2);
});

it('rejects duplicate revision identities at the database boundary', function () {
    PricingDefinitionDraft::factory()->create(['code' => 'TEST']);
    expect(fn () => PricingDefinitionDraft::factory()->create(['code' => 'TEST']))
        ->toThrow(QueryException::class);
});

it('rejects invalid definitions before inserting', function () {
    expect(fn () => PricingDefinitionDraft::factory()->create(['method' => 'fixed']))
        ->toThrow(InvalidArgumentException::class);
    expect(PricingDefinitionDraft::count())->toBe(0);
});

it('retains recorded evidence through model write guards', function () {
    $draft = PricingDefinitionDraft::factory()->create();
    expect(fn () => $draft->update(['source_locator' => 'changed']))->toThrow(LogicException::class);
    expect(fn () => $draft->delete())->toThrow(LogicException::class);
    expect($draft->fresh()->source_locator)->toBe('test-fixture');
});

it('rejects lossy amount coercion before model casts', function (mixed $amount) {
    expect(fn () => PricingDefinitionDraft::factory()->create([
        'method' => 'fixed', 'amount_minor' => $amount,
    ]))->toThrow(InvalidArgumentException::class);
    expect(PricingDefinitionDraft::count())->toBe(0);
})->with([25.9, '2500', true]);
