<?php

use App\Assessment\PricingMappingCoverageAudit;
use App\Models\PricingDefinitionDraft;
use App\Models\PricingDraftMapping;
use App\Models\RevenueAccount;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

test('partitions stored draft revisions and reports missing accounts without fiscal readiness', function () {
    $source = hash('sha256', 'test source');
    PricingDefinitionDraft::factory()->create();
    PricingDraftMapping::factory()->create();
    $account = RevenueAccount::query()->create(['code' => 'AUDIT', 'is_active' => true]);
    PricingDraftMapping::factory()->create(['revenue_account_id' => $account->id]);
    PricingDefinitionDraft::factory()->create(['source_sha256' => hash('sha256', 'other source')]);
    $report = app(PricingMappingCoverageAudit::class)->report($source);
    expect($report['draft_revision_count'])->toBe(3)
        ->and($report['counts'])->toBe([
            'unmapped' => 1, 'account_unmapped' => 1, 'identity_drift' => 0,
            'account_inactive' => 0, 'mapping_recorded' => 1,
        ])->and($report['fiscal_readiness'])->toBeFalse();
    $this->assertDatabaseCount('pricing_definition_drafts', 4);
    $this->assertDatabaseCount('pricing_draft_mappings', 2);
});

test('audits latest mapping revision without falling back to a previous account', function () {
    $account = RevenueAccount::query()->create(['code' => 'OLD', 'is_active' => true]);
    $old = PricingDraftMapping::factory()->create(['revenue_account_id' => $account->id]);
    PricingDraftMapping::factory()->create(['pricing_definition_draft_id' => $old->draft->id, 'revision' => 2]);
    $report = app(PricingMappingCoverageAudit::class)->report($old->draft->source_sha256);
    expect($report['counts']['account_unmapped'])->toBe(1)
        ->and($report['counts']['mapping_recorded'])->toBe(0);
});

test('detects account identity drift and inactivity without rewriting snapshots', function () {
    $account = RevenueAccount::query()->create(['code' => 'ORIGINAL', 'name' => 'Original', 'is_active' => true]);
    $mapping = PricingDraftMapping::factory()->create(['revenue_account_id' => $account->id]);
    $snapshot = $mapping->identity_snapshot;
    $account->update(['is_active' => false]);
    $audit = app(PricingMappingCoverageAudit::class);
    expect($audit->report($mapping->draft->source_sha256)['counts']['account_inactive'])->toBe(1);
    $account->update(['code' => 'CHANGED']);
    expect($audit->report($mapping->draft->source_sha256)['counts']['identity_drift'])->toBe(1)
        ->and($mapping->fresh()->identity_snapshot)->toBe($snapshot);
});

test('detects tampered snapshot keys and charge identity drift', function () {
    $mapping = PricingDraftMapping::factory()->create();
    $audit = app(PricingMappingCoverageAudit::class);
    DB::table('pricing_charge_items')->where('id', $mapping->pricing_charge_item_id)->update(['name' => 'Changed']);
    expect($audit->report($mapping->draft->source_sha256)['counts']['identity_drift'])->toBe(1);
    DB::table('pricing_draft_mappings')->where('id', $mapping->id)->update(['identity_snapshot' => '{}']);
    expect($audit->report($mapping->draft->source_sha256)['counts']['identity_drift'])->toBe(1);
});

test('does not borrow mappings between draft revisions', function () {
    $mapping = PricingDraftMapping::factory()->create();
    PricingDefinitionDraft::factory()->create(['code' => $mapping->draft->code, 'revision' => 2]);
    $report = app(PricingMappingCoverageAudit::class)->report($mapping->draft->source_sha256);
    expect($report['draft_revision_count'])->toBe(2)->and($report['fee_identity_count'])->toBe(1)
        ->and($report['counts']['unmapped'])->toBe(1);
});

test('reports empty cohorts honestly and rejects invalid fingerprints', function () {
    $audit = app(PricingMappingCoverageAudit::class);
    $report = $audit->report(str_repeat('0', 64));
    expect($report['state'])->toBe('no_stored_drafts')->and($report['executable'])->toBeFalse();
    expect(fn () => $audit->report(''))->toThrow(InvalidArgumentException::class);
});

test('command reports aggregate read only results and requires cohort selection', function () {
    $mapping = PricingDraftMapping::factory()->create();
    $exit = Artisan::call('pricing:audit-mappings', ['--source-sha256' => $mapping->draft->source_sha256]);
    $output = Artisan::output();
    $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    expect($exit)->toBe(0)->and($report['database_writes'])->toBeFalse()
        ->and($output)->not->toContain($mapping->rationale, $mapping->recorder->email);
    $this->artisan('pricing:audit-mappings')->assertFailed();
    $this->assertDatabaseCount('pricing_draft_mappings', 1);
});
