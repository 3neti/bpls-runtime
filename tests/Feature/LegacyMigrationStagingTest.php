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

/**
 * @return array{directory: string, manifest: string}
 */
function createLegacyRelationalStagingFixture(): array
{
    global $legacyStagingFixtureDirectories;

    $directory = storage_path('framework/testing/legacy-relational-staging-'.Str::uuid());
    File::ensureDirectoryExists($directory);
    $legacyStagingFixtureDirectories[] = $directory;
    $datasetRows = [
        'business_owners' => [
            json_encode(['_id' => 'owner-001', 'firstName' => 'Sensitive Owner'], JSON_THROW_ON_ERROR),
        ],
        'businesses' => [
            json_encode(['_id' => 'business-001', 'ownerId' => 'owner-001', 'name' => 'Linked Business'], JSON_THROW_ON_ERROR),
            json_encode(['_id' => 'business-002', 'ownerId' => 'missing-owner-secret', 'name' => 'Broken Business'], JSON_THROW_ON_ERROR),
            json_encode(['_id' => 'business-003', 'name' => 'Missing Owner Business'], JSON_THROW_ON_ERROR),
        ],
        'fees' => [
            json_encode(['_id' => 'fee-001', 'name' => 'Known Fee'], JSON_THROW_ON_ERROR),
        ],
        'applications' => [
            json_encode([
                '_id' => 'application-001',
                'businessOwnerId' => 'owner-001',
                'businessId' => 'business-001',
                'linesOfBusiness' => [[
                    'excludedFees' => ['fee-001', 'missing-fee-secret'],
                ]],
            ], JSON_THROW_ON_ERROR),
        ],
    ];
    $datasets = [];

    foreach ($datasetRows as $datasetKey => $rows) {
        $contents = implode("\n", $rows)."\n";
        $filename = $datasetKey.'.jsonl';
        File::put($directory.'/'.$filename, $contents);
        $references = match ($datasetKey) {
            'businesses' => [[
                'field' => 'ownerId',
                'target_dataset' => 'business_owners',
                'required' => true,
            ]],
            'applications' => [
                [
                    'field' => 'businessOwnerId',
                    'target_dataset' => 'business_owners',
                    'required' => true,
                ],
                [
                    'field' => 'businessId',
                    'target_dataset' => 'businesses',
                    'required' => true,
                ],
                [
                    'field' => 'linesOfBusiness.*.excludedFees',
                    'target_dataset' => 'fees',
                    'cardinality' => 'many',
                ],
            ],
            default => [],
        };
        $datasets[] = [
            'key' => $datasetKey,
            'entity_type' => str($datasetKey)->singular()->toString(),
            'file' => $filename,
            'sha256' => hash('sha256', $contents),
            'record_count' => count($rows),
            'identity_field' => '_id',
            'references' => $references,
        ];
    }

    $manifest = [
        'schema_version' => StageLegacyExport::SchemaVersion,
        'source' => [
            'key' => 'LEGACY-RELATIONAL-TEST',
            'title' => 'Relational legacy BPLS test export',
            'source_type' => 'convex_export',
            'baseline' => 'relational-test-baseline',
            'archive_checksum' => hash('sha256', 'relational-authoritative-test-archive'),
            'provenance' => ['origin' => 'isolated_relational_test_fixture'],
        ],
        'datasets' => $datasets,
    ];
    $manifestPath = $directory.'/manifest.json';
    File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    return ['directory' => $directory, 'manifest' => $manifestPath];
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
        ->and(MigrationValidationResult::query()->where('status', MigrationValidationStatus::Passed)->count())->toBe(4)
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
        ->and(MigrationValidationResult::query()->count())->toBe(4);

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

test('dataset inventory and declared references expose shape and integrity without copying values', function () {
    $fixture = createLegacyRelationalStagingFixture();

    $batch = app(StageLegacyExport::class)->handle($fixture['manifest'], 'migration-relations-001');
    $businessInventory = MigrationValidationResult::query()
        ->where('dataset_key', 'businesses')
        ->where('check_key', 'dataset_inventory')
        ->sole();
    $ownerReference = MigrationValidationResult::query()
        ->where('dataset_key', 'businesses')
        ->where('check_key', 'reference_integrity:ownerId->business_owners')
        ->sole();
    $nestedFeeReference = MigrationValidationResult::query()
        ->where('dataset_key', 'applications')
        ->where('check_key', 'reference_integrity:linesOfBusiness.*.excludedFees->fees')
        ->sole();
    $inventoryJson = json_encode($businessInventory->actual, JSON_THROW_ON_ERROR);

    expect($batch)
        ->status->toBe(LegacyImportBatchStatus::StagedWithExceptions)
        ->exception_count->toBe(3)
        ->mapping_count->toBe(0)
        ->and($batch->metadata['dataset_inventory_count'])->toBe(4)
        ->and($batch->metadata['reference_check_count'])->toBe(4)
        ->and($batch->metadata['unresolved_reference_count'])->toBe(2)
        ->and($businessInventory->status)->toBe(MigrationValidationStatus::Passed)
        ->and($businessInventory->actual['observed_fields'])->toContain([
            'path' => 'ownerId',
            'presence_count' => 2,
            'types' => ['string' => 2],
        ])
        ->and($inventoryJson)->not->toContain('Sensitive Owner', 'Linked Business', 'missing-owner-secret')
        ->and($ownerReference->status)->toBe(MigrationValidationStatus::Failed)
        ->and($ownerReference->actual)->toMatchArray([
            'source_record_count' => 3,
            'present_reference_count' => 2,
            'resolved_reference_count' => 1,
            'missing_required_count' => 1,
            'invalid_type_count' => 0,
            'unresolved_reference_count' => 1,
        ])
        ->and($nestedFeeReference->actual)->toMatchArray([
            'present_reference_count' => 2,
            'resolved_reference_count' => 1,
            'unresolved_reference_count' => 1,
        ])
        ->and(LegacyMigrationException::query()->where('code', 'missing_required_reference')->count())->toBe(1)
        ->and(LegacyMigrationException::query()->where('code', 'unresolved_legacy_reference')->count())->toBe(2)
        ->and(LegacyMigrationException::query()->whereNotNull('legacy_record_id')->count())->toBe(3)
        ->and(LegacyMigrationException::query()->whereNull('dataset_key')->count())->toBe(0)
        ->and(LegacyIdMapping::query()->count())->toBe(0)
        ->and(BusinessOwner::query()->count())->toBe(0)
        ->and(Business::query()->count())->toBe(0)
        ->and(PermitApplication::query()->count())->toBe(0);
});

test('reference evidence reports hashes rather than legacy identifiers or payload values', function () {
    Storage::fake('local');
    $fixture = createLegacyRelationalStagingFixture();

    $this->artisan('legacy:stage', [
        'manifest' => $fixture['manifest'],
        '--run-id' => 'migration-relations-report-001',
        '--json' => true,
    ])->assertSuccessful();

    $report = Storage::disk('local')->get('legacy-migrations/LEGACY-RELATIONAL-TEST/migration-relations-report-001/report.json');
    $decoded = json_decode($report, true, 512, JSON_THROW_ON_ERROR);

    expect($report)
        ->not->toContain('Sensitive Owner')
        ->not->toContain('Linked Business')
        ->not->toContain('missing-owner-secret')
        ->not->toContain('missing-fee-secret')
        ->and($decoded['result'])->toMatchArray([
            'dataset_inventory_count' => 4,
            'reference_check_count' => 4,
            'unresolved_reference_count' => 2,
            'domain_writes' => false,
        ])
        ->and(collect($decoded['exceptions'])->where('code', 'unresolved_legacy_reference')->first()['context'])
        ->toHaveKey('reference_sha256');
});

test('a reference target must be declared in the same immutable manifest', function () {
    $fixture = createLegacyStagingFixture([
        json_encode(['_id' => 'business-001', 'ownerId' => 'owner-001'], JSON_THROW_ON_ERROR),
    ], datasetOverrides: [
        'references' => [[
            'field' => 'ownerId',
            'target_dataset' => 'undeclared_owners',
            'required' => true,
        ]],
    ]);

    expect(fn () => app(StageLegacyExport::class)->handle($fixture['manifest'], 'migration-undeclared-target-001'))
        ->toThrow(RuntimeException::class, 'is not declared as a dataset');
});

test('duplicate reference declarations are refused before staging', function () {
    $reference = [
        'field' => 'ownerId',
        'target_dataset' => 'businesses',
        'required' => true,
    ];
    $fixture = createLegacyStagingFixture([
        json_encode(['_id' => 'business-001', 'ownerId' => 'business-001'], JSON_THROW_ON_ERROR),
    ], datasetOverrides: [
        'key' => 'businesses',
        'references' => [$reference, $reference],
    ]);

    expect(fn () => app(StageLegacyExport::class)->handle($fixture['manifest'], 'migration-duplicate-reference-001'))
        ->toThrow(RuntimeException::class, 'declares duplicate reference');
});

test('a declared reference refuses a payload with the wrong cardinality shape', function () {
    $fixture = createLegacyStagingFixture([
        json_encode(['_id' => 'business-001', 'relatedIds' => ['named' => 'business-001']], JSON_THROW_ON_ERROR),
    ], datasetOverrides: [
        'key' => 'businesses',
        'references' => [[
            'field' => 'relatedIds',
            'target_dataset' => 'businesses',
            'cardinality' => 'many',
        ]],
    ]);

    $batch = app(StageLegacyExport::class)->handle($fixture['manifest'], 'migration-invalid-reference-shape-001');
    $validation = MigrationValidationResult::query()
        ->where('check_key', 'reference_integrity:relatedIds->businesses')
        ->sole();

    expect($batch)
        ->status->toBe(LegacyImportBatchStatus::StagedWithExceptions)
        ->exception_count->toBe(1)
        ->and($validation->status)->toBe(MigrationValidationStatus::Failed)
        ->and($validation->actual['invalid_type_count'])->toBe(1)
        ->and(LegacyMigrationException::query()->sole()->code)->toBe('invalid_reference_type')
        ->and(LegacyIdMapping::query()->count())->toBe(0);
});
