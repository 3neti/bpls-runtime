<?php

use App\Actions\StageLegacyExport;
use App\Enums\LegacyImportBatchStatus;
use App\Enums\MigrationValidationStatus;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LegacyIdMapping;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMigrationException;
use App\Models\LegacyRecord;
use App\Models\LegacySource;
use App\Models\MigrationValidationResult;
use App\Models\PermitApplication;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** @var list<string> $legacyStagingFixtureDirectories */
$legacyStagingFixtureDirectories = [];

/**
 * @param  list<string>  $rows
 * @param  array<string, mixed>  $datasetOverrides
 * @param  array<string, mixed>  $manifestOverrides
 * @return array{directory: string, dataset: string, manifest: string, contents: string}
 */
function createLegacyStagingFixture(array $rows, array $datasetOverrides = [], array $manifestOverrides = []): array
{
    global $legacyStagingFixtureDirectories;

    $directory = storage_path('framework/testing/legacy-staging-'.Str::uuid());
    File::ensureDirectoryExists($directory);
    $legacyStagingFixtureDirectories[] = $directory;
    $datasetPath = $directory.'/business_owners.jsonl';
    $contents = implode("\n", $rows)."\n";
    File::put($datasetPath, $contents);
    $manifest = [
        'schema_version' => StageLegacyExport::SchemaVersion,
        'source' => [
            'key' => 'LEGACY-SOURCE-TEST',
            'title' => 'Test legacy BPLS export',
            'source_type' => 'convex_export',
            'baseline' => 'test-baseline',
            'archive_checksum' => hash('sha256', 'authoritative-test-archive'),
            'provenance' => ['origin' => 'isolated_test_fixture'],
        ],
        'datasets' => [[
            'key' => 'business_owners',
            'entity_type' => 'business_owner',
            'file' => 'business_owners.jsonl',
            'sha256' => hash('sha256', $contents),
            'record_count' => count($rows),
            'identity_field' => '_id',
            ...$datasetOverrides,
        ]],
        ...$manifestOverrides,
    ];
    $manifestPath = $directory.'/manifest.json';
    File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    return [
        'directory' => $directory,
        'dataset' => $datasetPath,
        'manifest' => $manifestPath,
        'contents' => $contents,
    ];
}

afterEach(function () use (&$legacyStagingFixtureDirectories): void {
    foreach ($legacyStagingFixtureDirectories as $directory) {
        File::deleteDirectory($directory);
    }

    $legacyStagingFixtureDirectories = [];
});

