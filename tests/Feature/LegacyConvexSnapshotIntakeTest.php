<?php

use App\Actions\PrepareLegacyConvexSnapshot;
use App\Actions\StageLegacyExport;
use App\Enums\LegacyImportBatchStatus;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LegacyRecord;
use App\Models\PermitApplication;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** @var list<string> $legacyConvexSnapshotFixtures */
$legacyConvexSnapshotFixtures = [];

/**
 * @param  array<string, string>  $entries
 */
function createConvexSnapshotArchive(array $entries): string
{
    global $legacyConvexSnapshotFixtures;

    $path = storage_path('framework/testing/convex-snapshot-'.Str::uuid().'.zip');
    File::ensureDirectoryExists(dirname($path));
    $zip = new ZipArchive;
    expect($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    foreach ($entries as $entry => $contents) {
        expect($zip->addFromString($entry, $contents))->toBeTrue();
    }
    expect($zip->close())->toBeTrue();
    $legacyConvexSnapshotFixtures[] = $path;

    return $path;
}

/** @param list<array<string, mixed>> $rows */
function convexJsonLines(array $rows): string
{
    return collect($rows)
        ->map(fn (array $row): string => json_encode($row, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))
        ->implode("\n")."\n";
}

afterEach(function (): void {
    global $legacyConvexSnapshotFixtures;

    foreach ($legacyConvexSnapshotFixtures as $path) {
        File::delete($path);
    }
    $legacyConvexSnapshotFixtures = [];
});

test('an authorized snapshot becomes immutable payload-free intake evidence and a valid staging manifest', function () {
    Storage::fake('local');
    $storedDocument = "%PDF-1.4\nAuthorized snapshot fixture\n%%EOF\n";
    $archive = createConvexSnapshotArchive([
        'business_owners/documents.jsonl' => convexJsonLines([
            ['_id' => 'owner-001', 'firstName' => 'Sensitive', 'lastName' => 'Owner'],
        ]),
        'businesses/documents.jsonl' => convexJsonLines([
            ['_id' => 'business-001', 'ownerId' => 'owner-001', 'name' => 'Sensitive Business'],
        ]),
        'business_permit_applications/documents.jsonl' => convexJsonLines([
            ['_id' => 'application-001', 'businessOwnerId' => 'owner-001', 'businessId' => 'business-001', 'status' => 'Draft', 'applicationNumber' => 'BPA-SECRET'],
        ]),
        '_storage/documents.jsonl' => convexJsonLines([[
            '_id' => 'storage-secret',
            'sha256' => base64_encode(hash('sha256', $storedDocument, true)),
            'size' => strlen($storedDocument),
            'contentType' => 'application/pdf',
        ]]),
        '_storage/storage-secret.pdf' => $storedDocument,
        'businesses/generated_schema.jsonl' => "{}\n",
    ]);

    $result = app(PrepareLegacyConvexSnapshot::class)->handle(
        $archive,
        'convex-intake-001',
        'adjoining-porcupine-740',
        '2026-08-16T16:00:00+08:00',
        'authorized@example.test',
        'Convex Dashboard backup download',
    );
    $report = File::get($result['report_path']);
    $manifest = json_decode(File::get($result['manifest_path']), true, flags: JSON_THROW_ON_ERROR);
    $storageIndex = json_decode(File::get($result['storage_index_path']), true, flags: JSON_THROW_ON_ERROR);
    $batch = app(StageLegacyExport::class)->handle($result['manifest_path'], 'convex-stage-001');

    expect($result['table_count'])->toBe(3)
        ->and($storageIndex['schema_version'])->toBe(PrepareLegacyConvexSnapshot::FileStorageIndexSchemaVersion)
        ->and($result['record_count'])->toBe(3)
        ->and($result['storage_file_count'])->toBe(1)
        ->and($result['storage_bytes'])->toBe(strlen($storedDocument))
        ->and(hash_file('sha256', $result['archive_path']))->toBe(hash_file('sha256', $archive))
        ->and(collect($result['datasets'])->pluck('key')->all())->toBe([
            'business_owners',
            'business_permit_applications',
            'businesses',
        ])
        ->and($manifest['source']['source_type'])->toBe('convex_snapshot_export')
        ->and($manifest['source']['provenance']['production_data'])->toBeTrue()
        ->and($manifest['source']['provenance']['deployment_sha256'])->toBe(hash('sha256', 'adjoining-porcupine-740'))
        ->and($manifest['source']['provenance'])->toMatchArray([
            'file_storage_included' => true,
            'file_storage_count' => 1,
            'file_storage_bytes' => strlen($storedDocument),
        ])
        ->and($storageIndex['entries'][0])->toMatchArray([
            'storage_id' => 'storage-secret',
            'archive_entry' => '_storage/storage-secret.pdf',
            'sha256' => hash('sha256', $storedDocument),
            'size_bytes' => strlen($storedDocument),
            'content_type' => 'application/pdf',
        ])
        ->and($report)->not->toContain('Sensitive', 'BPA-SECRET', 'owner-001', 'business-001', 'storage-secret')
        ->and($batch->status)->toBe(LegacyImportBatchStatus::Staged)
        ->and($batch->staged_record_count)->toBe(3)
        ->and(LegacyRecord::query()->count())->toBe(3)
        ->and(BusinessOwner::query()->count())->toBe(0)
        ->and(Business::query()->count())->toBe(0)
        ->and(PermitApplication::query()->count())->toBe(0);
});

test('snapshot intake refuses incomplete or tampered file storage', function (array $storageEntries, string $message) {
    Storage::fake('local');
    $archive = createConvexSnapshotArchive([
        'business_owners/documents.jsonl' => convexJsonLines([['_id' => 'owner-001']]),
        ...$storageEntries,
    ]);

    expect(fn () => app(PrepareLegacyConvexSnapshot::class)->handle(
        $archive,
        'convex-storage-refusal',
        'adjoining-porcupine-740',
        '2026-08-16T16:00:00+08:00',
        'authorized@example.test',
        'Convex Dashboard backup download',
    ))->toThrow(RuntimeException::class, $message);
})->with(function (): array {
    $expected = 'expected object';

    return [
        'metadata without blob' => [[
            '_storage/documents.jsonl' => convexJsonLines([[
                '_id' => 'storage-001',
                'sha256' => base64_encode(hash('sha256', $expected, true)),
                'size' => strlen($expected),
            ]]),
        ], 'metadata has no matching blob'],
        'blob without metadata' => [[
            '_storage/storage-001.pdf' => $expected,
        ], 'blobs without documents.jsonl metadata'],
        'tampered blob' => [[
            '_storage/documents.jsonl' => convexJsonLines([[
                '_id' => 'storage-001',
                'sha256' => base64_encode(hash('sha256', $expected, true)),
                'size' => strlen($expected),
            ]]),
            '_storage/storage-001.pdf' => 'tampered object',
        ], 'blob checksum does not match'],
        'orphan blob' => [[
            '_storage/documents.jsonl' => convexJsonLines([[
                '_id' => 'storage-001',
                'sha256' => base64_encode(hash('sha256', $expected, true)),
                'size' => strlen($expected),
            ]]),
            '_storage/storage-001.pdf' => $expected,
            '_storage/storage-002.pdf' => $expected,
        ], 'does not resolve to exactly one metadata record'],
    ];
});

test('snapshot intake is idempotent and a stable run refuses changed source evidence', function () {
    Storage::fake('local');
    $firstArchive = createConvexSnapshotArchive([
        'business_owners/documents.jsonl' => convexJsonLines([['_id' => 'owner-001']]),
    ]);
    $secondArchive = createConvexSnapshotArchive([
        'business_owners/documents.jsonl' => convexJsonLines([['_id' => 'owner-002']]),
    ]);
    $action = app(PrepareLegacyConvexSnapshot::class);
    $first = $action->handle($firstArchive, 'convex-intake-stable', 'adjoining-porcupine-740', '2026-08-16T16:00:00+08:00', 'authorized@example.test', 'Convex Dashboard backup download');
    $second = $action->handle($firstArchive, 'convex-intake-stable', 'adjoining-porcupine-740', '2026-08-16T16:00:00+08:00', 'authorized@example.test', 'Convex Dashboard backup download');

    expect($second['archive_checksum'])->toBe($first['archive_checksum'])
        ->and($second['manifest_path'])->toBe($first['manifest_path'])
        ->and(fn () => $action->handle($secondArchive, 'convex-intake-stable', 'adjoining-porcupine-740', '2026-08-16T16:00:00+08:00', 'authorized@example.test', 'Convex Dashboard backup download'))
        ->toThrow(RuntimeException::class, 'different source evidence');
});

test('snapshot intake declares only source-backed unit and fee override relationships', function () {
    Storage::fake('local');
    $archive = createConvexSnapshotArchive([
        'business_permit_applications/documents.jsonl' => convexJsonLines([['_id' => 'application-001']]),
        'businesses/documents.jsonl' => convexJsonLines([['_id' => 'business-001']]),
        'division_groups/documents.jsonl' => convexJsonLines([['_id' => 'division-group-001']]),
        'fee_overrides/documents.jsonl' => convexJsonLines([[
            '_id' => 'fee-override-001',
            'divisionGroupId' => 'division-group-001',
            'feeId' => 'fee-001',
        ]]),
        'fees/documents.jsonl' => convexJsonLines([['_id' => 'fee-001']]),
        'unitsOfMeasurement/documents.jsonl' => convexJsonLines([[
            '_id' => 'unit-001',
            'applicationId' => 'application-001',
            'businessId' => 'business-001',
        ]]),
    ]);

    $result = app(PrepareLegacyConvexSnapshot::class)->handle(
        $archive,
        'convex-reference-catalog',
        'adjoining-porcupine-740',
        '2026-08-16T16:00:00+08:00',
        'authorized@example.test',
        'Convex Dashboard backup download',
    );
    $manifest = json_decode(File::get($result['manifest_path']), true, flags: JSON_THROW_ON_ERROR);
    $datasets = collect($manifest['datasets'])->keyBy('key');

    expect($datasets['unitsOfMeasurement']['references'])->toBe([
        ['field' => 'applicationId', 'target_dataset' => 'business_permit_applications', 'required' => false, 'cardinality' => 'one'],
        ['field' => 'businessId', 'target_dataset' => 'businesses', 'required' => false, 'cardinality' => 'one'],
    ])->and($datasets['fee_overrides']['references'])->toBe([
        ['field' => 'divisionGroupId', 'target_dataset' => 'division_groups', 'required' => true, 'cardinality' => 'one'],
        ['field' => 'feeId', 'target_dataset' => 'fees', 'required' => true, 'cardinality' => 'one'],
    ]);
});

test('snapshot intake refuses unsafe paths and archives without table documents', function (array $entries, string $message) {
    Storage::fake('local');
    $archive = createConvexSnapshotArchive($entries);

    expect(fn () => app(PrepareLegacyConvexSnapshot::class)->handle(
        $archive,
        'convex-intake-refusal',
        'adjoining-porcupine-740',
        '2026-08-16T16:00:00+08:00',
        'authorized@example.test',
        'Convex Dashboard backup download',
    ))->toThrow(RuntimeException::class, $message);
})->with([
    'path traversal' => [
        ['../business_owners/documents.jsonl' => convexJsonLines([['_id' => 'owner-001']])],
        'unsafe ZIP entry path',
    ],
    'no tables' => [
        ['business_owners.jsonl' => convexJsonLines([['_id' => 'owner-001']])],
        'no table documents.jsonl entries',
    ],
]);

test('the command requires dual production-data confirmation and writes structured evidence', function () {
    Storage::fake('local');
    $archive = createConvexSnapshotArchive([
        'business_owners/documents.jsonl' => convexJsonLines([['_id' => 'owner-001']]),
    ]);
    $arguments = [
        'archive' => $archive,
        '--run-id' => 'convex-command-001',
        '--deployment' => 'adjoining-porcupine-740',
        '--captured-at' => '2026-08-16T16:00:00+08:00',
        '--operator' => 'authorized@example.test',
        '--tooling' => 'Convex Dashboard backup download',
        '--json' => true,
    ];

    $this->artisan('legacy:prepare-convex-snapshot', $arguments)->assertFailed();
    $this->artisan('legacy:prepare-convex-snapshot', [
        ...$arguments,
        '--accept-production-data' => true,
        '--confirm-production-data' => true,
    ])->assertSuccessful();

    $root = 'legacy-migrations/convex-snapshots/convex-command-001';
    Storage::disk('local')->assertExists($root.'/snapshot.zip');
    Storage::disk('local')->assertExists($root.'/manifest.json');
    Storage::disk('local')->assertExists($root.'/intake.json');
    Storage::disk('local')->assertExists($root.'/provenance.json');
    Storage::disk('local')->assertExists($root.'/review.md');
    $report = Storage::disk('local')->get($root.'/intake.json');
    $provenance = json_decode(Storage::disk('local')->get($root.'/provenance.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($report)->not->toContain('owner-001', 'authorized@example.test', 'password', 'token', 'cookie')
        ->and($provenance)->toMatchArray([
            'deployment' => 'adjoining-porcupine-740',
            'operator' => 'authorized@example.test',
            'tooling' => 'Convex Dashboard backup download',
        ])
        ->and(json_decode($report, true, flags: JSON_THROW_ON_ERROR)['safety'])->toMatchArray([
            'production_data_present' => true,
            'payloads_in_report' => false,
            'domain_writes' => false,
            'migration_executed' => false,
            'cutover_authorized' => false,
        ]);
});

test('snapshot intake refuses production execution even with confirmations', function () {
    Storage::fake('local');
    $archive = createConvexSnapshotArchive([
        'business_owners/documents.jsonl' => convexJsonLines([['_id' => 'owner-001']]),
    ]);
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('legacy:prepare-convex-snapshot', [
        'archive' => $archive,
        '--run-id' => 'convex-production-refusal',
        '--deployment' => 'adjoining-porcupine-740',
        '--captured-at' => '2026-08-16T16:00:00+08:00',
        '--operator' => 'authorized@example.test',
        '--tooling' => 'Convex Dashboard backup download',
        '--accept-production-data' => true,
        '--confirm-production-data' => true,
        '--json' => true,
    ])->assertFailed();

    Storage::disk('local')->assertMissing('legacy-migrations/convex-snapshots/convex-production-refusal');
});
