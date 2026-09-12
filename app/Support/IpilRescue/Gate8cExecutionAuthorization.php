<?php

namespace App\Support\IpilRescue;

use App\Http\Middleware\RestrictIpilHistoricalUat;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class Gate8cExecutionAuthorization
{
    public const TargetEnvironment = 'private-historical-uat';

    public const Database = 'bpls_ipil_historical_gate8c';

    public const Bucket = 'fls-a2b95366-1364-456a-8471-86c4feac19fc';

    /** @param array<string, mixed> $manifest */
    private function __construct(
        public array $manifest,
        public string $manifestPath,
        public string $targetDatabaseIdentity,
        public string $fingerprint,
    ) {}

    public static function issue(string $manifestPath, string $authorizationPath): self
    {
        $path = realpath($authorizationPath);
        $private = realpath(storage_path('app/private/ipil-rescue/gate8c'));
        $manifestFile = realpath($manifestPath);
        $plans = realpath(storage_path('app/private/ipil-rescue/plans'));
        if ($path === false || $private === false || ! str_starts_with($path, $private.DIRECTORY_SEPARATOR)
            || $manifestFile === false || $plans === false || ! str_starts_with($manifestFile, $plans.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Gate 8C requires private local authorization and accepted manifest artifacts.');
        }

        $manifest = json_decode((string) file_get_contents($manifestFile), true, flags: JSON_THROW_ON_ERROR);
        $record = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        self::validateBindings($record, $manifest);

        $configuration = DB::connection()->getConfig();
        if (! app()->runningInConsole() || ! app()->environment('historical-uat')
            || config('ipil_historical_uat.enabled') !== true
            || config('ipil_historical_uat.environment_id') !== RestrictIpilHistoricalUat::EnvironmentId
            || config('stakeholder_preview.mode') !== false
            || DB::connection()->getDriverName() !== 'pgsql'
            || ($configuration['database'] ?? null) !== self::Database
            || ($configuration['sslmode'] ?? null) !== 'verify-full'
            || ! hash_equals($record['database_host_sha256'], hash('sha256', (string) ($configuration['host'] ?? '')))
            || config('filesystems.disks.ipil_gate8c.driver') !== 's3'
            || config('filesystems.disks.ipil_gate8c.bucket') !== self::Bucket
            || config('filesystems.disks.ipil_gate8c.visibility') !== 'private'
            || ! hash_equals($record['media_endpoint_sha256'], hash('sha256', (string) config('filesystems.disks.ipil_gate8c.endpoint')))
            || ! hash_equals($record['reviewer_identity_sha256'], hash('sha256', strtolower((string) config('ipil_historical_uat.reviewer_email'))))) {
            throw new RuntimeException('Gate 8C refused mismatched target, transport, or reviewer configuration.');
        }

        $commit = trim((string) shell_exec('git -C '.escapeshellarg(base_path()).' rev-parse HEAD'));
        if (! hash_equals($record['deployed_commit'], $commit)
            || DB::scalar('select current_database()') !== self::Database) {
            throw new RuntimeException('Gate 8C refused a different execution revision or PostgreSQL database.');
        }

        return new self($manifest, $manifestFile, $record['target_database_identity_sha256'], $record['semantic_fingerprint_sha256']);
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $manifest
     */
    public static function validateBindings(array $record, array $manifest): void
    {
        $fingerprint = $record['semantic_fingerprint_sha256'] ?? '';
        unset($record['semantic_fingerprint_sha256']);
        $expected = [
            'schema_version' => 'bpls.ipil-gate8c-execution-authorization.v1',
            'authority' => 'explicit-owner-gate8c-directive',
            'application_id' => 'app-a2b8d56c-d074-4005-82ae-2679e2f60f09',
            'environment_id' => RestrictIpilHistoricalUat::EnvironmentId,
            'database_cluster_id' => 'twilight-bonus-30025572',
            'database_id' => '1099424',
            'database_name' => self::Database,
            'media_bucket_id' => 'fls-a2b95366-1364-456a-8471-86c4feac19fc',
            'target_environment' => self::TargetEnvironment,
            'corpus_id' => IpilSeedMappingProfile::CanonicalCorpusId,
            'corpus_fingerprint_sha256' => IpilSeedMappingProfile::CanonicalCorpusFingerprint,
            'mapping_profile_identity_sha256' => IpilSeedMappingProfile::Identity,
            'seed_plan_fingerprint_sha256' => Gate6ExecutionAuthorization::PlanFingerprint,
            'execution_manifest_fingerprint_sha256' => Gate6ExecutionAuthorization::ManifestFingerprint,
            'live_source_access' => false,
            'full_corpus_upload' => false,
            'associated_media_limit' => 16,
            'restricted_access_verified' => true,
        ];
        foreach ($expected as $key => $value) {
            if (($record[$key] ?? null) !== $value) {
                throw new RuntimeException('Gate 8C authorization binding mismatch: '.$key);
            }
        }
        foreach (['database_host_sha256', 'media_endpoint_sha256', 'reviewer_identity_sha256', 'target_database_identity_sha256'] as $key) {
            if (preg_match('/^[a-f0-9]{64}$/', (string) ($record[$key] ?? '')) !== 1) {
                throw new RuntimeException('Gate 8C requires a complete target fingerprint.');
            }
        }
        if (! is_string($fingerprint) || ! hash_equals($fingerprint, hash('sha256', CanonicalJson::encode($record)))
            || preg_match('/^[a-f0-9]{40}$/', (string) ($record['deployed_commit'] ?? '')) !== 1
            || ! is_string($record['deployment_id'] ?? null) || $record['deployment_id'] === ''
            || ! Gate6ExecutionAuthorization::hasAcceptedManifestFingerprint($manifest)) {
            throw new RuntimeException('Gate 8C authorization or immutable Gate 5 manifest fingerprint failed.');
        }
    }
}
