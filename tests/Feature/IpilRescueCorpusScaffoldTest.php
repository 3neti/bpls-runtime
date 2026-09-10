<?php

use App\Actions\VerifyIpilRescueCorpus;
use App\Support\IpilRescue\CanonicalJson;
use App\Support\IpilRescue\RescueCorpusManifest;
use App\Support\IpilRescue\SourceIdentity;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->ipilRescueTestRoot = storage_path('app/private/ipil-rescue/testing-'.Str::uuid());
});

afterEach(function () {
    File::deleteDirectory($this->ipilRescueTestRoot);
});

test('a complete synthetic corpus passes stable integrity checks while full verification stays fail closed', function () {
    createSyntheticIpilRescueCorpus($this->ipilRescueTestRoot);
    $verify = app(VerifyIpilRescueCorpus::class);

    $first = $verify->handle($this->ipilRescueTestRoot);
    $second = $verify->handle($this->ipilRescueTestRoot);

    expect($first->toArray())
        ->toBe($second->toArray())
        ->and($first->corpusFingerprint)->toMatch('/^[a-f0-9]{64}$/')
        ->and($first->verifiedFileCount)->toBe(9)
        ->and($first->sourceIdentityCount)->toBe(1);

    expect(Artisan::call('ipil:rescue:verify', [
        'corpus' => $this->ipilRescueTestRoot,
        '--json' => true,
    ]))->toBe(1);

    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($output)
        ->toMatchArray([
            'passed' => false,
            'integrity_passed' => true,
            'verification_complete' => false,
            'offline' => true,
            'healed' => false,
            'domain_writes' => false,
            'verified_file_count' => 9,
            'source_identity_count' => 1,
        ]);
});

test('verification detects changed bytes and never heals them', function () {
    createSyntheticIpilRescueCorpus($this->ipilRescueTestRoot);
    $schemaPath = $this->ipilRescueTestRoot.'/source/database/schema.json';
    File::put($schemaPath, "{\"tables\":[\"changed\"]}\n");
    $changedBytes = File::get($schemaPath);

    expect(fn () => app(VerifyIpilRescueCorpus::class)->handle($this->ipilRescueTestRoot))
        ->toThrow(RuntimeException::class, 'Checksum verification failed');

    expect(File::get($schemaPath))->toBe($changedBytes);
});

test('verification rejects payload files that are not checksum bound', function () {
    createSyntheticIpilRescueCorpus($this->ipilRescueTestRoot);
    File::put($this->ipilRescueTestRoot.'/source/database/unbound-snapshot.sqlite', 'unbound');

    expect(fn () => app(VerifyIpilRescueCorpus::class)->handle($this->ipilRescueTestRoot))
        ->toThrow(RuntimeException::class, 'missing or unbound files');
});

test('verification rejects incomplete remote and unsafe corpora', function () {
    $partialRoot = $this->ipilRescueTestRoot.'/partial';
    createSyntheticIpilRescueCorpus(
        $partialRoot,
        fn (array $manifest): array => [...$manifest, 'state' => 'partial'],
    );

    expect(fn () => app(VerifyIpilRescueCorpus::class)->handle($partialRoot))
        ->toThrow(InvalidArgumentException::class, 'must be complete')
        ->and(fn () => app(VerifyIpilRescueCorpus::class)->handle('https://ipil.example/corpus'))
        ->toThrow(RuntimeException::class, 'URLs are forbidden');

    $unsafeRoot = $this->ipilRescueTestRoot.'/unsafe';
    createSyntheticIpilRescueCorpus(
        $unsafeRoot,
        function (array $manifest): array {
            $manifest['bindings']['../outside.json'] = hash('sha256', 'outside');

            return $manifest;
        },
    );

    expect(fn () => app(VerifyIpilRescueCorpus::class)->handle($unsafeRoot))
        ->toThrow(InvalidArgumentException::class, 'unsafe relative path');

    $gitRoot = $this->ipilRescueTestRoot.'/git';
    createSyntheticIpilRescueCorpus($gitRoot);
    File::ensureDirectoryExists($gitRoot.'/.git');

    expect(fn () => app(VerifyIpilRescueCorpus::class)->handle($gitRoot))
        ->toThrow(RuntimeException::class, 'Git metadata');

    $unapprovedRepositoryRoot = storage_path('framework/testing/ipil-rescue-unapproved-'.Str::uuid());

    try {
        createSyntheticIpilRescueCorpus($unapprovedRepositoryRoot);

        expect(fn () => app(VerifyIpilRescueCorpus::class)->handle($unapprovedRepositoryRoot))
            ->toThrow(RuntimeException::class, 'must use the ignored private rescue root');
    } finally {
        File::deleteDirectory($unapprovedRepositoryRoot);
    }
});

