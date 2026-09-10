<?php

use App\Actions\CullIpilRescueCorpus;
use App\Contracts\IpilCullSource;
use App\Support\IpilRescue\CanonicalJson;
use App\Support\IpilRescue\ConvexAuthenticatedMediaRetriever;
use App\Support\IpilRescue\RescueCorpusSemantics;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->cullRoot = storage_path('app/private/ipil-rescue/synthetic-gate-2-'.Str::uuid());
    config()->set('ipil_rescue.destination_root', $this->cullRoot);
    config()->set('ipil_rescue.chunk_size', 1);
    config()->set('ipil_rescue.media_attempts', 3);
    $this->source = new SyntheticIpilCullSource;
    app()->instance(IpilCullSource::class, $this->source);
});

afterEach(function () {
    File::deleteDirectory($this->cullRoot);
});

test('ipil cull transports paged source evidence and finalizes a verified immutable corpus', function () {
    $exitCode = Artisan::call('ipil:cull', [
        '--accept-source-access' => true,
        '--confirm-source-access' => true,
        '--json' => true,
    ]);

    if ($exitCode !== 0) {
        throw new RuntimeException(Artisan::output());
    }

    $summary = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    $snapshotPath = $summary['snapshot_path'];

    expect($summary)->toMatchArray([
        'passed' => true,
        'state' => 'FINALIZED',
        'exceptions' => 4,
        'verification' => 'PASS',
        'domain_writes' => false,
        'bulk_rescue_executed' => false,
    ])->and($summary['database']['tables'])->toBe(2)
        ->and($summary['database']['rows'])->toBe(3)
        ->and($summary['media']['document_records'])->toBe(4)
        ->and($summary['media']['objects_retrieved'])->toBe(3)
        ->and($summary['media']['missing_source_bytes'])->toBe(1)
        ->and($summary['media']['retrieval_failures'])->toBe(0)
        ->and($summary['media']['unresolved_objects'])->toBe(1)
        ->and($summary['pricing']['records'])->toBe(1)
        ->and($this->source->pageCalls)->toBe([
            'applications:null',
            'applications:1',
            'businesses:null',
        ])->and(File::isDirectory($snapshotPath))->toBeTrue()
        ->and(File::exists($snapshotPath.'/source-export.zip'))->toBeFalse()
        ->and(File::exists($snapshotPath.'/run-state.json'))->toBeFalse();

    $applicationRows = collect(preg_split('/\R/', trim(File::get($snapshotPath.'/source/database/tables/applications.jsonl'))))
        ->map(fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR));

    expect($applicationRows)->toHaveCount(2)
        ->and($applicationRows[0])->toMatchArray(['_id' => 'app-1', 'status' => 3, 'nullable' => null])
        ->and($applicationRows[1]['status'])->toBe('Released')
        ->and(fn () => app(CullIpilRescueCorpus::class)->handle($summary['snapshot']))
        ->toThrow(RuntimeException::class, 'immutable');
});

test('ipil cull fails closed without explicit confirmations and preflight discloses no credentials', function () {
    expect(Artisan::call('ipil:cull', ['--json' => true]))->toBe(1);

    $rejected = Artisan::output();

    expect($rejected)->toContain('--accept-source-access')
        ->not->toContain('synthetic-secret-token');

    expect(Artisan::call('ipil:cull', [
        '--preflight' => true,
        '--accept-source-access' => true,
        '--json' => true,
    ]))->toBe(0);

    $preflight = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($preflight)->toMatchArray([
        'passed' => true,
        'destination_class' => 'local-private',
        'read_only' => true,
        'write_back' => false,
        'network_operation' => true,
    ])->and(Artisan::output())->not->toContain('synthetic-secret-token')
        ->and(File::directories($this->cullRoot))->toBe([]);
});

