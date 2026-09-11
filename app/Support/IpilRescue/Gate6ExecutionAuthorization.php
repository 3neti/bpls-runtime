<?php

namespace App\Support\IpilRescue;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class Gate6ExecutionAuthorization
{
    public const PlanId = 'ipil-seed-plan-874d26eb21ed0041';

    public const PlanFingerprint = '874d26eb21ed004147bdec32fdbd4cbf097101defdf37a7697776ea42080d9fb';

    public const ManifestFingerprint = 'c906494f1c37a4375d78343037e4c5a443986aba9df65217ab4efa4fdbfb64b6';

    public const TargetEnvironment = 'local-postgresql';

    /** @param array<string, mixed> $manifest */
    public function __construct(
        public array $manifest,
        public string $manifestPath,
        public string $targetDatabaseIdentity,
        public string $fingerprint,
    ) {}

    public static function issue(string $manifestPath, string $environment): self
    {
        if ($environment !== self::TargetEnvironment || ! app()->environment('local')) {
            throw new RuntimeException('Gate 6 execution is restricted to the explicit local-postgresql environment.');
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Gate 6 execution requires PostgreSQL; SQLite and other drivers are forbidden.');
        }

        $configuration = DB::connection()->getConfig();
        $host = (string) ($configuration['host'] ?? '');
        $database = (string) ($configuration['database'] ?? '');
        $port = (string) ($configuration['port'] ?? '5432');

        if (! in_array($host, ['127.0.0.1', 'localhost', '/tmp'], true)
            || preg_match('/^bpls_ipil_parity_gate6_[a-z0-9_]+$/', $database) !== 1) {
            throw new RuntimeException('Gate 6 refused a database that is not the dedicated local disposable parity target.');
        }

        $realManifest = realpath($manifestPath);
        $plansRoot = realpath(storage_path('app/private/ipil-rescue/plans'));

        if ($realManifest === false || $plansRoot === false || ! str_starts_with($realManifest, $plansRoot.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The Gate 6 manifest must be an existing private seed-plan artifact.');
        }

        $manifest = json_decode((string) file_get_contents($realManifest), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($manifest)
            || ! self::hasAcceptedManifestFingerprint($manifest)
            || ($manifest['plan_id'] ?? null) !== self::PlanId
            || ($manifest['plan_fingerprint_sha256'] ?? null) !== self::PlanFingerprint
            || data_get($manifest, 'corpus.id') !== IpilSeedMappingProfile::CanonicalCorpusId
            || data_get($manifest, 'corpus.fingerprint_sha256') !== IpilSeedMappingProfile::CanonicalCorpusFingerprint
            || data_get($manifest, 'mapping_profile.name') !== IpilSeedMappingProfile::Name
            || data_get($manifest, 'mapping_profile.identity_sha256') !== IpilSeedMappingProfile::Identity
            || ($manifest['target_environment'] ?? null) !== self::TargetEnvironment
            || ($manifest['execution_authorized'] ?? null) !== false) {
            throw new RuntimeException('The private Gate 5 Execution Manifest does not match the frozen Gate 6 authorization chain.');
        }

        $phaseCodes = array_column($manifest['phases'] ?? [], 'code');
        if ($phaseCodes !== range('A', 'L')) {
            throw new RuntimeException('The accepted Execution Manifest phase order changed.');
        }

        $serverVersion = (string) DB::scalar('select version()');
        $identity = hash('sha256', CanonicalJson::encode([
            'driver' => 'pgsql',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'server_version' => $serverVersion,
        ]));
        $payload = [
            'schema_version' => 'bpls.ipil-gate6-execution-authorization.v1',
            'authority' => 'explicit-owner-gate6-directive',
            'corpus_id' => IpilSeedMappingProfile::CanonicalCorpusId,
            'corpus_fingerprint_sha256' => IpilSeedMappingProfile::CanonicalCorpusFingerprint,
            'mapping_profile' => IpilSeedMappingProfile::Name,
            'mapping_profile_identity_sha256' => IpilSeedMappingProfile::Identity,
            'seed_plan_id' => self::PlanId,
            'seed_plan_fingerprint_sha256' => self::PlanFingerprint,
            'execution_manifest_fingerprint_sha256' => self::ManifestFingerprint,
            'target_environment' => self::TargetEnvironment,
            'target_database_identity_sha256' => $identity,
            'live_source_access' => false,
            'cloud_writes' => false,
        ];
        $fingerprint = hash('sha256', CanonicalJson::encode($payload));
        $directory = storage_path('app/private/ipil-rescue/gate6');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('The private Gate 6 authorization directory could not be created.');
        }
        $path = $directory.'/authorization-'.$fingerprint.'.json';
        $bytes = CanonicalJson::encode($payload + ['semantic_fingerprint_sha256' => $fingerprint])."\n";
        if (is_file($path) && ! hash_equals(hash('sha256', (string) file_get_contents($path)), hash('sha256', $bytes))) {
            throw new RuntimeException('The existing Gate 6 authorization artifact conflicts with this execution.');
        }
        if (! is_file($path)) {
            file_put_contents($path, $bytes, LOCK_EX);
            chmod($path, 0600);
        }

        return new self($manifest, $realManifest, $identity, $fingerprint);
    }

    /** @param array<string, mixed> $manifest */
    public static function hasAcceptedManifestFingerprint(array $manifest): bool
    {
        $claimed = $manifest['semantic_fingerprint_sha256'] ?? null;
        unset($manifest['semantic_fingerprint_sha256']);

        return is_string($claimed)
            && hash_equals(self::ManifestFingerprint, $claimed)
            && hash_equals($claimed, hash('sha256', CanonicalJson::encode($manifest)));
    }
}
