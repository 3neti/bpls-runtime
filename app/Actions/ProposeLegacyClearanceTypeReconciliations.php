<?php

namespace App\Actions;

use App\Enums\LegacyImportBatchStatus;
use App\Models\LegacyImportBatch;
use App\Models\LegacyRecord;
use RuntimeException;

final class ProposeLegacyClearanceTypeReconciliations
{
    public const SchemaVersion = 'bpls.clearance-type-reconciliation-proposal.v1';

    /** @return array<string, mixed> */
    public function handle(LegacyImportBatch $batch, string $runReference): array
    {
        $this->assertReady($batch, $runReference);
        $batch->loadMissing('source');

        $targets = [];
        $targetIds = [];
        foreach ($batch->records()->where('dataset_key', 'clearance_types')->orderBy('id')->cursor() as $record) {
            $targetIds[$record->legacy_id] = true;
            $signature = $this->signature([
                'name' => $this->string($record->payload['name'] ?? null),
                'short_name' => $this->string($record->payload['shortName'] ?? null),
                'certificate_name' => $this->string($record->payload['certificateName'] ?? null),
            ]);
            $targets[$signature][] = $record;
        }

        $missing = [];
        foreach ($batch->records()->where('dataset_key', 'permit_clearances')->orderBy('id')->cursor() as $record) {
            $sourceId = $this->string($record->payload['clearanceTypeId'] ?? null);
            if ($sourceId === '' || isset($targetIds[$sourceId])) {
                continue;
            }

            $evidence = [
                'name' => $this->string($record->payload['clearanceName'] ?? null),
                'short_name' => $this->string($record->payload['clearanceShortName'] ?? null),
                'certificate_name' => $this->string($record->payload['certificateName'] ?? null),
            ];
            $missing[$sourceId]['affected_records'] = ($missing[$sourceId]['affected_records'] ?? 0) + 1;
            $missing[$sourceId]['signatures'][$this->signature($evidence)] = $evidence;
        }

        $proposals = [];
        foreach ($missing as $sourceId => $group) {
            $signatures = array_values($group['signatures']);
            $candidateRecords = count($signatures) === 1 ? ($targets[$this->signature($signatures[0])] ?? []) : [];
            $candidate = count($candidateRecords) === 1 ? $candidateRecords[0] : null;
            $candidateEvidence = $candidate instanceof LegacyRecord ? [
                'name' => $this->string($candidate->payload['name'] ?? null),
                'short_name' => $this->string($candidate->payload['shortName'] ?? null),
                'certificate_name' => $this->string($candidate->payload['certificateName'] ?? null),
            ] : null;

            $proposals[] = [
                'source_legacy_id' => $sourceId,
                'source_legacy_id_sha256' => hash('sha256', $sourceId),
                'affected_records' => $group['affected_records'],
                'source_evidence_variants' => $signatures,
                'candidate_target_legacy_id' => $candidate?->legacy_id,
                'candidate_target_legacy_id_sha256' => $candidate instanceof LegacyRecord ? hash('sha256', $candidate->legacy_id) : null,
                'candidate_evidence' => $candidateEvidence,
                'candidate_count' => count($candidateRecords),
                'basis' => $candidate instanceof LegacyRecord ? 'exact_three_field_denormalized_match' : 'no_unique_exact_three_field_match',
                'status' => $candidate instanceof LegacyRecord ? 'proposed' : 'unresolved',
                'municipal_decision_status' => 'pending',
                'accepted' => false,
                'reconciliation_row_created' => false,
            ];
        }

        usort($proposals, fn (array $left, array $right): int => $left['source_legacy_id_sha256'] <=> $right['source_legacy_id_sha256']);

        return [
            'schema_version' => self::SchemaVersion,
            'run_id' => $runReference,
            'source' => [
                'key' => $batch->source->key,
                'archive_sha256' => $batch->source->archive_checksum,
                'batch_id' => $batch->id,
                'manifest_sha256' => $batch->manifest_checksum,
            ],
            'result' => [
                'missing_source_identifier_count' => count($proposals),
                'affected_record_count' => array_sum(array_column($proposals, 'affected_records')),
                'exact_candidate_count' => count(array_filter($proposals, fn (array $proposal): bool => $proposal['status'] === 'proposed')),
                'unresolved_candidate_count' => count(array_filter($proposals, fn (array $proposal): bool => $proposal['status'] === 'unresolved')),
                'accepted_count' => 0,
            ],
            'proposals' => $proposals,
            'safety' => [
                'exact_match_only' => true,
                'normalized_name_matching' => false,
                'similarity_matching' => false,
                'reconciliation_rows_created' => false,
                'municipal_decision_recorded' => false,
                'domain_writes' => false,
                'migration_executed' => false,
                'cutover_authorized' => false,
            ],
            'completed_at' => $batch->completed_at?->toIso8601String(),
        ];
    }

    private function assertReady(LegacyImportBatch $batch, string $runReference): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Production reconciliation analysis is restricted to local or testing environments.');
        }
        if ($runReference === '') {
            throw new RuntimeException('A stable run reference is required.');
        }
        if (! in_array($batch->status, [LegacyImportBatchStatus::Staged, LegacyImportBatchStatus::StagedWithExceptions], true)) {
            throw new RuntimeException('The legacy batch must be completely staged before reconciliation analysis.');
        }
        foreach (['clearance_types', 'permit_clearances'] as $dataset) {
            if (! $batch->records()->where('dataset_key', $dataset)->exists()) {
                throw new RuntimeException("Required staged dataset [{$dataset}] is unavailable.");
            }
        }
    }

    /** @param array<string, string> $evidence */
    private function signature(array $evidence): string
    {
        return hash('sha256', json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