test('an interrupted cull resumes without duplicating verified evidence', function () {
    $snapshot = 'synthetic-resume-001';
    $this->source->interruptOnceOn = 'storage-missing';

    expect(fn () => app(CullIpilRescueCorpus::class)->handle($snapshot))
        ->toThrow(RuntimeException::class, 'synthetic interruption');

    $working = $this->cullRoot.'/.in-progress/'.$snapshot;
    $control = $this->cullRoot.'/.control/'.$snapshot;
    $state = json_decode(File::get($control.'/run-state.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($state['state'])->toBe('FAILED')
        ->and(File::exists($control.'/media-checkpoint.jsonl'))->toBeTrue()
        ->and(File::exists($control.'/source-export.zip'))->toBeTrue();

    $result = app(CullIpilRescueCorpus::class)->handle($snapshot);
    $manifestLines = preg_split('/\R/', trim(File::get($result->snapshotPath.'/source/media/media-manifest.jsonl')));

    expect($result->snapshotId)->toBe($snapshot)
        ->and($manifestLines)->toHaveCount(5)
        ->and($this->source->retrievals['storage-good'])->toBe(1);
});

test('resume rejects changed bytes instead of overwriting a verified media checkpoint', function () {
    $snapshot = 'synthetic-resume-mismatch';
    $this->source->interruptOnceOn = 'storage-missing';

    try {
        app(CullIpilRescueCorpus::class)->handle($snapshot);
    } catch (RuntimeException) {
    }

    $object = $this->cullRoot.'/.in-progress/'.$snapshot.'/source/media/objects/'.hash('sha256', 'storage-good').'.bin';
    File::put($object, 'changed');

    expect(fn () => app(CullIpilRescueCorpus::class)->handle($snapshot))
        ->toThrow(RuntimeException::class, 'differs from its verified checkpoint');
});

test('resume rejects a changed database table instead of rebinding it', function () {
    $snapshot = 'synthetic-database-mismatch';
    $this->source->interruptOnceOn = 'storage-missing';

    try {
        app(CullIpilRescueCorpus::class)->handle($snapshot);
    } catch (RuntimeException) {
    }

    $table = $this->cullRoot.'/.in-progress/'.$snapshot.'/source/database/tables/applications.jsonl';
    File::append($table, CanonicalJson::encode(['_id' => 'tampered'])."\n");

    expect(fn () => app(CullIpilRescueCorpus::class)->handle($snapshot))
        ->toThrow(RuntimeException::class, 'database table differs from its verified checkpoint');
});

test('public and production destinations are rejected before source acquisition', function () {
    config()->set('ipil_rescue.destination_root', public_path('ipil-rescue'));

    expect(fn () => app(CullIpilRescueCorpus::class)->preflight())
        ->toThrow(RuntimeException::class, 'local private storage')
        ->and($this->source->preflightCalls)->toBe(0);
});

test('a private destination that traverses a symbolic link is rejected', function () {
    $link = storage_path('app/private/ipil-rescue-symlink-'.Str::uuid());
    $target = sys_get_temp_dir().'/ipil-rescue-target-'.Str::uuid();
    File::ensureDirectoryExists($target);
    symlink($target, $link);
    config()->set('ipil_rescue.destination_root', $link.'/snapshots');

    try {
        expect(fn () => app(CullIpilRescueCorpus::class)->preflight())
            ->toThrow(RuntimeException::class, 'symbolic link')
            ->and($this->source->preflightCalls)->toBe(0);
    } finally {
        unlink($link);
        File::deleteDirectory($target);
    }
});

test('ordinary local workflows never invoke ipil cull', function () {
    $paths = [
        base_path('bin/bpls-product-lab'),
        base_path('bin/bpls-ipil-rescue-lab'),
        base_path('app/Actions/InstallBplsBaseline.php'),
        base_path('app/Console/Commands/IpilSeedCommand.php'),
        base_path('app/Console/Commands/IpilAuditCommand.php'),
        base_path('routes/console.php'),
    ];

    foreach ($paths as $path) {
        expect(File::get($path))->not->toContain('ipil:cull');
    }
});

test('the Convex adapter is pinned to the approved complete source inventory', function () {
    $datasets = config('ipil_rescue.required_datasets');

    expect($datasets)->toBeArray()
        ->toHaveCount(53)
        ->toContain('activity_logs', 'businesses', 'business_permit_applications', 'payments', 'report_exports')
        ->and(array_unique($datasets))->toHaveCount(53);
});

test('authenticated Convex media retrieval uses bounded retries', function () {
    Http::fakeSequence()
        ->push(['status' => 'success', 'value' => ['_id' => 'synthetic-user']])
        ->push(['status' => 'success', 'value' => []])
        ->push(['status' => 'success', 'value' => 'https://media.example/object'])
        ->push('temporary', 503)
        ->push(['status' => 'success', 'value' => 'https://media.example/object'])
        ->push('temporary', 503)
        ->push(['status' => 'success', 'value' => 'https://media.example/object'])
        ->push('verified-bytes', 200);

    $retriever = app(ConvexAuthenticatedMediaRetriever::class);
    $retriever->assertAuthenticated('https://synthetic.convex.cloud', 'synthetic-secret-token');
    $result = $retriever->retrieve('https://synthetic.convex.cloud', 'synthetic-secret-token', 'storage-id', 3);

    expect($result)->toBe([
        'status' => 'retrieved',
        'bytes' => 'verified-bytes',
        'attempts' => 3,
        'error' => null,
    ]);
    Http::assertSentCount(8);
});

final class SyntheticIpilCullSource implements IpilCullSource
{
    public int $preflightCalls = 0;

    /** @var list<string> */
    public array $pageCalls = [];

    /** @var array<string, int> */
    public array $retrievals = [];

    public ?string $interruptOnceOn = null;

    private bool $interrupted = false;

    public function preflight(): array
    {
        $this->preflightCalls++;

        return [
            'source_system' => 'ipil-synthetic',
            'deployment_identity_sha256' => hash('sha256', 'synthetic-deployment'),
            'source_engine' => 'convex',
            'source_engine_version' => 'synthetic-1',
            'source_schema_sha256' => hash('sha256', 'synthetic-schema'),
            'consistency_method' => 'synthetic immutable source snapshot',
            'tool' => [
                'name' => 'synthetic-source',
                'version' => '1.0.0',
                'sha256' => hash('sha256', 'synthetic-source'),
            ],
        ];
    }

    public function begin(string $workingDirectory): void
    {
        File::put($workingDirectory.'/source-export.zip', 'synthetic-private-export');
    }

    public function datasets(): array
    {
        return [[
            'name' => 'applications',
            'identity_field' => '_id',
            'fields_sha256' => hash('sha256', 'application-fields'),
            'relationships' => [['field' => 'businessId', 'target_dataset' => 'businesses', 'cardinality' => 'one', 'required' => true]],
        ], [
            'name' => 'businesses',
            'identity_field' => '_id',
            'fields_sha256' => hash('sha256', 'business-fields'),
            'relationships' => [],
        ]];
    }

    public function databasePage(string $dataset, ?string $cursor, int $limit): array
    {
        $this->pageCalls[] = $dataset.':'.($cursor ?? 'null');
        $rows = [
            'applications' => [
                ['_id' => 'app-1', 'businessId' => 'business-1', 'status' => 3, 'nullable' => null],
                ['_id' => 'app-2', 'businessId' => 'business-1', 'status' => 'Released'],
            ],
            'businesses' => [['_id' => 'business-1', 'name' => 'Synthetic Business']],
        ][$dataset];
        $offset = $cursor === null ? 0 : (int) $cursor;
        $page = array_slice($rows, $offset, $limit);
        $next = $offset + count($page);

        return [
            'rows' => array_map(fn (array $row): array => ['raw' => CanonicalJson::encode($row), 'value' => $row], $page),
            'next_cursor' => $next < count($rows) ? (string) $next : null,
        ];
    }

    public function mediaObjects(): array
    {
        $good = 'synthetic-media-bytes';

        return [
            $this->media('businesses-media', 'doc-1', 'storage-good', 'metadata-relationship', 'ASSOCIATED', $good),
            $this->media('businesses-media', 'doc-missing', 'storage-missing', 'metadata-relationship', 'ASSOCIATED', 'missing'),
            $this->media('storage-objects', 'storage-unresolved', 'storage-unresolved', 'storage-object', 'UNRESOLVED', $good),
            $this->media('businesses-media', 'doc-duplicate', 'storage-good', 'metadata-relationship', 'ASSOCIATED', $good),
            $this->media('businesses-media', 'doc-corrupt', 'storage-corrupt', 'metadata-relationship', 'ASSOCIATED', 'expected-different-bytes'),
        ];
    }

    public function retrieveMedia(array $object, int $maximumAttempts): array
    {
        $storage = $object['storage_id'];
        $this->retrievals[$storage] = ($this->retrievals[$storage] ?? 0) + 1;

        if ($storage === $this->interruptOnceOn && ! $this->interrupted) {
            $this->interrupted = true;

            throw new RuntimeException('synthetic interruption');
        }

        if ($storage === 'storage-missing') {
            return ['status' => 'source-missing', 'bytes' => null, 'attempts' => 1, 'error' => 'Synthetic source bytes are missing.'];
        }

        return ['status' => 'retrieved', 'bytes' => 'synthetic-media-bytes', 'attempts' => 1, 'error' => null];
    }

    public function pricingEvidence(): array
    {
        $record = [
            'schema_version' => RescueCorpusSemantics::PricingRecordVersion,
            'source_dataset' => 'pricing-corpus',
            'source_key_sha256' => hash('sha256', 'pricing-source'),
            'knowledge_kind' => 'raw-dataset-evidence',
            'candidate_payload_sha256' => hash('sha256', 'pricing-candidate'),
            'confidence' => 'established',
            'evidence' => ['classification' => 'synthetic'],
        ];

        return [
            'manifest' => [
                'schema_version' => RescueCorpusSemantics::PricingManifestVersion,
                'evidence_class' => 'interpreted-pricing-knowledge',
                'raw_database_fingerprint_sha256' => hash('sha256', 'raw-pricing'),
                'records_relative_path' => 'source/pricing/records.jsonl',
                'records_sha256' => str_repeat('0', 64),
                'record_count' => 1,
                'interpreter' => 'synthetic-pricing-reference',
                'interpreter_version' => '1.0.0',
                'interpretation_state' => 'candidate-only',
                'source_datasets' => [['dataset' => 'applications', 'row_count' => 2, 'sha256' => hash('sha256', 'source-pricing')]],
            ],
            'records' => [$record],
        ];
    }

    /** @return array<string, mixed> */
    private function media(string $dataset, string $sourceKey, string $storageId, string $role, string $association, string $expectedBytes): array
    {
        return [
            'source_dataset' => $dataset,
            'source_key' => $sourceKey,
            'storage_id' => $storageId,
            'relationship' => $role === 'storage-object' ? '_storage' : 'businesses.documents[0].storageId',
            'evidence_role' => $role,
            'association_state' => $association,
            'document_type' => $role === 'storage-object' ? null : 'DTI Certificate',
            'original_filename' => $role === 'storage-object' ? null : 'synthetic.txt',
            'declared_mime' => 'text/plain',
            'declared_size_bytes' => strlen($expectedBytes),
            'declared_sha256' => hash('sha256', $expectedBytes),
        ];
    }
}