test('seed and audit verify locally then remain fail closed', function () {
    createSyntheticIpilRescueCorpus($this->ipilRescueTestRoot);

    expect(Artisan::call('ipil:seed', [
        'corpus' => $this->ipilRescueTestRoot,
        '--json' => true,
    ]))->toBe(1);

    $seed = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($seed)->toMatchArray([
        'passed' => false,
        'corpus_integrity_passed' => true,
        'verified' => false,
        'seeded' => false,
        'offline' => true,
        'domain_writes' => false,
    ])->and($seed['error'])->toContain('intentionally unavailable until full corpus verification plus mapper and disposition contracts are approved');

    expect(Artisan::call('ipil:audit', [
        'corpus' => $this->ipilRescueTestRoot,
        '--json' => true,
    ]))->toBe(1);

    $audit = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($audit)->toMatchArray([
        'passed' => false,
        'corpus_integrity_passed' => true,
        'verified' => false,
        'audited' => false,
        'offline' => true,
        'domain_writes' => false,
    ])->and($audit['error'])->toContain('intentionally unavailable until full corpus verification and deterministic historical projections exist');
});

test('the local rescue lab exposes only offline scaffold operations', function () {
    createSyntheticIpilRescueCorpus($this->ipilRescueTestRoot);
    $commands = Artisan::all();
    $script = base_path('bin/bpls-ipil-rescue-lab');
    $contents = File::get($script);

    expect($commands)
        ->toHaveKeys(['ipil:rescue:verify', 'ipil:seed', 'ipil:audit'])
        ->not->toHaveKey('ipil:cull')
        ->and(is_executable($script))->toBeTrue()
        ->and($contents)->toContain('set -euo pipefail')
        ->and($contents)->toContain('<verify|seed|audit>')
        ->and($contents)->toContain('Live acquisition is never available')
        ->and($contents)->not->toContain('ipil:cull');

    $process = new Process([$script, 'verify', $this->ipilRescueTestRoot, '--json'], base_path());
    $process->run();
    $output = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    expect($process->getExitCode())->toBe(1)
        ->and($output)->toMatchArray([
            'passed' => false,
            'integrity_passed' => true,
            'verification_complete' => false,
            'offline' => true,
            'domain_writes' => false,
        ]);
});

/**
 * @param  (Closure(array<string, mixed>): array<string, mixed>)|null  $mutateManifest
 */
function createSyntheticIpilRescueCorpus(string $root, ?Closure $mutateManifest = null): void
{
    $deploymentIdentity = hash('sha256', 'synthetic-ipil-deployment');
    $corpusId = 'synthetic-corpus-'.substr(hash('sha256', $root), 0, 12);
    $files = [
        'source/database/schema.json' => "{\"tables\":[\"applications\"]}\n",
        'source/database/table-manifest.json' => "{\"applications\":{\"rows\":1}}\n",
        'source/media/media-manifest.jsonl' => '',
        'source/pricing/pricing-manifest.json' => "{\"records\":0}\n",
        'source/pricing/records.jsonl' => '',
        'provenance/acquisition.json' => "{\"mode\":\"synthetic\",\"network\":false}\n",
        'provenance/tools.json' => "{\"generator\":\"test\"}\n",
        'verification/exceptions.jsonl' => '',
    ];
    $identity = SourceIdentity::fromArray([
        'schema_version' => SourceIdentity::SchemaVersion,
        'source_system' => 'ipil-synthetic',
        'deployment_identity_sha256' => $deploymentIdentity,
        'corpus_id' => $corpusId,
        'dataset' => 'applications',
        'source_key_sha256' => hash('sha256', 'synthetic-application-1'),
        'canonical_payload_sha256' => hash('sha256', '{"id":"synthetic-application-1"}'),
        'raw_evidence_locator' => 'source/database/table-manifest.json',
        'entity_kind' => 'application',
        'mapping_state' => 'observed',
        'mapper_version' => null,
        'disposition' => 'preserve',
        'evidence' => [],
        'authority' => null,
        'target_reference' => null,
        'flags' => ['synthetic'],
    ]);
    $files['provenance/source-identities.jsonl'] = CanonicalJson::encode($identity->toArray())."\n";

    foreach ($files as $relativePath => $contents) {
        File::ensureDirectoryExists(dirname($root.'/'.$relativePath));
        File::put($root.'/'.$relativePath, $contents);
    }

    $bindings = [];

    foreach (array_keys($files) as $relativePath) {
        $bindings[$relativePath] = hash_file('sha256', $root.'/'.$relativePath);
    }

    ksort($bindings);
    $manifest = [
        'schema_version' => RescueCorpusManifest::SchemaVersion,
        'corpus_id' => $corpusId,
        'source' => [
            'system' => 'ipil-synthetic',
            'deployment_identity_sha256' => $deploymentIdentity,
        ],
        'acquisition' => [
            'run_id' => 'synthetic-run-001',
            'consistency_method' => 'synthetic immutable fixture',
        ],
        'state' => 'complete',
        'parent_corpus_id' => null,
        'counts' => [
            'database_rows' => 1,
            'media_metadata' => 0,
            'media_bytes' => 0,
            'pricing_records' => 0,
            'exceptions' => 0,
        ],
        'bindings' => $bindings,
    ];

    if ($mutateManifest !== null) {
        $manifest = $mutateManifest($manifest);
    }

    File::ensureDirectoryExists($root.'/verification');
    File::put(
        $root.'/verification/checksums.sha256',
        collect($manifest['bindings'])
            ->map(fn (string $checksum, string $relativePath): string => "{$checksum}  {$relativePath}")
            ->implode("\n")."\n",
    );
    File::put($root.'/corpus.json', CanonicalJson::encode($manifest)."\n");
}