test('a checksum verified export is staged without changing domain records', function () {
    $firstPayload = ['_id' => 'owner-001', 'firstName' => 'Maria', 'lastName' => 'Santos'];
    $fixture = createLegacyStagingFixture([
        json_encode($firstPayload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        json_encode(['_id' => 'owner-002', 'firstName' => 'Jose'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ]);

    $batch = app(StageLegacyExport::class)->handle($fixture['manifest'], 'migration-stage-001');

    expect($batch)
        ->status->toBe(LegacyImportBatchStatus::Staged)
        ->source_record_count->toBe(2)
        ->staged_record_count->toBe(2)
        ->exception_count->toBe(0)
        ->mapping_count->toBe(0)
        ->and($batch->metadata['domain_writes'])->toBeFalse()
        ->and(LegacySource::query()->sole()->archive_checksum)->toBe(hash('sha256', 'authoritative-test-archive'))
        ->and(LegacyRecord::query()->where('legacy_id', 'owner-001')->sole()->payload)->toBe($firstPayload)
        ->and(MigrationValidationResult::query()->where('status', MigrationValidationStatus::Passed)->count())->toBe(3)
        ->and(LegacyIdMapping::query()->count())->toBe(0)
        ->and(BusinessOwner::query()->count())->toBe(0)
        ->and(Business::query()->count())->toBe(0)
        ->and(PermitApplication::query()->count())->toBe(0);
});

test('invalid missing and duplicate identities become reviewable exceptions without guessed records', function () {
    $fixture = createLegacyStagingFixture([
        json_encode(['_id' => 'owner-001', 'name' => 'Valid Owner'], JSON_THROW_ON_ERROR),
        '{not-json}',
        json_encode(['name' => 'Missing Identity'], JSON_THROW_ON_ERROR),
        json_encode(['_id' => 'owner-001', 'name' => 'Duplicate Owner'], JSON_THROW_ON_ERROR),
    ]);

    $batch = app(StageLegacyExport::class)->handle($fixture['manifest'], 'migration-stage-exceptions-001');

    expect($batch)
        ->status->toBe(LegacyImportBatchStatus::StagedWithExceptions)
        ->source_record_count->toBe(4)
        ->staged_record_count->toBe(1)
        ->exception_count->toBe(3)
        ->and(LegacyMigrationException::query()->orderBy('line_number')->pluck('code')->all())
        ->toBe(['invalid_json', 'missing_legacy_id', 'duplicate_legacy_id'])
        ->and(LegacyRecord::query()->sole()->legacy_id)->toBe('owner-001')
        ->and(LegacyIdMapping::query()->count())->toBe(0);
});

test('a stable run is idempotent and cannot be rebound to changed input', function () {
    $fixture = createLegacyStagingFixture([
        json_encode(['_id' => 'owner-001', 'name' => 'Stable Owner'], JSON_THROW_ON_ERROR),
    ]);
    $action = app(StageLegacyExport::class);

    $first = $action->handle($fixture['manifest'], 'migration-idempotency-001');
    $second = $action->handle($fixture['manifest'], 'migration-idempotency-001');

    expect($second->id)->toBe($first->id)
        ->and(LegacyImportBatch::query()->count())->toBe(1)
        ->and(LegacyRecord::query()->count())->toBe(1)
        ->and(MigrationValidationResult::query()->count())->toBe(3);

    File::append($fixture['manifest'], "\n");

    expect(fn () => $action->handle($fixture['manifest'], 'migration-idempotency-001'))
        ->toThrow(RuntimeException::class, 'different manifest checksum');
});

test('a registered source key cannot be rebound to changed provenance', function () {
    $firstFixture = createLegacyStagingFixture([
        json_encode(['_id' => 'owner-001'], JSON_THROW_ON_ERROR),
    ]);
    $secondFixture = createLegacyStagingFixture([
        json_encode(['_id' => 'owner-002'], JSON_THROW_ON_ERROR),
    ], manifestOverrides: [
        'source' => [
            'key' => 'LEGACY-SOURCE-TEST',
            'title' => 'Changed source claim',
            'source_type' => 'convex_export',
            'baseline' => 'different-baseline',
            'archive_checksum' => hash('sha256', 'different-archive'),
            'provenance' => ['origin' => 'changed_claim'],
        ],
    ]);
    $action = app(StageLegacyExport::class);
    $action->handle($firstFixture['manifest'], 'migration-source-001');

    expect(fn () => $action->handle($secondFixture['manifest'], 'migration-source-002'))
        ->toThrow(RuntimeException::class, 'different identity or provenance');
});

test('checksum and path failures refuse staging and preserve diagnostic evidence', function (string $failure, array $overrides, string $exceptionCode) {
    $fixture = createLegacyStagingFixture([
        json_encode(['_id' => 'owner-001'], JSON_THROW_ON_ERROR),
    ], $overrides);

    $batch = app(StageLegacyExport::class)->handle($fixture['manifest'], 'migration-refusal-'.$failure);

    expect($batch)
        ->status->toBe(LegacyImportBatchStatus::Failed)
        ->staged_record_count->toBe(0)
        ->exception_count->toBe(1)
        ->and(LegacyMigrationException::query()->sole()->code)->toBe($exceptionCode)
        ->and(LegacyIdMapping::query()->count())->toBe(0)
        ->and(BusinessOwner::query()->count())->toBe(0);
})->with([
    'checksum mismatch' => ['checksum', ['sha256' => str_repeat('0', 64)], 'dataset_checksum_mismatch'],
    'path traversal' => ['path', ['file' => '../outside.jsonl'], 'dataset_file_unavailable'],
]);

test('the command writes a payload free report and requires a stable run reference', function () {
    Storage::fake('local');
    $fixture = createLegacyStagingFixture([
        json_encode(['_id' => 'owner-secret', 'name' => 'Sensitive Fixture Name'], JSON_THROW_ON_ERROR),
        json_encode(['_id' => 'owner-secret', 'name' => 'Duplicate Sensitive Fixture Name'], JSON_THROW_ON_ERROR),
    ]);

    $this->artisan('legacy:stage', ['manifest' => $fixture['manifest']])
        ->expectsOutput('A stable --run-id is required.')
        ->assertFailed();

    $this->artisan('legacy:stage', [
        'manifest' => $fixture['manifest'],
        '--run-id' => 'migration-command-001',
        '--json' => true,
    ])->assertSuccessful();

    $reportPath = 'legacy-migrations/LEGACY-SOURCE-TEST/migration-command-001/report.json';
    Storage::disk('local')->assertExists($reportPath);
    Storage::disk('local')->assertExists('legacy-migrations/LEGACY-SOURCE-TEST/migration-command-001/review.md');
    $report = Storage::disk('local')->get($reportPath);

    expect($report)
        ->not->toContain('Sensitive Fixture Name')
        ->not->toContain('owner-secret')
        ->and(json_decode($report, true, 512, JSON_THROW_ON_ERROR)['result']['domain_writes'])->toBeFalse()
        ->and(json_decode($report, true, 512, JSON_THROW_ON_ERROR)['exceptions'][0]['context'])
        ->toHaveKey('legacy_id_sha256');
});
