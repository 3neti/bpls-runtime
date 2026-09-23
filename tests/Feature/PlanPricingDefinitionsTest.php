<?php

use Illuminate\Support\Facades\Artisan;

test('reports source coverage without persisting any drafts', function () {
    $path = database_path('seeders/data/ipil_municipal_fee_catalog.v1.yaml');
    $exit = Artisan::call('pricing:plan-definitions', ['--source-sha256' => hash_file('sha256', $path)]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    expect($exit)->toBe(0)
        ->and($report['draft_count'])->toBe(171)
        ->and($report['tax_branch_count'])->toBe(245)
        ->and($report['database_writes'])->toBeFalse()
        ->and($report['executable'])->toBeFalse();
    $this->assertDatabaseCount('pricing_definition_drafts', 0);
});

test('refuses to plan without a reviewed fingerprint', function () {
    $this->artisan('pricing:plan-definitions')->assertFailed();
    $this->assertDatabaseCount('pricing_definition_drafts', 0);
});
