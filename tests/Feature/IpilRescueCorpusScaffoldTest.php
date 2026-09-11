<?php

use App\Actions\PersistIpilSeedPlan;
use App\Actions\PlanIpilRescueSeed;
use App\Actions\VerifyIpilRescueCorpus;
use App\Support\IpilRescue\CanonicalJson;
use App\Support\IpilRescue\Gate6ExecutionAuthorization;
use App\Support\IpilRescue\HistoricalAmount;
use App\Support\IpilRescue\HistoricalApplicationPlan;
use App\Support\IpilRescue\HistoricalEvidencePlan;
use App\Support\IpilRescue\HistoricalEvidenceRegistry;
use App\Support\IpilRescue\IpilSeedMappingProfile;
use App\Support\IpilRescue\JsonNumericLexeme;
use App\Support\IpilRescue\RescueCorpusManifest;
use App\Support\IpilRescue\RescueCorpusSemantics;
use App\Support\IpilRescue\SourceIdentity;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->ipilRescueTestRoot = storage_path('app/private/ipil-rescue/testing-'.Str::uuid());
});

afterEach(function () {
    File::deleteDirectory($this->ipilRescueTestRoot);

    if (isset($this->ipilSeedPlanDirectory)) {
        File::deleteDirectory($this->ipilSeedPlanDirectory);
    }
});

test('a complete synthetic corpus passes stable integrity and semantic contract checks', function () {
    createSyntheticIpilRescueCorpus($this->ipilRescueTestRoot);
    $verify = app(VerifyIpilRescueCorpus::class);

    $first = $verify->handle($this->ipilRescueTestRoot);
    $second = $verify->handle($this->ipilRescueTestRoot);

    expect($first->toArray())
        ->toBe($second->toArray())
        ->and($first->corpusFingerprint)->toMatch('/^[a-f0-9]{64}$/')
        ->and($first->verifiedFileCount)->toBe(11)
        ->and($first->sourceIdentityCount)->toBe(3)
        ->and($first->semanticCounts)->toBe([
            'database_rows' => 1,
            'media_metadata' => 1,
            'media_bytes' => 1,
            'pricing_records' => 1,
            'exceptions' => 1,
        ]);

    expect(Artisan::call('ipil:rescue:verify', [
        'corpus' => $this->ipilRescueTestRoot,
        '--json' => true,
    ]))->toBe(0);

    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($output)
        ->toMatchArray([
            'passed' => true,
            'integrity_passed' => true,
            'semantic_contracts_passed' => true,
            'verification_complete' => true,
            'offline' => true,
            'healed' => false,
            'domain_writes' => false,
            'verified_file_count' => 11,
            'source_identity_count' => 3,
        ]);
});

test('verification rejects semantically false row counts even when every checksum is rebound', function () {
    createSyntheticIpilRescueCorpus($this->ipilRescueTestRoot);
    $path = $this->ipilRescueTestRoot.'/source/database/table-manifest.json';
    $manifest = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
    $manifest['tables'][0]['row_count'] = 2;
    replaceBoundSyntheticFile($this->ipilRescueTestRoot, 'source/database/table-manifest.json', CanonicalJson::encode($manifest)."\n");

    expect(fn () => app(VerifyIpilRescueCorpus::class)->handle($this->ipilRescueTestRoot))
        ->toThrow(InvalidArgumentException::class, 'row count does not match');
});

test('verification rejects media whose declared rescued size does not match its bound bytes', function () {
    createSyntheticIpilRescueCorpus($this->ipilRescueTestRoot);
    $path = $this->ipilRescueTestRoot.'/source/media/media-manifest.jsonl';
    $entry = json_decode(trim(File::get($path)), true, flags: JSON_THROW_ON_ERROR);
    $entry['rescued_size_bytes']++;
    replaceBoundSyntheticFile($this->ipilRescueTestRoot, 'source/media/media-manifest.jsonl', CanonicalJson::encode($entry)."\n");

    expect(fn () => app(VerifyIpilRescueCorpus::class)->handle($this->ipilRescueTestRoot))
        ->toThrow(InvalidArgumentException::class, 'Media object integrity');
});

