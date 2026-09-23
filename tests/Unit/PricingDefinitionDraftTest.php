<?php

declare(strict_types=1);

use App\Assessment\PricingDefinitionDraft;

function pricingDraft(array $changes = []): PricingDefinitionDraft
{
    return new PricingDefinitionDraft(...array_replace([
        'code' => 'ID', 'revision' => 1, 'method' => 'quantity_rate',
        'basis' => 'employee_count', 'unitCode' => 'ID', 'amountMinor' => 2500,
        'currency' => 'PHP', 'revenueAccountCode' => '001-02',
        'sourceSha256' => hash('sha256', 'source'), 'sourceLocator' => 'PDF 37',
        'sourceEvidence' => ['rate' => '25.00'],
    ], $changes));
}

it('preserves exact money units and account leading zeros', function () {
    expect(pricingDraft()->toRecord())->toMatchArray([
        'amount_minor' => 2500, 'unit_code' => 'ID', 'revenue_account_code' => '001-02',
    ]);
});

it('distinguishes an explicit zero from an unresolved amount', function () {
    expect(pricingDraft(['method' => 'fixed', 'amountMinor' => 0])->amountMinor)->toBe(0)
        ->and(pricingDraft(['method' => 'manual_determination', 'amountMinor' => null])->amountMinor)->toBeNull();
});

it('rejects invalid or ambiguous definitions', function (array $changes) {
    expect(fn () => pricingDraft($changes))->toThrow(InvalidArgumentException::class);
})->with([
    [['revision' => 0]], [['code' => '']], [['amountMinor' => -1]],
    [['method' => 'fixed', 'amountMinor' => null]],
    [['method' => 'manual_determination', 'amountMinor' => 0]],
    [['method' => 'source_observed', 'amountMinor' => 0]],
    [['method' => 'formula']], [['unitCode' => null]], [['basis' => 'none']],
    [['currency' => 'php']], [['sourceSha256' => 'wrong']], [['sourceLocator' => '']],
    [['sourceEvidence' => ['rate' => 0.005723]]],
    [['sourceEvidence' => ['execute' => new stdClass]]],
]);

it('never becomes executable even when evidence claims authority', function () {
    expect(fn () => pricingDraft(['sourceEvidence' => ['executable' => true]])->assertExecutable())
        ->toThrow(LogicException::class);
});
