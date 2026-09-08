<?php

namespace App\Actions;

use App\Enums\LegacyFeeRuleReconciliationStatus;
use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyLineOfBusinessReconciliationStatus;
use App\Models\FeeRule;
use App\Models\LegacyFeeRuleReconciliation;
use App\Models\LegacyImportBatch;
use App\Models\LegacyLineOfBusinessReconciliation;
use App\Models\LegacyRecord;
use App\Models\LineOfBusiness;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CharacterizeLegacyFeeCatalog
{
    public const SchemaVersion = 'bpls.legacy-fee-catalog-candidates.v1';

    /** @return array<string, mixed> */
    public function handle(LegacyImportBatch $batch): array
    {
        $this->assertReady($batch);
        $batch->loadMissing('source');

        $records = $batch->records()
            ->whereIn('dataset_key', ['fees', 'fee_overrides', 'division_groups', 'divisions', 'groups'])
            ->orderBy('id')
            ->get();
        $byIdentity = $records->keyBy(fn (LegacyRecord $record): string => $record->dataset_key.'|'.$record->legacy_id);
        $divisionGroups = $records->where('dataset_key', 'division_groups')->groupBy(
            fn (LegacyRecord $record): string => $this->string($record->payload['divisionId'] ?? null),
        )->map(fn (Collection $group): array => $group->values()->all())->all();
        $overrides = $records->where('dataset_key', 'fee_overrides')->groupBy(
            fn (LegacyRecord $record): string => $this->string($record->payload['feeId'] ?? null),
        );
        $fees = $records->where('dataset_key', 'fees')->values();
        $nameCounts = $fees->countBy(fn (LegacyRecord $record): string => $this->string($record->payload['name'] ?? null));
        $targetNameCounts = FeeRule::query()->get(['id', 'name'])->countBy('name')->all();
        $counts = ['constant' => 0, 'range' => 0, 'formula' => 0, 'created' => 0, 'preserved' => 0];
        $rangeBandCount = 0;
        $matchedOverrideCount = 0;
        $overrideRecordCount = $records->where('dataset_key', 'fee_overrides')->count();
        $lineOfBusinessCandidateCount = $this->characterizeLinesOfBusiness($batch, $records->where('dataset_key', 'groups')->values());

        DB::transaction(function () use ($batch, $fees, $byIdentity, $divisionGroups, $overrides, $nameCounts, $targetNameCounts, &$counts, &$rangeBandCount, &$matchedOverrideCount): void {
            foreach ($fees as $record) {
                $candidate = $this->candidate(
                    $record,
                    $batch,
                    $byIdentity,
                    $divisionGroups,
                    $overrides->get($record->legacy_id, collect()),
                    (int) $nameCounts->get($this->string($record->payload['name'] ?? null), 0),
                    $targetNameCounts,
                );
                $type = $candidate['calculation_type'];
                $counts[$type]++;
                $rangeBandCount += $candidate['range_count'];
                $matchedOverrideCount += $candidate['override_count'];

                $reconciliation = LegacyFeeRuleReconciliation::query()->firstOrNew([
                    'legacy_source_id' => $batch->legacy_source_id,
                    'source_dataset' => 'fees',
                    'source_legacy_id' => $record->legacy_id,
                ]);

                if ($reconciliation->exists) {
                    $recordedHash = data_get($reconciliation->metadata, 'source_payload_sha256');
                    if (is_string($recordedHash) && ! hash_equals($recordedHash, $record->payload_hash)) {
                        throw new RuntimeException('Legacy fee catalogue characterization refused a changed source payload under an existing source identity.');
                    }
                    if ($reconciliation->status !== LegacyFeeRuleReconciliationStatus::Pending) {
                        $counts['preserved']++;

                        continue;
                    }
                }

                $reconciliation->fill([
                    'status' => LegacyFeeRuleReconciliationStatus::Pending,
                    'metadata' => $candidate,
                ])->save();
                $counts['created']++;
            }
        });

        return [
            'schema_version' => self::SchemaVersion,
            'source' => [
                'key' => $batch->source->key,
                'archive_sha256' => $batch->source->archive_checksum,
                'batch_id' => $batch->id,
                'manifest_sha256' => $batch->manifest_checksum,
            ],
            'summary' => [
                'fee_definition_count' => $fees->count(),
                'constant_count' => $counts['constant'],
                'range_count' => $counts['range'],
                'formula_count' => $counts['formula'],
                'range_band_count' => $rangeBandCount,
                'override_count' => $overrideRecordCount,
                'matched_override_count' => $matchedOverrideCount,
                'orphan_override_count' => $overrideRecordCount - $matchedOverrideCount,
                'duplicate_name_group_count' => $nameCounts->filter(fn (int $count): bool => $count > 1)->count(),
                'legacy_group_record_count' => $records->where('dataset_key', 'groups')->count(),
                'line_of_business_candidate_count' => $lineOfBusinessCandidateCount,
                'candidate_rows_written' => $counts['created'],
                'decided_rows_preserved' => $counts['preserved'],
                'active_fee_rules_created' => 0,
                'financial_records_changed' => 0,
            ],
            'safety' => [
                'source_ids_exposed' => false,
                'formulas_evaluated' => false,
                'legacy_values_activated' => false,
                'historical_liability_recalculated' => false,
                'financial_domain_writes' => false,
            ],
        ];
    }

    private function assertReady(LegacyImportBatch $batch): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Legacy fee catalogue characterization is restricted to local and testing environments.');
        }
        if (! in_array($batch->status, [LegacyImportBatchStatus::Staged, LegacyImportBatchStatus::StagedWithExceptions], true)) {
            throw new RuntimeException('The legacy batch must be completely staged before fee catalogue characterization.');
        }
        foreach (['fees', 'fee_overrides', 'division_groups', 'divisions', 'groups'] as $dataset) {
            if (! $batch->records()->where('dataset_key', $dataset)->exists()) {
                throw new RuntimeException("Required staged dataset [{$dataset}] is unavailable.");
            }
        }
    }

    /** @param Collection<int, LegacyRecord> $groups */
    private function characterizeLinesOfBusiness(LegacyImportBatch $batch, Collection $groups): int
    {
        $currentNames = LineOfBusiness::query()->get(['id', 'name'])->groupBy(
            fn (LineOfBusiness $line): string => str($line->name)->squish()->lower()->toString(),
        );
        $grouped = $groups->groupBy(function (LegacyRecord $group): string {
            return str($this->string($group->payload['name'] ?? null))->squish()->lower()->toString();
        })->filter(fn (Collection $records, string $name): bool => $name !== '');

        foreach ($grouped as $normalizedName => $records) {
            $sourceValueHash = hash('sha256', $normalizedName);
            $reconciliation = LegacyLineOfBusinessReconciliation::query()->firstOrNew([
                'legacy_source_id' => $batch->legacy_source_id,
                'source_dataset' => 'groups',
                'source_value_hash' => $sourceValueHash,
            ]);
            if ($reconciliation->exists && $reconciliation->status !== LegacyLineOfBusinessReconciliationStatus::Pending) {
                continue;
            }
            $reconciliation->fill([
                'status' => LegacyLineOfBusinessReconciliationStatus::Pending,
                'metadata' => [
                    'schema_version' => self::SchemaVersion,
                    'batch_id' => $batch->id,
                    'name' => $this->string($records->first()->payload['name'] ?? null),
                    'normalized_name_sha256' => $sourceValueHash,
                    'source_record_count' => $records->count(),
                    'source_payload_sha256' => $records->pluck('payload_hash')->sort()->values()->all(),
                    'exact_name_target_count' => $currentNames->get($normalizedName, collect())->count(),
                    'identity_inferred_from_name' => false,
                    'executable' => false,
                ],
            ])->save();
        }

        return $grouped->count();
    }

    /**
     * @param  Collection<string, LegacyRecord>  $byIdentity
     * @param  array<int|string, array<int, mixed>>  $divisionGroups
     * @param  Collection<int, LegacyRecord>  $overrides
     * @param  array<string, int>  $targetNameCounts
     * @return array<string, mixed>
     */
    private function candidate(LegacyRecord $record, LegacyImportBatch $batch, Collection $byIdentity, array $divisionGroups, Collection $overrides, int $duplicateNameCount, array $targetNameCounts): array
    {
        $payload = $record->payload;
        $legacyType = $this->string($payload['feeType'] ?? null);
        $calculationType = match ($legacyType) {
            'Constant' => 'constant',
            'Range' => 'range',
            default => 'formula',
        };
        $name = $this->string($payload['name'] ?? null);
        $divisionId = $this->string($payload['divisionId'] ?? null);
        $groupId = $this->string($payload['groupId'] ?? null);
        $division = $divisionId === '' ? null : $byIdentity->get('divisions|'.$divisionId);
        $sourceGroupIds = $groupId !== ''
            ? collect([$groupId])
            : collect($divisionGroups[$divisionId] ?? [])
                ->filter(fn (mixed $edge): bool => $edge instanceof LegacyRecord)
                ->map(fn (LegacyRecord $edge): string => $this->string($edge->payload['groupId'] ?? null));
        $groups = $sourceGroupIds
            ->map(function (string $sourceGroupId) use ($byIdentity): ?string {
                $group = $byIdentity->get('groups|'.$sourceGroupId);

                return $group instanceof LegacyRecord ? $this->nullableString($group->payload['name'] ?? null) : null;
            })
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $ranges = collect($this->items($payload['ranges'] ?? null))->map(fn (array $range): array => [
            'minimum_basis_minor' => $this->minor($range['min'] ?? null),
            'maximum_basis_minor' => $this->minor($range['max'] ?? null),
            'amount_minor' => $this->minor($range['fee'] ?? null),
            'formula' => $this->nullableString($range['formula'] ?? null),
        ])->values();
        $overrideRows = $overrides->map(function (LegacyRecord $override) use ($byIdentity): array {
            $edgeId = $this->string($override->payload['divisionGroupId'] ?? null);
            $edge = $edgeId === '' ? null : $byIdentity->get('division_groups|'.$edgeId);
            $groupId = $edge instanceof LegacyRecord ? $this->string($edge->payload['groupId'] ?? null) : '';
            $group = $groupId === '' ? null : $byIdentity->get('groups|'.$groupId);

            return [
                'source_payload_sha256' => $override->payload_hash,
                'group_name' => $group instanceof LegacyRecord ? $this->nullableString($group->payload['name'] ?? null) : null,
                'amount_minor' => $this->minor($override->payload['overrideAmount'] ?? null),
                'range_field' => $this->nullableString($override->payload['overrideRangeField'] ?? null),
                'range_count' => count($this->items($override->payload['overrideRanges'] ?? null)),
                'reason' => $this->nullableString($override->payload['reason'] ?? null),
            ];
        })->values();
        $rangeField = $this->nullableString($payload['rangeField'] ?? null);
        $formula = $this->nullableString($payload['formula'] ?? null);
        $scope = match (true) {
            $groupId !== '' => 'direct_group',
            $divisionId !== '' => 'inherited_division',
            default => 'application_wide',
        };
        $blockers = ['municipal_fee_identity_acceptance_required', 'effective_date_acceptance_required'];
        if ($scope !== 'application_wide') {
            $blockers[] = 'line_of_business_crosswalk_required';
        }
        if ($calculationType === 'formula') {
            $blockers[] = 'formula_semantics_and_rounding_not_implemented';
        }
        if ($calculationType === 'range' && ! in_array($rangeField, ['capitalInvestment', 'grossSales'], true)) {
            $blockers[] = 'assessment_basis_not_implemented';
        }
        if ($overrideRows->isNotEmpty()) {
            $blockers[] = 'override_policy_acceptance_required';
        }

        return [
            'schema_version' => self::SchemaVersion,
            'batch_id' => $batch->id,
            'source_payload_sha256' => $record->payload_hash,
            'source_fee_id_sha256' => hash('sha256', $record->legacy_id),
            'name' => $name,
            'duplicate_name_count' => $duplicateNameCount,
            'legacy_fee_type' => $legacyType,
            'calculation_type' => $calculationType,
            'legacy_fee_category' => $this->nullableString($payload['feeCategory'] ?? null),
            'application_types' => $this->strings($payload['applicationType'] ?? null),
            'amount_minor' => $this->minor($payload['amount'] ?? null),
            'range_field' => $rangeField,
            'ranges' => $ranges->all(),
            'range_count' => $ranges->count(),
            'formula' => $formula,
            'formula_sha256' => $formula === null ? null : hash('sha256', $formula),
            'applicability_scope' => $scope,
            'division_name' => $division instanceof LegacyRecord ? $this->nullableString($division->payload['name'] ?? null) : null,
            'applicable_group_names' => $groups->all(),
            'applicable_group_count' => $groups->count(),
            'overrides' => $overrideRows->all(),
            'override_count' => $overrideRows->count(),
            'exact_name_target_count' => $targetNameCounts[$name] ?? 0,
            'blockers' => array_values(array_unique($blockers)),
            'executable' => false,
            'source_identity_inferred_from_name' => false,
        ];
    }

    private function minor(mixed $value): ?int
    {
        if (! is_int($value) && ! is_float($value) && ! is_numeric($value)) {
            return null;
        }

        $minor = (int) round((float) $value * 100, 0, PHP_ROUND_HALF_UP);

        return $minor >= 0 ? $minor : null;
    }

    /** @return list<array<string, mixed>> */
    private function items(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_array(...))) : [];
    }

    /** @return list<string> */
    private function strings(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = $this->string($value);

        return $value === '' ? null : $value;
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
