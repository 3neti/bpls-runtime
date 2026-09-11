<?php

namespace App\Actions;

use App\Support\IpilRescue\CanonicalJson;
use App\Support\IpilRescue\IpilSeedMappingProfile;
use App\Support\IpilRescue\IpilSeedPlan;
use JsonException;
use RuntimeException;
use SplFileObject;

final class PlanIpilRescueSeed
{
    public function __construct(private readonly VerifyIpilRescueCorpus $verify) {}

    public function handle(string $corpusPath, ?IpilSeedMappingProfile $profile = null): IpilSeedPlan
    {
        $profile ??= IpilSeedMappingProfile::canonical();
        $verification = $this->verify->handle($corpusPath);
        $root = realpath($corpusPath);

        if ($root === false || $verification->corpusId !== $profile->corpusId || $verification->corpusFingerprint !== $profile->corpusFingerprint) {
            throw new RuntimeException('The corpus does not match the mapping profile binding. Planning stopped before interpretation.');
        }

        if ($verification->semanticCounts['database_rows'] !== $profile->databaseRows
            || $verification->semanticCounts['media_metadata'] !== $profile->mediaMetadataRecords
            || $verification->semanticCounts['pricing_records'] !== $profile->pricingRecords) {
            throw new RuntimeException('The verified corpus counts drift from the accepted mapping profile.');
        }

        $tableManifest = $this->jsonObject($root.'/source/database/table-manifest.json');
        $actualTables = [];

        foreach ($tableManifest['tables'] ?? [] as $table) {
            if (! is_array($table) || ! is_string($table['dataset'] ?? null) || ! is_int($table['row_count'] ?? null)) {
                throw new RuntimeException('The database table manifest cannot be planned safely.');
            }
            $actualTables[$table['dataset']] = $table['row_count'];
        }

        ksort($actualTables);
        $expectedTables = [];
        foreach ($profile->tables as $table => $rule) {
            $expectedTables[$table] = $rule['count'];
        }
        ksort($expectedTables);

        if ($actualTables !== $expectedTables) {
            throw new RuntimeException('The source table inventory or row counts drift from the accepted mapping profile.');
        }

        $dispositions = [];
        $confidences = [];
        $datasets = [];
        $seen = [];
        $identityCount = 0;
        $registry = new SplFileObject($root.'/provenance/source-identities.jsonl', 'rb');

        while (! $registry->eof()) {
            $line = trim((string) $registry->fgets());
            if ($line === '') {
                continue;
            }
            $identity = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $key = $identity['source_key_sha256'] ?? null;
            $dataset = $identity['dataset'] ?? null;

            if (! is_string($key) || ! is_string($dataset)) {
                throw new RuntimeException('Every source identity must be unique and dispositionable.');
            }
            $identityKey = $dataset.'|'.$key;
            if (isset($seen[$identityKey])) {
                throw new RuntimeException('Every source identity must be unique and dispositionable.');
            }
            $seen[$identityKey] = true;
            $identityCount++;

            if (isset($profile->tables[$dataset])) {
                $disposition = $profile->tables[$dataset]['disposition'];
                $confidence = $profile->tables[$dataset]['confidence'];
            } elseif ($dataset === 'businesses-media') {
                $disposition = 'MAP_AS_HISTORICAL_EVIDENCE';
                $confidence = 'PROBABLE';
            } elseif (in_array($dataset, ['media-documents', 'storage-objects'], true) || str_ends_with($dataset, '-media')) {
                $disposition = 'PRESERVE_UNINTERPRETED';
                $confidence = $dataset === 'storage-objects' ? 'UNKNOWN' : 'ESTABLISHED';
            } elseif (in_array($dataset, ['pricing-corpus', 'pricing-knowledge'], true)) {
                $disposition = 'REFERENCE_DATA';
                $confidence = 'PROBABLE';
            } else {
                throw new RuntimeException("Source dataset {$dataset} has no accepted disposition.");
            }

            $dispositions[$disposition] = ($dispositions[$disposition] ?? 0) + 1;
            $confidences[$confidence] = ($confidences[$confidence] ?? 0) + 1;
            $datasets[$dataset] = ($datasets[$dataset] ?? 0) + 1;
        }

        if ($identityCount !== $verification->sourceIdentityCount) {
            throw new RuntimeException('Source identity planning did not close to the verified registry count.');
        }

        foreach ($expectedTables as $dataset => $count) {
            if (($datasets[$dataset] ?? 0) !== $count) {
                throw new RuntimeException("Source identity coverage for {$dataset} does not match its verified table row count.");
            }
        }

        $plannedMedia = array_sum(array_filter($datasets, fn (int $count, string $dataset): bool => $dataset === 'media-documents' || $dataset === 'storage-objects' || str_ends_with($dataset, '-media'), ARRAY_FILTER_USE_BOTH));
        $plannedPricing = ($datasets['pricing-corpus'] ?? 0) + ($datasets['pricing-knowledge'] ?? 0);
        if ($plannedMedia !== $profile->mediaSourceIdentities || $plannedPricing !== $profile->pricingRecords) {
            throw new RuntimeException('Media or pricing source identity coverage drifted from the accepted profile.');
        }

        ksort($datasets);
        ksort($dispositions);
        ksort($confidences);
        $phases = [
            ['code' => 'A', 'name' => 'bind/inventory', 'expected' => '324,833 rows plus media/pricing identities', 'writes' => false],
            ['code' => 'B', 'name' => 'reference proposals', 'expected' => '44 barangays, 562 LOB labels, fee/location catalogs', 'writes' => false],
            ['code' => 'C', 'name' => 'owners', 'expected' => 3194, 'writes' => false],
            ['code' => 'D', 'name' => 'businesses', 'expected' => 3212, 'writes' => false],
            ['code' => 'E', 'name' => 'Applications', 'expected' => 3137, 'writes' => false],
            ['code' => 'F', 'name' => 'declarations/classifications', 'expected' => '4,236 LOB plus 4,388 measurement rows', 'writes' => false],
            ['code' => 'G', 'name' => 'permit finance', 'expected' => '7,648 schedules plus 5,874 payment events', 'writes' => false],
            ['code' => 'H', 'name' => 'billing/report finance', 'expected' => '71,032 source table rows', 'writes' => false],
            ['code' => 'I', 'name' => 'clearances/permits', 'expected' => '14,615 clearance plus 2,766 permit claims', 'writes' => false],
            ['code' => 'J', 'name' => 'media reconciliation', 'expected' => '35 objects and 16 typed relationships', 'writes' => false],
            ['code' => 'K', 'name' => 'search/report projection', 'expected' => 'contract only', 'writes' => false],
            ['code' => 'L', 'name' => 'audit/replay', 'expected' => 'all evidence classes', 'writes' => false],
        ];
        $semanticFiles = [
            __FILE__,
            app_path('Support/IpilRescue/HistoricalAmount.php'),
            app_path('Support/IpilRescue/HistoricalApplicationPlan.php'),
            app_path('Support/IpilRescue/HistoricalEvidencePlan.php'),
            app_path('Support/IpilRescue/HistoricalEvidenceRegistry.php'),
            app_path('Support/IpilRescue/IpilSeedMappingProfile.php'),
            app_path('Support/IpilRescue/IpilSeedPlan.php'),
        ];
        $semanticFileHashes = array_map(fn (string $path): string => (string) hash_file('sha256', $path), $semanticFiles);
        $phases = array_map(function (array $phase) use ($profile): array {
            $phase['prerequisite'] = $phase['code'] === 'A' ? 'verified corpus/profile binding' : 'all preceding phases planned';
            $phase['blocked_count'] = 0;
            $phase['semantic_fingerprint_sha256'] = hash('sha256', CanonicalJson::encode([
                'mapping_profile_identity' => $profile->identity,
                'phase' => $phase,
            ]));

            return $phase;
        }, $phases);
        $planner = [
            'name' => 'offline-ipil-seed-planner',
            'version' => '1.0.0',
            'semantics_sha256' => hash('sha256', implode(':', [$profile->identity, ...$semanticFileHashes])),
            'offline' => true,
            'reads' => ['verified-local-corpus', 'accepted-mapping-profile', 'local-code-and-config'],
        ];
        $payload = [
            'schema_version' => 'bpls.ipil-seed-plan.v1',
            'corpus' => ['id' => $verification->corpusId, 'fingerprint_sha256' => $verification->corpusFingerprint],
            'mapping_profile' => ['name' => $profile->name, 'contract' => $profile->contract, 'identity_sha256' => $profile->identity],
            'planner' => $planner,
            'bpls_baseline_commit' => 'bb1ddf953b0052f4735bdafb967d98a8776870b5',
            'coverage' => [
                'database_rows' => $profile->databaseRows,
                'media_records' => $profile->mediaSourceIdentities,
                'pricing_records' => $profile->pricingRecords,
                'source_identities' => $identityCount,
                'unplanned_source_identities' => 0,
                'dataset_counts' => $datasets,
                'disposition_counts' => $dispositions,
                'confidence_counts' => $confidences,
                'blocking_ambiguities' => 0,
                'preserved_unresolved' => $profile->acceptedEvidence['unresolved'] ?? [],
            ],
            'accepted_evidence' => $profile->acceptedEvidence,
            'phases' => $phases,
            'protections' => [
                'source_read_only' => true, 'corpus_immutable' => true, 'database_writes' => false,
                'domain_models_created' => false, 'network_access' => false, 'current_pricing_invoked' => false,
                'historical_is_operational' => false, 'money_rounding_permitted' => false,
            ],
            'stop_conditions' => [
                'corpus_or_profile_drift', 'unknown_dataset_or_unplanned_identity', 'duplicate_source_identity',
                'non_cent_value_requires_rounding', 'historical_operational_leakage', 'network_or_cloud_access',
                'canonical_write_without_gate_6_authorization',
            ],
            'materialization' => ['authorized' => false, 'recommended_database' => 'postgresql', 'synthetic_sqlite_only' => true],
        ];

        return new IpilSeedPlan($payload, hash('sha256', CanonicalJson::encode($payload)), now()->utc()->toIso8601String());
    }

    /** @return array<string, mixed> */
    private function jsonObject(string $path): array
    {
        try {
            $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('A bound planning manifest is not valid JSON.', previous: $exception);
        }

        if (! is_array($payload) || array_is_list($payload)) {
            throw new RuntimeException('A bound planning manifest must be a JSON object.');
        }

        return $payload;
    }
}
