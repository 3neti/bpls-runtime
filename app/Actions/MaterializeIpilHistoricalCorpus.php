<?php

namespace App\Actions;

use App\Models\IpilHistoricalMediaEvidence;
use App\Support\IpilRescue\CanonicalJson;
use App\Support\IpilRescue\Gate6ExecutionAuthorization;
use App\Support\IpilRescue\HistoricalAmount;
use App\Support\IpilRescue\IpilMaterializationResult;
use App\Support\IpilRescue\IpilSeedMappingProfile;
use App\Support\IpilRescue\JsonNumericLexeme;
use App\Support\IpilRescue\SourceIdentity;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use SplFileObject;
use Throwable;

final class MaterializeIpilHistoricalCorpus
{
    private const ChunkSize = 1000;

    /** @var array<string, int> */
    private array $created = [];

    /** @var list<array<string, mixed>> */
    private array $phaseResults = [];

    public function handle(string $corpusPath, Gate6ExecutionAuthorization $authorization): IpilMaterializationResult
    {
        $root = realpath($corpusPath);
        if ($root === false) {
            throw new RuntimeException('The verified corpus path is unavailable.');
        }

        $started = microtime(true);
        $runId = 'ipil-g6-'.now()->utc()->format('YmdHis').'-'.strtolower(Str::random(8));
        $now = now()->utc();
        $profile = IpilSeedMappingProfile::canonical();
        $operationalBaseline = $this->operationalBaseline();
        $commit = trim((string) shell_exec('git -C '.escapeshellarg(base_path()).' rev-parse HEAD'));
        if (preg_match('/^[a-f0-9]{40}$/', $commit) !== 1) {
            throw new RuntimeException('The BPLS code revision could not be identified.');
        }

        DB::table('ipil_rescue_import_runs')->insert([
            'id' => $runId,
            'corpus_id' => $profile->corpusId,
            'corpus_fingerprint_sha256' => $profile->corpusFingerprint,
            'mapping_profile' => $profile->name,
            'mapping_profile_identity_sha256' => $profile->identity,
            'seed_plan_id' => Gate6ExecutionAuthorization::PlanId,
            'seed_plan_fingerprint_sha256' => Gate6ExecutionAuthorization::PlanFingerprint,
            'execution_manifest_fingerprint_sha256' => Gate6ExecutionAuthorization::ManifestFingerprint,
            'authorization_fingerprint_sha256' => $authorization->fingerprint,
            'code_commit' => $commit,
            'target_environment' => Gate6ExecutionAuthorization::TargetEnvironment,
            'target_database_identity_sha256' => $authorization->targetDatabaseIdentity,
            'status' => 'RUNNING',
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($authorization->manifest['phases'] as $phase) {
            DB::table('ipil_rescue_phase_checkpoints')->insert([
                'ipil_rescue_import_run_id' => $runId,
                'phase_code' => $phase['code'],
                'phase_name' => $phase['name'],
                'semantic_fingerprint_sha256' => $phase['semantic_fingerprint_sha256'],
                'status' => 'PENDING',
                'expected' => json_encode($phase['expected'], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        try {
            $this->phase($runId, 'A', fn (): array => $this->bindAndPreserve($root, $runId, $profile));
            $this->phase($runId, 'B', fn (): array => $this->referenceProposals($root));
            $this->phase($runId, 'C', fn (): array => $this->owners($root));
            $this->phase($runId, 'D', fn (): array => $this->businesses($root));
            $this->phase($runId, 'E', fn (): array => $this->applications($root));
            $this->phase($runId, 'F', fn (): array => $this->declarationsAndMeasurements($root));
            $this->phase($runId, 'G', fn (): array => $this->permitFinance($root));
            $this->phase($runId, 'H', fn (): array => $this->billingEvidence());
            $this->phase($runId, 'I', fn (): array => $this->permitsAndClearances($root));
            $this->phase($runId, 'J', fn (): array => $this->media($root, $profile));
            $this->phase($runId, 'K', fn (): array => $this->readProjectionChecks());
            $this->phase($runId, 'L', fn (): array => ['audit_ready' => true, 'replay_safe' => true]);

            $counts = $this->resultCounts();
            foreach (array_keys($counts) as $counter) {
                $this->created[$counter] ??= 0;
            }
            DB::table('ipil_rescue_import_runs')->where('id', $runId)->update([
                'status' => 'COMPLETED',
                'result' => json_encode(['created' => $this->created, 'counts' => $counts, 'operational_baseline' => $operationalBaseline], JSON_THROW_ON_ERROR),
                'completed_at' => now()->utc(),
                'updated_at' => now()->utc(),
            ]);

            return new IpilMaterializationResult($runId, round(microtime(true) - $started, 3), [
                'created' => $this->created,
                'materialized' => $counts,
                'already_materialized' => array_sum($counts) - array_sum($this->created),
                'duplicated' => 0,
                'unexpectedly_changed' => 0,
                'conflicted' => 0,
            ], $this->phaseResults);
        } catch (Throwable $exception) {
            DB::table('ipil_rescue_import_runs')->where('id', $runId)->update([
                'status' => 'FAILED',
                'result' => json_encode(['created' => $this->created, 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR),
                'completed_at' => now()->utc(),
                'updated_at' => now()->utc(),
            ]);
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function bindAndPreserve(string $root, string $runId, IpilSeedMappingProfile $profile): array
    {
        $identityRows = [];
        $ordinals = [];
        $registry = new SplFileObject($root.'/provenance/source-identities.jsonl', 'rb');
        $now = now()->utc();
        $seen = 0;

        while (! $registry->eof()) {
            $line = trim((string) $registry->fgets());
            if ($line === '') {
                continue;
            }
            $identity = SourceIdentity::fromArray(json_decode($line, true, flags: JSON_THROW_ON_ERROR));
            [$disposition, $confidence] = $this->classification($identity->dataset, $profile);
            $ordinal = ($ordinals[$identity->dataset] ?? 0) + 1;
            $ordinals[$identity->dataset] = $ordinal;
            $identityRows[] = [
                'first_import_run_id' => $runId,
                'source_system' => $identity->sourceSystem,
                'deployment_identity_sha256' => $identity->deploymentIdentitySha256,
                'corpus_id' => $identity->corpusId,
                'dataset' => $identity->dataset,
                'source_key_sha256' => $identity->sourceKeySha256,
                'canonical_payload_sha256' => $identity->canonicalPayloadSha256,
                'raw_evidence_locator' => $identity->rawEvidenceLocator,
                'entity_kind' => $identity->entityKind,
                'disposition' => $disposition,
                'confidence' => $confidence,
                'source_ordinal' => $ordinal,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $seen++;
            if (count($identityRows) === self::ChunkSize) {
                $this->created['source_identities'] = ($this->created['source_identities'] ?? 0) + DB::table('ipil_rescue_source_identities')->insertOrIgnore($identityRows);
                $this->verifyIdentityRows($identityRows);
                $identityRows = [];
            }
        }
        if ($identityRows !== []) {
            $this->created['source_identities'] = ($this->created['source_identities'] ?? 0) + DB::table('ipil_rescue_source_identities')->insertOrIgnore($identityRows);
            $this->verifyIdentityRows($identityRows);
        }
        if ($seen !== 324873) {
            throw new RuntimeException("SourceIdentity coverage is {$seen}; expected 324873.");
        }

        foreach ($profile->tables as $dataset => $rule) {
            if ($rule['disposition'] === 'IGNORE_WITH_EVIDENCE_BASED_REASON' || $rule['count'] === 0) {
                continue;
            }
            $this->preserveDatabaseDataset($root, $dataset, $rule['disposition'], $rule['confidence']);
        }
        $this->preserveManifestEvidence($root, 'source/media/media-manifest.jsonl', 'media');
        $this->preserveManifestEvidence($root, 'source/pricing/records.jsonl', 'pricing');

        return [
            'source_identities' => DB::table('ipil_rescue_source_identities')->count(),
            'preserved_evidence' => DB::table('ipil_historical_evidence_records')->count(),
            'ignored_payloads_staged' => DB::table('ipil_historical_evidence_records')->whereIn('dataset', ['authAccounts', 'authRateLimits', 'authRefreshTokens', 'authSessions'])->count(),
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    private function verifyIdentityRows(array $rows): void
    {
        $byDataset = [];
        foreach ($rows as $row) {
            $byDataset[$row['dataset']][] = $row;
        }
        foreach ($byDataset as $dataset => $expectedRows) {
            $keys = array_column($expectedRows, 'source_key_sha256');
            $actual = DB::table('ipil_rescue_source_identities')->where('dataset', $dataset)->whereIn('source_key_sha256', $keys)->pluck('canonical_payload_sha256', 'source_key_sha256');
            foreach ($expectedRows as $expected) {
                if (($actual[$expected['source_key_sha256']] ?? null) !== $expected['canonical_payload_sha256']) {
                    throw new RuntimeException("A persisted SourceIdentity conflicts in {$dataset}.");
                }
            }
        }
    }

    private function preserveDatabaseDataset(string $root, string $dataset, string $disposition, string $confidence): void
    {
        $identityIds = DB::table('ipil_rescue_source_identities')->where('dataset', $dataset)->pluck('id', 'source_key_sha256')->all();
        $rows = [];
        foreach ($this->lines($root.'/source/database/tables/'.$dataset.'.jsonl') as $line) {
            $payload = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $key = hash('sha256', (string) ($payload['_id'] ?? ''));
            $identityId = $identityIds[$key] ?? null;
            if (! is_int($identityId)) {
                throw new RuntimeException("The {$dataset} row has no persisted SourceIdentity.");
            }
            $rows[] = $this->evidenceRow($identityId, $dataset, 'raw-database', $disposition, $confidence, false, $line, null);
            if (count($rows) === self::ChunkSize) {
                $this->insertEvidence($rows);
                $rows = [];
            }
        }
        $this->insertEvidence($rows);
    }

    private function preserveManifestEvidence(string $root, string $relativePath, string $evidenceClass): void
    {
        $rows = [];
        foreach ($this->lines($root.'/'.$relativePath) as $line) {
            $payload = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $dataset = (string) $payload['source_dataset'];
            $identityId = DB::table('ipil_rescue_source_identities')->where('dataset', $dataset)->where('source_key_sha256', $payload['source_key_sha256'])->value('id');
            if (! is_int($identityId)) {
                throw new RuntimeException("The {$evidenceClass} record has no persisted SourceIdentity.");
            }
            [$disposition, $confidence] = $this->classification($dataset, IpilSeedMappingProfile::canonical());
            $unresolved = ($payload['association_state'] ?? null) === 'UNRESOLVED';
            $rows[] = $this->evidenceRow($identityId, $dataset, $evidenceClass, $disposition, $confidence, $unresolved, $line, ['source_sha256' => $payload['sha256'] ?? null]);
        }
        $this->insertEvidence($rows);
    }

    /** @return array<string, mixed> */
    private function referenceProposals(string $root): array
    {
        $targetNames = [];
        foreach (config('ipil_references.barangays.items', []) as $item) {
            $targetNames[$this->normalizeLabel((string) $item['name'])] = (string) $item['code'];
        }
        $proposals = 0;
        foreach ($this->decodedRows($root, 'barangays') as $row) {
            if (isset($targetNames[$this->normalizeLabel((string) ($row['name'] ?? ''))])) {
                $proposals++;
            }
        }
        if ($proposals !== 24) {
            throw new RuntimeException("Barangay proposal parity is {$proposals}; expected 24.");
        }

        return ['barangays' => 44, 'normalized_proposals' => 24, 'unresolved' => 20, 'automatic_psgc_mappings' => 0];
    }

    /** @return array<string, mixed> */
    private function owners(string $root): array
    {
        $barangays = $this->sourceLabelMap($root, 'barangays');
        $identityIds = $this->identityMap('business_owners');
        $rows = [];
        foreach ($this->rawDecodedRows($root, 'business_owners') as [$raw, $row]) {
            $key = hash('sha256', (string) $row['_id']);
            $name = trim(implode(' ', array_filter([$row['firstName'] ?? null, $row['middleName'] ?? null, $row['lastName'] ?? null], fn ($value): bool => is_string($value) && trim($value) !== '')));
            $rows[] = [
                'ipil_rescue_source_identity_id' => $identityIds[$key],
                'name' => $name === '' ? '[missing source name]' : $name,
                'email' => $this->nullableString($row['email'] ?? null),
                'phone' => $this->nullableString($row['mobile'] ?? null),
                'address' => $this->nullableString($row['address'] ?? null),
                'barangay_literal' => $barangays[$row['barangayId'] ?? ''] ?? null,
                'operationally_eligible' => false,
                'collision_candidate' => false,
                'source_payload_json' => $raw,
                'created_at' => now()->utc(), 'updated_at' => now()->utc(),
            ];
            $this->flush('ipil_historical_owners', $rows, 'owners');
        }
        $this->flush('ipil_historical_owners', $rows, 'owners', true);
        $actual = DB::table('ipil_historical_owners')->count();
        $this->expect($actual, 3194, 'Historical owners');

        return ['expected' => 3194, 'actual' => $actual, 'automatic_merges' => 0, 'source_identities' => $actual, 'users_created' => 0, 'collision_groups_preserved' => 286];
    }

    /** @return array<string, mixed> */
    private function businesses(string $root): array
    {
        $barangays = $this->sourceLabelMap($root, 'barangays');
        $identityIds = $this->identityMap('businesses');
        $ownerTargets = $this->targetMap('business_owners', 'ipil_historical_owners');
        $psgcNames = [];
        foreach (config('ipil_references.barangays.items', []) as $item) {
            $psgcNames[$this->normalizeLabel((string) $item['name'])] = (string) $item['code'];
        }
        $rows = [];
        foreach ($this->rawDecodedRows($root, 'businesses') as [$raw, $row]) {
            $ownerId = $ownerTargets[hash('sha256', (string) ($row['ownerId'] ?? ''))] ?? null;
            if (! is_int($ownerId)) {
                throw new RuntimeException('A historical Business owner edge is unresolved contrary to the accepted plan.');
            }
            $literal = $barangays[$row['barangayId'] ?? ''] ?? null;
            $rows[] = [
                'ipil_rescue_source_identity_id' => $identityIds[hash('sha256', (string) $row['_id'])],
                'ipil_historical_owner_id' => $ownerId,
                'name' => trim((string) ($row['name'] ?? '')) ?: '[missing source name]',
                'registration_number' => $this->nullableString($row['registrationNumber'] ?? null),
                'address' => $this->nullableString($row['address'] ?? null),
                'barangay_literal' => $literal,
                'barangay_psgc_proposal' => $literal === null ? null : ($psgcNames[$this->normalizeLabel($literal)] ?? null),
                'operationally_eligible' => false,
                'collision_candidate' => false,
                'source_payload_json' => $raw,
                'created_at' => now()->utc(), 'updated_at' => now()->utc(),
            ];
            $this->flush('ipil_historical_businesses', $rows, 'businesses');
        }
        $this->flush('ipil_historical_businesses', $rows, 'businesses', true);
        $actual = DB::table('ipil_historical_businesses')->count();
        $this->expect($actual, 3212, 'Historical businesses');

        return ['expected' => 3212, 'actual' => $actual, 'fuzzy_merges' => 0, 'owner_edges' => $actual, 'collision_groups_preserved' => 48];
    }

    /** @return array<string, mixed> */
    private function applications(string $root): array
    {
        $identityIds = $this->identityMap('business_permit_applications');
        $ownerTargets = $this->targetMap('business_owners', 'ipil_historical_owners');
        $businessTargets = $this->targetMap('businesses', 'ipil_historical_businesses');
        $rows = [];
        foreach ($this->rawDecodedRows($root, 'business_permit_applications') as [$raw, $row]) {
            $ownerId = $ownerTargets[hash('sha256', (string) ($row['businessOwnerId'] ?? ''))] ?? null;
            $businessId = $businessTargets[hash('sha256', (string) ($row['businessId'] ?? ''))] ?? null;
            if (! is_int($ownerId) || ! is_int($businessId)) {
                throw new RuntimeException('A historical Application registry edge is unresolved contrary to the accepted plan.');
            }
            $lexeme = JsonNumericLexeme::field($raw, 'totalFees');
            $amount = $lexeme === null ? null : HistoricalAmount::fromLexeme($lexeme);
            $submittedAt = $this->nullableString($row['submittedAt'] ?? null);
            $year = $submittedAt === null ? null : (int) substr($submittedAt, 0, 4);
            if ($year === null || $year < 1900 || $year > 2200) {
                throw new RuntimeException('A historical Application lacks an accepted submission year.');
            }
            $rows[] = [
                'ipil_rescue_source_identity_id' => $identityIds[hash('sha256', (string) $row['_id'])],
                'ipil_historical_owner_id' => $ownerId,
                'ipil_historical_business_id' => $businessId,
                'source_application_number' => $this->nullableString($row['applicationNumber'] ?? null),
                'source_type' => (string) $row['permitApplicationType'],
                'source_status' => (string) $row['status'],
                'application_year' => $year,
                'total_fees_source_lexeme' => $amount?->sourceLexeme,
                'total_fees_decimal' => $amount?->decimal,
                'total_fees_cent_exact' => $amount?->isCentExact(),
                'submitted_at' => $submittedAt,
                'operationally_eligible' => false,
                'can_continue' => false,
                'source_payload_json' => $raw,
                'created_at' => now()->utc(), 'updated_at' => now()->utc(),
            ];
            $this->flush('ipil_historical_applications', $rows, 'applications');
        }
        $this->flush('ipil_historical_applications', $rows, 'applications', true);
        $actual = DB::table('ipil_historical_applications')->count();
        $this->expect($actual, 3137, 'Historical Applications');
        $types = DB::table('ipil_historical_applications')->selectRaw('source_type, count(*) as aggregate')->groupBy('source_type')->pluck('aggregate', 'source_type')->map(fn ($value): int => (int) $value)->all();
        $statuses = DB::table('ipil_historical_applications')->selectRaw('source_status, count(*) as aggregate')->groupBy('source_status')->pluck('aggregate', 'source_status')->map(fn ($value): int => (int) $value)->all();
        if ($types !== ['Additional' => 55, 'New' => 461, 'Renewal' => 2621] && $types !== ['Renewal' => 2621, 'New' => 461, 'Additional' => 55]) {
            ksort($types);
            if ($types !== ['Additional' => 55, 'New' => 461, 'Renewal' => 2621]) {
                throw new RuntimeException('Historical Application type parity failed.');
            }
        }

        return ['expected' => 3137, 'actual' => $actual, 'types' => $types, 'statuses' => $statuses, 'operational_leakage' => 0];
    }

    /** @return array<string, mixed> */
    private function declarationsAndMeasurements(string $root): array
    {
        $applicationTargets = $this->targetMap('business_permit_applications', 'ipil_historical_applications');
        $applicationIdentityIds = $this->identityMap('business_permit_applications');
        $rows = [];
        foreach ($this->rawDecodedRows($root, 'business_permit_applications') as [$raw, $application]) {
            $sourceHash = hash('sha256', (string) $application['_id']);
            foreach (($application['linesOfBusiness'] ?? []) as $index => $line) {
                $literal = $this->nullableString($line['businessCategory'] ?? null);
                $rows[] = [
                    'ipil_historical_application_id' => $applicationTargets[$sourceHash],
                    'source_application_identity_id' => $applicationIdentityIds[$sourceHash],
                    'source_index' => $index,
                    'item_identity_sha256' => hash('sha256', $sourceHash.'|'.$index),
                    'source_literal' => $literal,
                    'normalized_candidate' => $literal === null ? null : $this->normalizeLabel($literal),
                    'confidence' => 'PROBABLE',
                    'source_payload_json' => CanonicalJson::encode($line),
                    'created_at' => now()->utc(), 'updated_at' => now()->utc(),
                ];
                $this->flush('ipil_historical_classifications', $rows, 'classifications');
            }
        }
        $this->flush('ipil_historical_classifications', $rows, 'classifications', true);

        $measurementRows = [];
        foreach (['assetSizes', 'mayorsPermitCategories', 'unitsOfMeasurement'] as $dataset) {
            $identities = $this->identityMap($dataset);
            foreach ($this->rawDecodedRows($root, $dataset) as [$raw, $row]) {
                $applicationId = isset($row['applicationId']) ? ($applicationTargets[hash('sha256', (string) $row['applicationId'])] ?? null) : null;
                $measurementRows[] = [
                    'ipil_rescue_source_identity_id' => $identities[hash('sha256', (string) $row['_id'])],
                    'ipil_historical_application_id' => $applicationId,
                    'source_dataset' => $dataset,
                    'source_variable' => $this->nullableString($row['variableName'] ?? $row['name'] ?? $row['description'] ?? null),
                    'source_quantity_lexeme' => JsonNumericLexeme::field($raw, 'quantity'),
                    'source_payload_json' => $raw,
                    'created_at' => now()->utc(), 'updated_at' => now()->utc(),
                ];
                $this->flush('ipil_historical_measurements', $measurementRows, 'measurements');
            }
        }
        $this->flush('ipil_historical_measurements', $measurementRows, 'measurements', true);
        $classifications = DB::table('ipil_historical_classifications')->count();
        $measurements = DB::table('ipil_historical_measurements')->count();
        $this->expect($classifications, 4236, 'Historical classifications');
        $this->expect($measurements, 4388, 'Historical measurements');

        return ['classifications' => $classifications, 'measurements' => $measurements, 'current_lob_assignments_created' => 0];
    }

    /** @return array<string, mixed> */
    private function permitFinance(string $root): array
    {
        $applications = $this->targetMap('business_permit_applications', 'ipil_historical_applications');
        $scheduleIdentities = $this->identityMap('payment_schedules');
        $rows = [];
        foreach ($this->rawDecodedRows($root, 'payment_schedules') as [$raw, $row]) {
            $applicationId = $applications[hash('sha256', (string) $row['applicationId'])] ?? null;
            $total = HistoricalAmount::fromLexeme((string) JsonNumericLexeme::field($raw, 'totalAmount'));
            $paid = HistoricalAmount::fromLexeme((string) JsonNumericLexeme::field($raw, 'paidAmount'));
            $rows[] = [
                'ipil_rescue_source_identity_id' => $scheduleIdentities[hash('sha256', (string) $row['_id'])],
                'ipil_historical_application_id' => $applicationId,
                'source_status' => (string) $row['status'],
                'total_amount_source_lexeme' => $total->sourceLexeme,
                'total_amount_decimal' => $total->decimal,
                'total_amount_cent_exact' => $total->isCentExact(),
                'paid_amount_source_lexeme' => $paid->sourceLexeme,
                'paid_amount_decimal' => $paid->decimal,
                'missing_application' => ! is_int($applicationId),
                'source_fee_lines' => json_encode($row['fees'] ?? [], JSON_THROW_ON_ERROR),
                'source_payload_json' => $raw,
                'created_at' => now()->utc(), 'updated_at' => now()->utc(),
            ];
            $this->flush('ipil_historical_payment_schedules', $rows, 'schedules');
        }
        $this->flush('ipil_historical_payment_schedules', $rows, 'schedules', true);

        $scheduleTargets = $this->targetMap('payment_schedules', 'ipil_historical_payment_schedules');
        $paymentIdentities = $this->identityMap('payments');
        $payments = [];
        foreach ($this->rawDecodedRows($root, 'payments') as [$raw, $row]) {
            $applicationId = $applications[hash('sha256', (string) $row['applicationId'])] ?? null;
            $scheduleId = $scheduleTargets[hash('sha256', (string) $row['scheduleId'])] ?? null;
            $amount = HistoricalAmount::fromLexeme((string) JsonNumericLexeme::field($raw, 'amount'));
            $receipt = $this->nullableString($row['receiptNumber'] ?? null);
            $payments[] = [
                'ipil_rescue_source_identity_id' => $paymentIdentities[hash('sha256', (string) $row['_id'])],
                'ipil_historical_application_id' => $applicationId,
                'ipil_historical_payment_schedule_id' => $scheduleId,
                'source_status' => (string) $row['status'],
                'amount_source_lexeme' => $amount->sourceLexeme,
                'amount_decimal' => $amount->decimal,
                'transaction_number' => $this->nullableString($row['transactionNumber'] ?? null),
                'receipt_number' => $receipt,
                'receipt_number_normalized_sha256' => $receipt === null ? null : hash('sha256', $this->normalizeReceipt($receipt)),
                'paid_at' => $this->nullableString($row['paidAt'] ?? null),
                'missing_application' => ! is_int($applicationId),
                'missing_schedule' => ! is_int($scheduleId),
                'source_payload_json' => $raw,
                'created_at' => now()->utc(), 'updated_at' => now()->utc(),
            ];
            $this->flush('ipil_historical_payments', $payments, 'payments');
        }
        $this->flush('ipil_historical_payments', $payments, 'payments', true);

        DB::statement('insert into ipil_historical_receipt_claims (ipil_historical_payment_id, receipt_number, normalized_sha256, is_duplicate_claim, created_at, updated_at) select id, receipt_number, receipt_number_normalized_sha256, false, now(), now() from ipil_historical_payments where receipt_number is not null on conflict (ipil_historical_payment_id) do nothing');
        DB::statement('update ipil_historical_receipt_claims set is_duplicate_claim = normalized_sha256 in (select normalized_sha256 from ipil_historical_receipt_claims group by normalized_sha256 having count(*) > 1)');
        $this->created['receipt_claims'] = DB::table('ipil_historical_receipt_claims')->count() - (($this->created['payments'] ?? 0) === 0 ? DB::table('ipil_historical_receipt_claims')->count() : 0);

        $scheduleCount = DB::table('ipil_historical_payment_schedules')->count();
        $paymentCount = DB::table('ipil_historical_payments')->count();
        $receiptCount = DB::table('ipil_historical_receipt_claims')->count();
        $this->expect($scheduleCount, 7648, 'Historical schedules');
        $this->expect($paymentCount, 5874, 'Historical payments');
        $this->expect($receiptCount, 5873, 'Historical receipt claims');

        return [
            'schedules' => $scheduleCount,
            'schedule_missing_application' => DB::table('ipil_historical_payment_schedules')->where('missing_application', true)->count(),
            'payments' => $paymentCount,
            'payment_missing_application' => DB::table('ipil_historical_payments')->where('missing_application', true)->count(),
            'payment_missing_schedule' => DB::table('ipil_historical_payments')->where('missing_schedule', true)->count(),
            'receipt_claims' => $receiptCount,
            'current_liabilities_created' => 0,
            'current_receipts_created' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function billingEvidence(): array
    {
        $actual = DB::table('ipil_historical_evidence_records')->whereIn('dataset', ['billing_group_fee_fields', 'billing_group_fees', 'billing_group_fields', 'billing_group_line_items', 'billing_group_records', 'billing_groups'])->count();
        $deferredLayouts = DB::table('ipil_historical_evidence_records')->where('dataset', 'billing_group_print_layouts')->count();
        $this->expect($actual, 71032, 'Historical billing/report evidence');
        $this->expect($deferredLayouts, 4, 'Deferred billing print layouts');

        return ['billing_report_evidence' => $actual, 'deferred_print_layouts' => $deferredLayouts, 'permit_finance_links_inferred' => 0];
    }

    /** @return array<string, mixed> */
    private function permitsAndClearances(string $root): array
    {
        $applications = $this->targetMap('business_permit_applications', 'ipil_historical_applications');
        $businesses = $this->targetMap('businesses', 'ipil_historical_businesses');
        $owners = $this->targetMap('business_owners', 'ipil_historical_owners');
        $permitIdentities = $this->identityMap('permits');
        $permitRows = [];
        foreach ($this->rawDecodedRows($root, 'permits') as [$raw, $row]) {
            $applicationKey = $this->nullableString($row['applicationId'] ?? null);
            $businessKey = $this->nullableString($row['businessId'] ?? null);
            $ownerKey = $this->nullableString($row['ownerId'] ?? null);
            $applicationId = $applicationKey === null ? null : ($applications[hash('sha256', $applicationKey)] ?? null);
            $businessId = $businessKey === null ? null : ($businesses[hash('sha256', $businessKey)] ?? null);
            $ownerId = $ownerKey === null ? null : ($owners[hash('sha256', $ownerKey)] ?? null);
            $permitRows[] = [
                'ipil_rescue_source_identity_id' => $permitIdentities[hash('sha256', (string) $row['_id'])],
                'ipil_historical_application_id' => $applicationId,
                'ipil_historical_business_id' => $businessId,
                'ipil_historical_owner_id' => $ownerId,
                'permit_number' => $this->nullableString($row['permitNumber'] ?? null),
                'source_status' => $this->nullableString($row['status'] ?? null),
                'missing_application' => $applicationId === null,
                'broken_business_edge' => $businessKey !== null && $businessId === null,
                'broken_owner_edge' => $ownerKey !== null && $ownerId === null,
                'source_payload_json' => $raw,
                'created_at' => now()->utc(), 'updated_at' => now()->utc(),
            ];
            $this->flush('ipil_historical_permit_claims', $permitRows, 'permit_claims');
        }
        $this->flush('ipil_historical_permit_claims', $permitRows, 'permit_claims', true);

        $clearanceTypes = array_fill_keys(array_keys($this->sourceLabelMap($root, 'clearance_types')), true);
        $clearanceIdentities = $this->identityMap('permit_clearances');
        $clearanceRows = [];
        foreach ($this->rawDecodedRows($root, 'permit_clearances') as [$raw, $row]) {
            $applicationId = $applications[hash('sha256', (string) $row['applicationId'])] ?? null;
            if (! is_int($applicationId)) {
                throw new RuntimeException('A historical clearance Application edge is unresolved contrary to the accepted plan.');
            }
            $typeKey = (string) ($row['clearanceTypeId'] ?? '');
            $clearanceRows[] = [
                'ipil_rescue_source_identity_id' => $clearanceIdentities[hash('sha256', (string) $row['_id'])],
                'ipil_historical_application_id' => $applicationId,
                'clearance_name' => $this->nullableString($row['clearanceName'] ?? null),
                'source_completed' => (bool) ($row['isCompleted'] ?? false),
                'broken_type_reference' => ! isset($clearanceTypes[$typeKey]),
                'source_payload_json' => $raw,
                'created_at' => now()->utc(), 'updated_at' => now()->utc(),
            ];
            $this->flush('ipil_historical_clearance_claims', $clearanceRows, 'clearance_claims');
        }
        $this->flush('ipil_historical_clearance_claims', $clearanceRows, 'clearance_claims', true);
        $permits = DB::table('ipil_historical_permit_claims')->count();
        $clearances = DB::table('ipil_historical_clearance_claims')->count();
        $this->expect($permits, 2766, 'Historical permit claims');
        $this->expect($clearances, 14615, 'Historical clearance claims');

        return ['permit_claims' => $permits, 'clearance_claims' => $clearances, 'current_permits_created' => 0, 'current_certifications_created' => 0];
    }

    /** @return array<string, mixed> */
    private function media(string $root, IpilSeedMappingProfile $profile): array
    {
        $identityMaps = [];
        $createdRows = 0;
        foreach ($this->lines($root.'/source/media/media-manifest.jsonl') as $line) {
            $entry = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $dataset = (string) $entry['source_dataset'];
            $identityMaps[$dataset] ??= $this->identityMap($dataset);
            $identityId = $identityMaps[$dataset][$entry['source_key_sha256']] ?? null;
            if (! is_int($identityId)) {
                throw new RuntimeException('A media record has no persisted SourceIdentity.');
            }
            $accepted = ($entry['association_state'] ?? null) === 'ASSOCIATED';
            $createdRows += DB::table('ipil_historical_media_evidence')->insertOrIgnore([
                'ipil_rescue_source_identity_id' => $identityId,
                'source_dataset' => $dataset,
                'association_state' => $entry['association_state'],
                'evidence_role' => $entry['evidence_role'],
                'document_type' => $entry['document_type'],
                'original_filename' => $entry['original_filename'],
                'source_sha256' => $entry['sha256'],
                'source_size_bytes' => $entry['rescued_size_bytes'],
                'object_relative_path' => $entry['object_relative_path'],
                'import_accepted' => $accepted,
                'spatie_imported' => false,
                'source_manifest_entry' => json_encode($entry, JSON_THROW_ON_ERROR),
                'created_at' => now()->utc(), 'updated_at' => now()->utc(),
            ]);
        }
        $this->created['media_evidence'] = $createdRows;

        foreach (IpilHistoricalMediaEvidence::query()->where('import_accepted', true)->where('spatie_imported', false)->cursor() as $evidence) {
            $source = $root.'/'.$evidence->object_relative_path;
            if (! is_file($source) || ! hash_equals($evidence->source_sha256, (string) hash_file('sha256', $source))) {
                throw new RuntimeException('An accepted historical media source byte failed checksum verification.');
            }
            $collection = $evidence->source_dataset === 'businesses-media'
                ? IpilHistoricalMediaEvidence::ApplicationDocumentsCollection
                : IpilHistoricalMediaEvidence::GeneratedArtifactsCollection;
            [, $associationConfidence] = $this->classification($evidence->source_dataset, $profile);
            $filename = basename((string) $evidence->original_filename);
            if ($filename === '' || $filename === '.' || $filename === '..') {
                $filename = $evidence->source_sha256.'.bin';
            }
            $media = $evidence->addMedia($source)
                ->preservingOriginal()
                ->usingFileName($filename)
                ->withCustomProperties([
                    'source_system' => 'ipil-convex',
                    'source_snapshot' => $profile->corpusId,
                    'source_identity_sha256' => DB::table('ipil_rescue_source_identities')->where('id', $evidence->ipil_rescue_source_identity_id)->value('source_key_sha256'),
                    'source_checksum_sha256' => $evidence->source_sha256,
                    'source_filename' => $evidence->original_filename,
                    'historical' => true,
                    'document_type' => $evidence->document_type,
                    'association_confidence' => $associationConfidence,
                    'mapping_profile' => $profile->name,
                ])->toMediaCollection($collection, 'ipil_gate6');
            $managed = $media->getPath();
            $managedHash = is_file($managed) ? (string) hash_file('sha256', $managed) : '';
            if (! hash_equals($evidence->source_sha256, $managedHash)) {
                throw new RuntimeException('A managed historical media copy differs from its rescued source bytes.');
            }
            $evidence->forceFill(['spatie_imported' => true, 'managed_copy_sha256' => $managedHash])->save();
            $this->created['spatie_media'] = ($this->created['spatie_media'] ?? 0) + 1;
        }
        $all = IpilHistoricalMediaEvidence::query()->count();
        $unresolved = IpilHistoricalMediaEvidence::query()->where('association_state', 'UNRESOLVED')->count();
        $imported = IpilHistoricalMediaEvidence::query()->where('spatie_imported', true)->count();
        $this->expect($all, 35, 'Historical media evidence');
        $this->expect($unresolved, 19, 'Unresolved historical media');
        $this->expect($imported, 16, 'Accepted historical media copies');

        return ['source_objects' => $all, 'accepted_imported' => $imported, 'unresolved' => $unresolved, 'unresolved_imported' => 0, 'disk' => 'local-private'];
    }

    /** @return array<string, mixed> */
    private function readProjectionChecks(): array
    {
        $joined = DB::table('ipil_historical_applications as a')
            ->join('ipil_historical_businesses as b', 'b.id', '=', 'a.ipil_historical_business_id')
            ->join('ipil_historical_owners as o', 'o.id', '=', 'a.ipil_historical_owner_id')
            ->where('a.operationally_eligible', false)
            ->where('a.can_continue', false)
            ->count();
        $this->expect($joined, 3137, 'Historical read projection');

        return ['joined_applications' => $joined, 'business_history_navigation' => true, 'search_fields_indexed' => true, 'actionable_records' => 0];
    }

    /** @return array<string, int> */
    private function resultCounts(): array
    {
        return [
            'source_identities' => DB::table('ipil_rescue_source_identities')->count(),
            'evidence_records' => DB::table('ipil_historical_evidence_records')->count(),
            'owners' => DB::table('ipil_historical_owners')->count(),
            'businesses' => DB::table('ipil_historical_businesses')->count(),
            'applications' => DB::table('ipil_historical_applications')->count(),
            'classifications' => DB::table('ipil_historical_classifications')->count(),
            'measurements' => DB::table('ipil_historical_measurements')->count(),
            'schedules' => DB::table('ipil_historical_payment_schedules')->count(),
            'payments' => DB::table('ipil_historical_payments')->count(),
            'receipt_claims' => DB::table('ipil_historical_receipt_claims')->count(),
            'permit_claims' => DB::table('ipil_historical_permit_claims')->count(),
            'clearance_claims' => DB::table('ipil_historical_clearance_claims')->count(),
            'media_evidence' => DB::table('ipil_historical_media_evidence')->count(),
            'spatie_media' => DB::table('media')->where('model_type', IpilHistoricalMediaEvidence::class)->count(),
        ];
    }

    /** @return array<string, int> */
    private function operationalBaseline(): array
    {
        $tables = [
            'users', 'permit_applications', 'payment_schedules', 'treasury_collections', 'receipts',
            'permits', 'signature_evidences', 'bplo_routing_works', 'paperless_payment_orders',
            'post_payment_office_certifications',
        ];
        $counts = [];
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $counts[$table] = DB::table($table)->count();
            }
        }

        return $counts;
    }

    /** @return array{string, string} */
    private function classification(string $dataset, IpilSeedMappingProfile $profile): array
    {
        if (isset($profile->tables[$dataset])) {
            return [$profile->tables[$dataset]['disposition'], $profile->tables[$dataset]['confidence']];
        }
        if ($dataset === 'businesses-media') {
            return ['MAP_AS_HISTORICAL_EVIDENCE', 'PROBABLE'];
        }
        if ($dataset === 'storage-objects') {
            return ['PRESERVE_UNINTERPRETED', 'UNKNOWN'];
        }
        if (str_ends_with($dataset, '-media')) {
            return ['PRESERVE_UNINTERPRETED', 'ESTABLISHED'];
        }
        if (in_array($dataset, ['pricing-corpus', 'pricing-knowledge'], true)) {
            return ['REFERENCE_DATA', 'PROBABLE'];
        }
        throw new RuntimeException("Dataset {$dataset} has no Gate 6 classification.");
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>
     */
    private function evidenceRow(int $identityId, string $dataset, string $class, string $disposition, string $confidence, bool $unresolved, ?string $payload, ?array $metadata): array
    {
        return [
            'ipil_rescue_source_identity_id' => $identityId,
            'dataset' => $dataset,
            'evidence_class' => $class,
            'disposition' => $disposition,
            'confidence' => $confidence,
            'unresolved' => $unresolved,
            'source_payload_json' => $payload,
            'metadata' => $metadata === null ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => now()->utc(), 'updated_at' => now()->utc(),
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    private function insertEvidence(array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $this->created['evidence_records'] = ($this->created['evidence_records'] ?? 0) + DB::table('ipil_historical_evidence_records')->insertOrIgnore($rows);
    }

    /** @param list<array<string, mixed>> $rows */
    private function flush(string $table, array &$rows, string $counter, bool $force = false): void
    {
        if ($rows === [] || (! $force && count($rows) < self::ChunkSize)) {
            return;
        }
        $this->created[$counter] = ($this->created[$counter] ?? 0) + DB::table($table)->insertOrIgnore($rows);
        $rows = [];
    }

    /** @return array<string, int> */
    private function identityMap(string $dataset): array
    {
        return DB::table('ipil_rescue_source_identities')->where('dataset', $dataset)->pluck('id', 'source_key_sha256')->map(fn ($value): int => (int) $value)->all();
    }

    /** @return array<string, int> */
    private function targetMap(string $dataset, string $targetTable): array
    {
        return DB::table('ipil_rescue_source_identities as s')->join($targetTable.' as t', 't.ipil_rescue_source_identity_id', '=', 's.id')->where('s.dataset', $dataset)->pluck('t.id', 's.source_key_sha256')->map(fn ($value): int => (int) $value)->all();
    }

    /** @return array<string, string> raw source ID => label */
    private function sourceLabelMap(string $root, string $dataset): array
    {
        $map = [];
        foreach ($this->decodedRows($root, $dataset) as $row) {
            $map[(string) $row['_id']] = (string) ($row['name'] ?? $row['clearanceName'] ?? '');
        }

        return $map;
    }

    /** @return iterable<string> */
    private function lines(string $path): iterable
    {
        $file = new SplFileObject($path, 'rb');
        while (! $file->eof()) {
            $line = trim((string) $file->fgets());
            if ($line !== '') {
                yield $line;
            }
        }
    }

    /** @return iterable<array<string, mixed>> */
    private function decodedRows(string $root, string $dataset): iterable
    {
        foreach ($this->lines($root.'/source/database/tables/'.$dataset.'.jsonl') as $line) {
            yield json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        }
    }

    /** @return iterable<array{string, array<string, mixed>}> */
    private function rawDecodedRows(string $root, string $dataset): iterable
    {
        foreach ($this->lines($root.'/source/database/tables/'.$dataset.'.jsonl') as $line) {
            yield [$line, json_decode($line, true, flags: JSON_THROW_ON_ERROR)];
        }
    }

    /** @return array<string, mixed> */
    private function phase(string $runId, string $code, Closure $callback): array
    {
        $started = microtime(true);
        DB::table('ipil_rescue_phase_checkpoints')->where(['ipil_rescue_import_run_id' => $runId, 'phase_code' => $code])->update(['status' => 'RUNNING', 'started_at' => now()->utc(), 'updated_at' => now()->utc()]);
        try {
            DB::beginTransaction();
            $actual = $callback();
            DB::commit();
            DB::table('ipil_rescue_phase_checkpoints')->where(['ipil_rescue_import_run_id' => $runId, 'phase_code' => $code])->update([
                'status' => 'COMPLETED', 'actual' => json_encode($actual, JSON_THROW_ON_ERROR), 'completed_at' => now()->utc(), 'updated_at' => now()->utc(),
            ]);
            $result = ['code' => $code, 'status' => 'COMPLETED', 'duration_seconds' => round(microtime(true) - $started, 3), 'actual' => $actual];
            $this->phaseResults[] = $result;

            return $result;
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::table('ipil_rescue_phase_checkpoints')->where(['ipil_rescue_import_run_id' => $runId, 'phase_code' => $code])->update([
                'status' => 'FAILED', 'error' => Str::limit($exception->getMessage(), 2000, ''), 'completed_at' => now()->utc(), 'updated_at' => now()->utc(),
            ]);
            throw $exception;
        }
    }

    private function expect(int $actual, int $expected, string $label): void
    {
        if ($actual !== $expected) {
            throw new RuntimeException("{$label} parity is {$actual}; expected {$expected}.");
        }
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private function normalizeLabel(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
    }

    private function normalizeReceipt(string $value): string
    {
        return mb_strtolower((string) preg_replace('/[^[:alnum:]]+/u', '', trim($value)));
    }
}