test('verification preserves unresolved unassociated bytes without declaring them orphaned', function () {
    createSyntheticIpilRescueCorpus($this->ipilRescueTestRoot);
    $path = $this->ipilRescueTestRoot.'/source/media/media-manifest.jsonl';
    $entry = json_decode(trim(File::get($path)), true, flags: JSON_THROW_ON_ERROR);
    $entry['evidence_role'] = 'storage-object';
    $entry['association_state'] = 'UNRESOLVED';
    $entry['disposition'] = 'unassociated-byte';
    $entry['finding_codes'] = ['unassociated-byte'];

    $result = (new RescueCorpusSemantics)->verifyMedia(
        CanonicalJson::encode($entry)."\n",
        fn (string $relativePath): string => File::get($this->ipilRescueTestRoot.'/'.$relativePath),
    );

    expect($result)->toMatchArray([
        'metadata' => 0,
        'bytes' => 1,
    ]);

    $accessDenied = $entry;
    $accessDenied['rescued_size_bytes'] = null;
    $accessDenied['object_relative_path'] = null;
    $accessDenied['sha256'] = null;
    $accessDenied['bytes_verified'] = false;
    $accessDenied['disposition'] = 'access-denied';
    $accessDenied['finding_codes'] = ['access-denied'];

    expect((new RescueCorpusSemantics)->verifyMedia(
        CanonicalJson::encode($accessDenied)."\n",
        fn (string $relativePath): string => File::get($this->ipilRescueTestRoot.'/'.$relativePath),
    ))->toMatchArray([
        'metadata' => 0,
        'bytes' => 0,
    ]);

    $entry['disposition'] = 'orphan-byte';
    $entry['finding_codes'] = ['orphan-byte'];

    expect(fn () => (new RescueCorpusSemantics)->verifyMedia(
        CanonicalJson::encode($entry)."\n",
        fn (string $relativePath): string => File::get($this->ipilRescueTestRoot.'/'.$relativePath),
    ))->toThrow(InvalidArgumentException::class, 'confirmed orphan classification');
});

