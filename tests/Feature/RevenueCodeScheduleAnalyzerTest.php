<?php

use App\Actions\AnalyzeRevenueCodeSchedule;
use App\Enums\RevenueCodeProvisionRowStatus;
use App\Enums\RevenueCodeProvisionStatus;
use App\Models\RevenueCodeProvision;
use App\Models\RevenueCodeProvisionRow;
use Database\Seeders\RevenueCodeFeeCatalogSeeder;

it('detects the disputed wholesale schedule without authorizing execution', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $provision = RevenueCodeProvision::query()
        ->where('code', 'MRC-2A-02-B-WHOLESALERS')
        ->sole();
    $analysis = app(AnalyzeRevenueCodeSchedule::class)->handle($provision);

    expect($analysis['summary'])
        ->row_count->toBe(24)
        ->exact_row_count->toBe(21)
        ->reconciliation_required_count->toBe(3)
        ->overlap_count->toBe(1)
        ->gap_count->toBe(0)
        ->ceiling_count->toBe(1)
        ->execution_ready->toBeFalse();

    $overlap = collect($analysis['rows'])->firstWhere('code', 'MRC-2A-02-B-ROW-08');
    $malformed = collect($analysis['rows'])->firstWhere('code', 'MRC-2A-02-B-ROW-18');
    $ceiling = collect($analysis['rows'])->firstWhere('code', 'MRC-2A-02-B-ROW-24');

    expect($overlap['issues'])->toContain([
        'type' => 'overlap',
        'related_row_code' => 'MRC-2A-02-B-ROW-07',
    ])->and($malformed['issues'])->toContain([
        'type' => 'normalization_required',
    ])->and($ceiling['issues'])
        ->toContain(['type' => 'normalization_required'])
        ->toContain(['type' => 'ceiling_not_exact']);
});

it('analyzes each extracted schedule without authorizing execution', function (string $provisionCode, array $expectedSummary, string $findingRowCode, string $findingType) {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $provision = RevenueCodeProvision::query()->where('code', $provisionCode)->sole();
    $analysis = app(AnalyzeRevenueCodeSchedule::class)->handle($provision);
    $findingRow = collect($analysis['rows'])->firstWhere('code', $findingRowCode);

    expect($analysis['summary'])->toMatchArray($expectedSummary)
        ->and($analysis['summary']['execution_ready'])->toBeFalse()
        ->and(collect($findingRow['issues'])->pluck('type')->all())->toContain($findingType);
})->with([
    'manufacturers' => [
        'MRC-2A-02-A-MANUFACTURERS',
        ['row_count' => 20, 'exact_row_count' => 18, 'reconciliation_required_count' => 2, 'overlap_count' => 0, 'gap_count' => 0, 'ceiling_count' => 1],
        'MRC-2A-02-A-ROW-18',
        'normalization_required',
    ],
    'contractors' => [
        'MRC-2A-02-E-CONTRACTORS',
        ['row_count' => 19, 'exact_row_count' => 18, 'reconciliation_required_count' => 1, 'overlap_count' => 1, 'gap_count' => 0, 'ceiling_count' => 1],
        'MRC-2A-02-E-ROW-15',
        'overlap',
    ],
    'enumerated services' => [
        'MRC-2A-02-G-ENUMERATED-SERVICES',
        ['row_count' => 19, 'exact_row_count' => 18, 'reconciliation_required_count' => 1, 'overlap_count' => 1, 'gap_count' => 0, 'ceiling_count' => 1],
        'MRC-2A-02-G-ROW-15',
        'overlap',
    ],
]);

it('detects a gap from candidate bounds without changing row evidence', function () {
    $provision = RevenueCodeProvision::factory()->create([
        'reconciliation_status' => RevenueCodeProvisionStatus::Reconciled,
    ]);
    RevenueCodeProvisionRow::factory()->for($provision, 'provision')->create([
        'sequence' => 1,
        'code' => 'TEST-GAP-ROW-01',
        'basis_from_cents' => 0,
        'basis_below_cents' => 100_000,
    ]);
    RevenueCodeProvisionRow::factory()->for($provision, 'provision')->create([
        'sequence' => 2,
        'code' => 'TEST-GAP-ROW-02',
        'source_basis_text' => 'PHP 2,000.00 or more',
        'basis_from_cents' => 200_000,
        'basis_below_cents' => null,
        'normalization_status' => RevenueCodeProvisionRowStatus::Exact,
    ]);

    $analysis = app(AnalyzeRevenueCodeSchedule::class)->handle($provision);

    expect($analysis['summary'])
        ->gap_count->toBe(1)
        ->overlap_count->toBe(0)
        ->execution_ready->toBeFalse()
        ->and($analysis['rows'][1]['issues'])->toContain([
            'type' => 'gap',
            'related_row_code' => 'TEST-GAP-ROW-01',
        ]);
});

it('refuses execution when a reconciled provision has no schedule rows', function () {
    $provision = RevenueCodeProvision::factory()->create([
        'reconciliation_status' => RevenueCodeProvisionStatus::Reconciled,
    ]);

    $analysis = app(AnalyzeRevenueCodeSchedule::class)->handle($provision);

    expect($analysis['summary'])
        ->row_count->toBe(0)
        ->execution_ready->toBeFalse();
});