test('verification keeps interpreted pricing candidate only and rejects canonical activation', function () {
    createSyntheticIpilRescueCorpus($this->ipilRescueTestRoot);
    $path = $this->ipilRescueTestRoot.'/source/pricing/pricing-manifest.json';
    $manifest = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
    $manifest['interpretation_state'] = 'canonical';
    replaceBoundSyntheticFile($this->ipilRescueTestRoot, 'source/pricing/pricing-manifest.json', CanonicalJson::encode($manifest)."\n");

    expect(fn () => app(VerifyIpilRescueCorpus::class)->handle($this->ipilRescueTestRoot))
        ->toThrow(InvalidArgumentException::class, 'candidate-only');
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

test('seed and audit require explicit Gate 6 execution artifacts', function () {
    createSyntheticIpilRescueCorpus($this->ipilRescueTestRoot);

    expect(Artisan::call('ipil:seed', [
        'corpus' => $this->ipilRescueTestRoot,
        '--json' => true,
    ]))->toBe(1);

    $seed = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($seed)->toMatchArray([
        'passed' => false,
        'corpus_integrity_passed' => true,
        'verified' => true,
        'seeded' => false,
        'offline' => true,
        'domain_writes' => false,
    ])->and($seed['error'])->toContain('explicit, fully confirmed --execute interface');

    expect(Artisan::call('ipil:audit', [
        'corpus' => $this->ipilRescueTestRoot,
        '--json' => true,
    ]))->toBe(1);

    $audit = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($audit)->toMatchArray([
        'passed' => false,
        'corpus_integrity_passed' => true,
        'verified' => true,
        'audited' => false,
        'offline' => true,
        'domain_writes' => false,
    ])->and($audit['error'])->toContain('exact accepted Gate 5 Execution Manifest is required');
});

test('Gate 6 execution refuses a non PostgreSQL target before materialization', function () {
    createSyntheticIpilRescueCorpus($this->ipilRescueTestRoot);

    expect(Artisan::call('ipil:seed', [
        'corpus' => $this->ipilRescueTestRoot,
        '--execute' => true,
        '--manifest' => storage_path('app/private/ipil-rescue/plans/missing.json'),
        '--confirm-corpus' => IpilSeedMappingProfile::CanonicalCorpusId,
        '--confirm-profile' => IpilSeedMappingProfile::Name,
        '--environment' => 'local-postgresql',
        '--json' => true,
    ]))->toBe(1);

    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    expect($output)->toMatchArray(['passed' => false, 'seeded' => false, 'source_write' => false, 'cloud_writes' => false]);
});

test('the local rescue lab exposes only offline scaffold operations', function () {
    createSyntheticIpilRescueCorpus($this->ipilRescueTestRoot);
    $commands = Artisan::all();
    $script = base_path('bin/bpls-ipil-rescue-lab');
    $contents = File::get($script);

    expect($commands)
        ->toHaveKeys(['ipil:cull', 'ipil:rescue:verify', 'ipil:seed', 'ipil:audit'])
        ->and(is_executable($script))->toBeTrue()
        ->and($contents)->toContain('set -euo pipefail')
        ->and($contents)->toContain('<verify|plan|seed|audit>')
        ->and($contents)->toContain('Live acquisition is never available')
        ->and($contents)->not->toContain('ipil:cull');

    $process = new Process([$script, 'verify', $this->ipilRescueTestRoot, '--json'], base_path());
    $process->run();
    $output = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    expect($process->getExitCode())->toBe(0)
        ->and($output)->toMatchArray([
            'passed' => true,
            'integrity_passed' => true,
            'semantic_contracts_passed' => true,
            'verification_complete' => true,
            'offline' => true,
            'domain_writes' => false,
        ]);
});

test('the offline seed planner deterministically dispositions every verified source identity', function () {
    createSyntheticIpilRescueCorpus($this->ipilRescueTestRoot);
    $verification = app(VerifyIpilRescueCorpus::class)->handle($this->ipilRescueTestRoot);
    $profile = IpilSeedMappingProfile::synthetic($verification->corpusId, $verification->corpusFingerprint, ['applications' => 1], 1, 1);
    $planner = app(PlanIpilRescueSeed::class);

    $first = $planner->handle($this->ipilRescueTestRoot, $profile);
    $second = $planner->handle($this->ipilRescueTestRoot, $profile);

    expect($first->fingerprint)->toBe($second->fingerprint)
        ->and($first->semanticPayload)->toBe($second->semanticPayload)
        ->and($first->semanticPayload['coverage'])->toMatchArray([
            'database_rows' => 1,
            'media_records' => 1,
            'pricing_records' => 1,
            'source_identities' => 3,
            'unplanned_source_identities' => 0,
            'disposition_counts' => ['PRESERVE_UNINTERPRETED' => 2, 'REFERENCE_DATA' => 1],
        ])
        ->and($first->semanticPayload['protections'])->toMatchArray([
            'database_writes' => false,
            'domain_models_created' => false,
            'network_access' => false,
            'current_pricing_invoked' => false,
            'historical_is_operational' => false,
        ]);
});

test('the planner rejects corpus fingerprint and table inventory drift', function () {
    createSyntheticIpilRescueCorpus($this->ipilRescueTestRoot);
    $verification = app(VerifyIpilRescueCorpus::class)->handle($this->ipilRescueTestRoot);
    $wrongFingerprint = IpilSeedMappingProfile::synthetic($verification->corpusId, str_repeat('a', 64), ['applications' => 1], 1, 1);
    $wrongInventory = IpilSeedMappingProfile::synthetic($verification->corpusId, $verification->corpusFingerprint, ['unknown' => 1], 1, 1);

    expect(fn () => app(PlanIpilRescueSeed::class)->handle($this->ipilRescueTestRoot, $wrongFingerprint))
        ->toThrow(RuntimeException::class, 'does not match the mapping profile binding')
        ->and(fn () => app(PlanIpilRescueSeed::class)->handle($this->ipilRescueTestRoot, $wrongInventory))
        ->toThrow(RuntimeException::class, 'table inventory or row counts drift');
});

test('private seed plan artifacts are immutable and contain no materialization authority', function () {
    createSyntheticIpilRescueCorpus($this->ipilRescueTestRoot);
    $verification = app(VerifyIpilRescueCorpus::class)->handle($this->ipilRescueTestRoot);
    $profile = IpilSeedMappingProfile::synthetic($verification->corpusId, $verification->corpusFingerprint, ['applications' => 1], 1, 1);
    $plan = app(PlanIpilRescueSeed::class)->handle($this->ipilRescueTestRoot, $profile);
    $artifacts = app(PersistIpilSeedPlan::class)->handle($plan);
    $this->ipilSeedPlanDirectory = $artifacts['directory'];
    $again = app(PersistIpilSeedPlan::class)->handle($plan);
    $manifest = json_decode(File::get($artifacts['execution_manifest']), true, flags: JSON_THROW_ON_ERROR);

    expect($again)->toBe($artifacts)
        ->and($manifest)->toMatchArray(['execution_authorized' => false, 'domain_writes' => false, 'network_access' => false])
        ->and($artifacts['directory'])->toStartWith(storage_path('app/private/ipil-rescue/plans/'));
});

test('historical money preserves exact lexemes and never rounds non-cent evidence', function (string $lexeme, string $decimal, ?string $minorUnits) {
    $amount = HistoricalAmount::fromLexeme($lexeme);

    expect($amount->sourceLexeme)->toBe($lexeme)
        ->and($amount->decimal)->toBe($decimal)
        ->and($amount->minorUnits)->toBe($minorUnits)
        ->and($amount->isCentExact())->toBe($minorUnits !== null);
})->with([
    ['0', '0', '0'],
    ['001.2300', '1.23', '123'],
    ['1.234', '1.234', null],
    ['1e3', '1000', '100000'],
    ['12.3e-2', '0.123', null],
    ['-0.010', '-0.01', '-1'],
]);

test('historical application plans are structurally non-operational', function () {
    $projection = new HistoricalApplicationPlan(hash('sha256', 'application'), 'Renewal', 'Released', 2025);

    expect($projection->toArray())->toMatchArray([
        'target_status' => 'historical_evidence',
        'operationally_eligible' => false,
        'actor_id' => null,
        'current_liability' => null,
        'allowed_actions' => [],
    ]);
});

test('Gate 6 exact JSON numeric extraction preserves the source spelling', function () {
    $json = '{"whole":12,"fractional":120.3400,"scientific":1.25e+3,"missing":null}';

    expect(JsonNumericLexeme::field($json, 'whole'))->toBe('12')
        ->and(JsonNumericLexeme::field($json, 'fractional'))->toBe('120.3400')
        ->and(JsonNumericLexeme::field($json, 'scientific'))->toBe('1.25e+3')
        ->and(JsonNumericLexeme::field($json, 'missing'))->toBeNull()
        ->and(JsonNumericLexeme::field($json, 'absent'))->toBeNull();
});

test('Gate 6 authorization rejects the test environment before any materialization write', function () {
    expect(fn () => Gate6ExecutionAuthorization::issue('/missing/private/manifest.json', 'local-postgresql'))
        ->toThrow(RuntimeException::class, 'restricted to the explicit local-postgresql environment');
});

test('Gate 6 historical tables are structurally separate from operational tables', function () {
    expect(Schema::hasTable('ipil_rescue_import_runs'))->toBeTrue()
        ->and(Schema::hasTable('ipil_rescue_phase_checkpoints'))->toBeTrue()
        ->and(Schema::hasTable('ipil_rescue_source_identities'))->toBeTrue()
        ->and(Schema::hasTable('ipil_historical_applications'))->toBeTrue()
        ->and(Schema::hasColumn('ipil_historical_applications', 'operationally_eligible'))->toBeTrue()
        ->and(Schema::hasColumn('ipil_historical_payment_schedules', 'total_amount_source_lexeme'))->toBeTrue();
});

test('historical evidence contracts preserve duplicate and orphan claims without operational effects', function () {
    $receipt = new HistoricalEvidencePlan('receipt_claim', hash('sha256', 'receipt-one'), 'MAP_AS_HISTORICAL_EVIDENCE', 'ESTABLISHED', ['receipt_number_claim' => 'SYNTHETIC-DUPLICATE']);
    $duplicate = new HistoricalEvidencePlan('receipt_claim', hash('sha256', 'receipt-two'), 'MAP_AS_HISTORICAL_EVIDENCE', 'ESTABLISHED', ['receipt_number_claim' => 'SYNTHETIC-DUPLICATE']);
    $permit = new HistoricalEvidencePlan('permit_claim', hash('sha256', 'permit-orphan'), 'PRESERVE_UNINTERPRETED', 'AMBIGUOUS', ['application_source_identity' => null], true);

    expect($receipt->toArray()['facts']['receipt_number_claim'])->toBe($duplicate->toArray()['facts']['receipt_number_claim'])
        ->and($receipt->sourceIdentitySha256)->not->toBe($duplicate->sourceIdentitySha256)
        ->and($permit->toArray())->toMatchArray([
            'orphaned' => true,
            'historical' => true,
            'operationally_eligible' => false,
            'creates_current_user' => false,
            'creates_current_finance' => false,
            'creates_current_permit' => false,
            'creates_spatie_media' => false,
        ]);
});

test('synthetic historical materialization intents replay idempotently and reject conflicting targets', function () {
    $registry = new HistoricalEvidenceRegistry;
    $sourceIdentity = hash('sha256', 'same-source');
    $payment = new HistoricalEvidencePlan('payment', $sourceIdentity, 'MAP_AS_HISTORICAL_EVIDENCE', 'ESTABLISHED', ['amount' => HistoricalAmount::fromLexeme('12.34')]);
    $conflict = new HistoricalEvidencePlan('permit_claim', $sourceIdentity, 'MAP_AS_HISTORICAL_EVIDENCE', 'ESTABLISHED', ['permit_number' => 'SYNTHETIC']);

    expect($registry->record($payment))->toBeTrue()
        ->and($registry->record($payment))->toBeFalse()
        ->and(fn () => $registry->record($conflict))->toThrow(RuntimeException::class, 'conflicting historical targets');
});

/**
 * @param  (Closure(array<string, mixed>): array<string, mixed>)|null  $mutateManifest
 */
function createSyntheticIpilRescueCorpus(string $root, ?Closure $mutateManifest = null): void
{
    $deploymentIdentity = hash('sha256', 'synthetic-ipil-deployment');
    $corpusId = 'synthetic-corpus-'.substr(hash('sha256', $root), 0, 12);
    $databaseBytes = CanonicalJson::encode(['_id' => 'synthetic-application-1', 'status' => 'Draft'])."\n";
    $mediaBytes = 'synthetic-media-bytes';
    $pricingRecord = [
        'schema_version' => RescueCorpusSemantics::PricingRecordVersion,
        'source_dataset' => 'pricing-knowledge',
        'source_key_sha256' => hash('sha256', 'synthetic-price-source'),
        'knowledge_kind' => 'fee-rule-candidate',
        'candidate_payload_sha256' => hash('sha256', 'synthetic-price-candidate'),
        'confidence' => 'probable',
        'evidence' => ['source' => 'synthetic fee table'],
    ];
    $pricingBytes = CanonicalJson::encode($pricingRecord)."\n";
    $mediaEntry = [
        'schema_version' => RescueCorpusSemantics::MediaEntryVersion,
        'source_dataset' => 'media-documents',
        'source_key_sha256' => hash('sha256', 'synthetic-media-source'),
        'storage_identifier_sha256' => hash('sha256', 'synthetic-storage-id'),
        'relationship' => 'businesses.documents[0].storageId',
        'evidence_role' => 'metadata-relationship',
        'document_type' => 'DTI Certificate',
        'original_filename' => 'synthetic-dti.txt',
        'declared_mime' => 'text/plain',
        'detected_mime' => 'text/plain',
        'declared_size_bytes' => strlen($mediaBytes),
        'rescued_size_bytes' => strlen($mediaBytes),
        'object_relative_path' => 'source/media/objects/synthetic-media.bin',
        'sha256' => hash('sha256', $mediaBytes),
        'retrieval_attempts' => 1,
        'bytes_verified' => true,
        'association_state' => 'ASSOCIATED',
        'disposition' => 'rescued',
        'finding_codes' => [],
    ];
    $databaseSchema = [
        'schema_version' => RescueCorpusSemantics::DatabaseSchemaVersion,
        'source_engine' => 'convex',
        'source_engine_version' => null,
        'source_schema_sha256' => hash('sha256', 'synthetic-schema'),
        'datasets' => [[
            'name' => 'applications',
            'identity_field' => '_id',
            'fields_sha256' => hash('sha256', 'synthetic-application-fields'),
            'relationships' => [],
        ]],
    ];
    $databaseManifest = [
        'schema_version' => RescueCorpusSemantics::DatabaseManifestVersion,
        'format' => 'jsonl',
        'consistency_method' => 'synthetic immutable fixture',
        'tables' => [[
            'dataset' => 'applications',
            'relative_path' => 'source/database/tables/applications.jsonl',
            'row_count' => 1,
            'sha256' => hash('sha256', $databaseBytes),
        ]],
    ];
    $pricingManifest = [
        'schema_version' => RescueCorpusSemantics::PricingManifestVersion,
        'evidence_class' => 'interpreted-pricing-knowledge',
        'raw_database_fingerprint_sha256' => hash('sha256', $databaseBytes),
        'records_relative_path' => 'source/pricing/records.jsonl',
        'records_sha256' => hash('sha256', $pricingBytes),
        'record_count' => 1,
        'interpreter' => 'synthetic-characterizer',
        'interpreter_version' => '1.0.0',
        'interpretation_state' => 'candidate-only',
        'source_datasets' => [[
            'dataset' => 'applications',
            'row_count' => 1,
            'sha256' => hash('sha256', $databaseBytes),
        ]],
    ];
    $finding = [
        'schema_version' => RescueCorpusSemantics::FindingVersion,
        'finding_id' => 'synthetic-finding-001',
        'evidence_class' => 'database',
        'code' => 'unresolved-reference',
        'severity' => 'warning',
        'status' => 'open',
        'source_key_sha256' => hash('sha256', 'synthetic-application-source'),
        'dataset' => 'applications',
        'message' => 'Synthetic unresolved reference retained as evidence.',
        'details' => ['synthetic' => true],
        'disposition' => 'preserve',
        'authority' => null,
    ];
    $files = [
        'source/database/schema.json' => CanonicalJson::encode($databaseSchema)."\n",
        'source/database/table-manifest.json' => CanonicalJson::encode($databaseManifest)."\n",
        'source/database/tables/applications.jsonl' => $databaseBytes,
        'source/media/media-manifest.jsonl' => CanonicalJson::encode($mediaEntry)."\n",
        'source/media/objects/synthetic-media.bin' => $mediaBytes,
        'source/pricing/pricing-manifest.json' => CanonicalJson::encode($pricingManifest)."\n",
        'source/pricing/records.jsonl' => $pricingBytes,
        'provenance/acquisition.json' => CanonicalJson::encode([
            'schema_version' => RescueCorpusSemantics::AcquisitionVersion,
            'run_id' => 'synthetic-run-001',
            'source_system' => 'ipil-synthetic',
            'deployment_identity_sha256' => $deploymentIdentity,
            'mode' => 'synthetic',
            'started_at' => '2000-01-01T00:00:00Z',
            'completed_at' => '2000-01-01T00:00:01Z',
            'consistency_method' => 'synthetic immutable fixture',
            'read_only' => true,
            'write_back' => false,
            'destination_class' => 'local-private',
        ])."\n",
        'provenance/tools.json' => CanonicalJson::encode([
            'schema_version' => RescueCorpusSemantics::ToolsVersion,
            'tools' => [[
                'name' => 'synthetic-generator',
                'version' => '1.0.0',
                'sha256' => hash('sha256', 'synthetic-generator'),
            ]],
        ])."\n",
        'verification/exceptions.jsonl' => CanonicalJson::encode($finding)."\n",
    ];
    $identities = [[
        'schema_version' => SourceIdentity::SchemaVersion,
        'source_system' => 'ipil-synthetic',
        'deployment_identity_sha256' => $deploymentIdentity,
        'corpus_id' => $corpusId,
        'dataset' => 'applications',
        'source_key_sha256' => hash('sha256', 'synthetic-application-source'),
        'canonical_payload_sha256' => hash('sha256', trim($databaseBytes)),
        'raw_evidence_locator' => 'source/database/tables/applications.jsonl',
        'entity_kind' => 'application',
        'mapping_state' => 'observed',
        'mapper_version' => null,
        'disposition' => 'preserve',
        'evidence' => [],
        'authority' => null,
        'target_reference' => null,
        'flags' => ['synthetic'],
    ], [
        'schema_version' => SourceIdentity::SchemaVersion,
        'source_system' => 'ipil-synthetic',
        'deployment_identity_sha256' => $deploymentIdentity,
        'corpus_id' => $corpusId,
        'dataset' => 'media-documents',
        'source_key_sha256' => hash('sha256', 'synthetic-media-source'),
        'canonical_payload_sha256' => hash('sha256', CanonicalJson::encode($mediaEntry)),
        'raw_evidence_locator' => 'source/media/media-manifest.jsonl',
        'entity_kind' => 'document',
        'mapping_state' => 'observed',
        'mapper_version' => null,
        'disposition' => 'preserve',
        'evidence' => [],
        'authority' => null,
        'target_reference' => null,
        'flags' => ['synthetic'],
    ], [
        'schema_version' => SourceIdentity::SchemaVersion,
        'source_system' => 'ipil-synthetic',
        'deployment_identity_sha256' => $deploymentIdentity,
        'corpus_id' => $corpusId,
        'dataset' => 'pricing-knowledge',
        'source_key_sha256' => hash('sha256', 'synthetic-price-source'),
        'canonical_payload_sha256' => hash('sha256', CanonicalJson::encode($pricingRecord)),
        'raw_evidence_locator' => 'source/pricing/records.jsonl',
        'entity_kind' => 'pricing-candidate',
        'mapping_state' => 'proposed',
        'mapper_version' => 'synthetic-characterizer@1.0.0',
        'disposition' => 'defer',
        'evidence' => [],
        'authority' => null,
        'target_reference' => null,
        'flags' => ['synthetic'],
    ]];
    $files['provenance/source-identities.jsonl'] = collect($identities)
        ->map(fn (array $identity): string => CanonicalJson::encode(SourceIdentity::fromArray($identity)->toArray()))
        ->implode("\n")."\n";

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
            'media_metadata' => 1,
            'media_bytes' => 1,
            'pricing_records' => 1,
            'exceptions' => 1,
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

function replaceBoundSyntheticFile(string $root, string $relativePath, string $contents): void
{
    File::put($root.'/'.$relativePath, $contents);
    $manifest = json_decode(File::get($root.'/corpus.json'), true, flags: JSON_THROW_ON_ERROR);
    $manifest['bindings'][$relativePath] = hash('sha256', $contents);
    ksort($manifest['bindings']);
    File::put($root.'/corpus.json', CanonicalJson::encode($manifest)."\n");
    File::put(
        $root.'/verification/checksums.sha256',
        collect($manifest['bindings'])
            ->map(fn (string $checksum, string $path): string => "{$checksum}  {$path}")
            ->implode("\n")."\n",
    );
}
