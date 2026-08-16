<?php

namespace App\Actions;

use App\Enums\MigrationExceptionSeverity;
use App\Enums\MigrationExceptionStatus;
use App\Enums\MigrationValidationStatus;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMigrationException;
use App\Models\LegacyRecord;
use App\Models\MigrationValidationResult;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;

class ValidateStagedLegacyDatasets
{
    /**
     * @param  list<array{
     *   key: string,
     *   entity_type: string,
     *   file: string,
     *   sha256: string,
     *   record_count: int,
     *   identity_field: string,
     *   references: list<array{field: string, target_dataset: string, required: bool, cardinality: 'one'|'many'}>
     * }>  $datasets
     * @return array{inventory_count: int, reference_check_count: int, exception_count: int, unresolved_reference_count: int}
     */
    public function handle(LegacyImportBatch $batch, array $datasets, bool $validateReferences = true): array
    {
        $inventoryCount = 0;
        $referenceCheckCount = 0;
        $exceptionCount = 0;
        $unresolvedReferenceCount = 0;

        foreach ($datasets as $dataset) {
            $this->inventoryDataset($batch, $dataset);
            $inventoryCount++;

            foreach ($dataset['references'] as $reference) {
                $referenceCheckCount++;

                if (! $validateReferences) {
                    $this->recordSkippedReferenceValidation($batch, $dataset['key'], $reference);

                    continue;
                }

                $result = $this->validateReference($batch, $dataset['key'], $reference);
                $exceptionCount += $result['exception_count'];
                $unresolvedReferenceCount += $result['unresolved_reference_count'];
            }
        }

        return [
            'inventory_count' => $inventoryCount,
            'reference_check_count' => $referenceCheckCount,
            'exception_count' => $exceptionCount,
            'unresolved_reference_count' => $unresolvedReferenceCount,
        ];
    }

    /**
     * @param  array{
     *   key: string,
     *   entity_type: string,
     *   file: string,
     *   sha256: string,
     *   record_count: int,
     *   identity_field: string,
     *   references: list<array{field: string, target_dataset: string, required: bool, cardinality: 'one'|'many'}>
     * }  $dataset
     */
    private function inventoryDataset(LegacyImportBatch $batch, array $dataset): void
    {
        /** @var array<string, array{presence_count: int, types: array<string, int>}> $observedFields */
        $observedFields = [];
        $stagedRecordCount = 0;

        LegacyRecord::query()
            ->whereBelongsTo($batch, 'importBatch')
            ->where('dataset_key', $dataset['key'])
            ->select(['id', 'payload'])
            ->chunkById(500, function (EloquentCollection $records) use (&$observedFields, &$stagedRecordCount): void {
                foreach ($records as $record) {
                    $stagedRecordCount++;
                    /** @var array<string, list<string>> $recordFields */
                    $recordFields = [];
                    $this->observeValue($record->payload, '', $recordFields);

                    foreach ($recordFields as $path => $types) {
                        $observedFields[$path] ??= ['presence_count' => 0, 'types' => []];
                        $observedFields[$path]['presence_count']++;

                        foreach (array_unique($types) as $type) {
                            $observedFields[$path]['types'][$type] = ($observedFields[$path]['types'][$type] ?? 0) + 1;
                        }
                    }
                }
            });

        ksort($observedFields);

        $fields = [];

        foreach ($observedFields as $path => $observation) {
            ksort($observation['types']);
            $fields[] = [
                'path' => $path,
                'presence_count' => $observation['presence_count'],
                'types' => $observation['types'],
            ];
        }

        MigrationValidationResult::query()->create([
            'legacy_import_batch_id' => $batch->id,
            'dataset_key' => $dataset['key'],
            'check_key' => 'dataset_inventory',
            'status' => MigrationValidationStatus::Passed,
            'expected' => [
                'declared_record_count' => $dataset['record_count'],
                'identity_field' => $dataset['identity_field'],
                'reference_count' => count($dataset['references']),
            ],
            'actual' => [
                'staged_record_count' => $stagedRecordCount,
                'observed_field_count' => count($fields),
                'observed_fields' => $fields,
            ],
            'details' => 'Inventory contains field paths and type counts only; source values are not copied into validation evidence.',
        ]);
    }

    /**
     * @param  array<string, list<string>>  $recordFields
     */
    private function observeValue(mixed $value, string $path, array &$recordFields): void
    {
        if ($path !== '') {
            $recordFields[$path] ??= [];
            $recordFields[$path][] = $this->valueType($value);
        }

        if (! is_array($value)) {
            return;
        }

        if (array_is_list($value)) {
            foreach ($value as $item) {
                $this->observeValue($item, $path === '' ? '*' : $path.'.*', $recordFields);
            }

            return;
        }

        foreach ($value as $key => $item) {
            $childPath = $path === '' ? (string) $key : $path.'.'.$key;
            $this->observeValue($item, $childPath, $recordFields);
        }
    }

    private function valueType(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_string($value) => 'string',
            is_array($value) && array_is_list($value) => 'array',
            is_array($value) => 'object',
            default => 'unsupported',
        };
    }

    /**
     * @param  array{field: string, target_dataset: string, required: bool, cardinality: 'one'|'many'}  $reference
     * @return array{exception_count: int, unresolved_reference_count: int}
     */
    private function validateReference(LegacyImportBatch $batch, string $sourceDataset, array $reference): array
    {
        $sourceRecordCount = 0;
        $presentReferenceCount = 0;
        $resolvedReferenceCount = 0;
        $missingRequiredCount = 0;
        $invalidTypeCount = 0;
        $unresolvedReferenceCount = 0;
        $exceptionCount = 0;

        LegacyRecord::query()
            ->whereBelongsTo($batch, 'importBatch')
            ->where('dataset_key', $sourceDataset)
            ->select(['id', 'dataset_key', 'payload', 'line_number'])
            ->chunkById(500, function (EloquentCollection $records) use (
                $batch,
                $reference,
                &$sourceRecordCount,
                &$presentReferenceCount,
                &$resolvedReferenceCount,
                &$missingRequiredCount,
                &$invalidTypeCount,
                &$unresolvedReferenceCount,
                &$exceptionCount,
            ): void {
                /** @var list<array{record: LegacyRecord, value: string}> $pendingReferences */
                $pendingReferences = [];

                foreach ($records as $record) {
                    $sourceRecordCount++;
                    $extracted = $this->extractReferenceValues($record->payload, $reference['field'], $reference['cardinality']);

                    if ($extracted['missing'] && $reference['required']) {
                        $missingRequiredCount++;
                        $exceptionCount++;
                        $this->recordReferenceException(
                            $batch,
                            $record,
                            $reference,
                            'missing_required_reference',
                            'A required legacy reference is absent.',
                        );
                    }

                    if ($extracted['invalid']) {
                        $invalidTypeCount++;
                        $exceptionCount++;
                        $this->recordReferenceException(
                            $batch,
                            $record,
                            $reference,
                            'invalid_reference_type',
                            'A legacy reference does not use the declared scalar or collection shape.',
                        );
                    }

                    foreach (array_unique($extracted['values']) as $value) {
                        $presentReferenceCount++;
                        $pendingReferences[] = ['record' => $record, 'value' => $value];
                    }
                }

                $resolvedValues = $this->resolvedValues($batch, $reference['target_dataset'], array_column($pendingReferences, 'value'));

                foreach ($pendingReferences as $pendingReference) {
                    if (isset($resolvedValues[$pendingReference['value']])) {
                        $resolvedReferenceCount++;

                        continue;
                    }

                    $unresolvedReferenceCount++;
                    $exceptionCount++;
                    $this->recordReferenceException(
                        $batch,
                        $pendingReference['record'],
                        $reference,
                        'unresolved_legacy_reference',
                        'A legacy reference does not resolve to a staged target record.',
                        $pendingReference['value'],
                    );
                }
            });

        $passed = $missingRequiredCount === 0 && $invalidTypeCount === 0 && $unresolvedReferenceCount === 0;

        MigrationValidationResult::query()->create([
            'legacy_import_batch_id' => $batch->id,
            'dataset_key' => $sourceDataset,
            'check_key' => 'reference_integrity:'.$reference['field'].'->'.$reference['target_dataset'],
            'status' => $passed ? MigrationValidationStatus::Passed : MigrationValidationStatus::Failed,
            'expected' => [
                'field' => $reference['field'],
                'target_dataset' => $reference['target_dataset'],
                'required' => $reference['required'],
                'cardinality' => $reference['cardinality'],
                'unresolved_reference_count' => 0,
            ],
            'actual' => [
                'source_record_count' => $sourceRecordCount,
                'present_reference_count' => $presentReferenceCount,
                'resolved_reference_count' => $resolvedReferenceCount,
                'missing_required_count' => $missingRequiredCount,
                'invalid_type_count' => $invalidTypeCount,
                'unresolved_reference_count' => $unresolvedReferenceCount,
            ],
            'details' => $passed
                ? 'Every declared reference resolves to the exact target dataset staged in this batch.'
                : 'Unresolved, missing, or malformed references require explicit reconciliation before transformation.',
        ]);

        return [
            'exception_count' => $exceptionCount,
            'unresolved_reference_count' => $unresolvedReferenceCount,
        ];
    }

    /**
     * @param  array{field: string, target_dataset: string, required: bool, cardinality: 'one'|'many'}  $reference
     */
    private function recordSkippedReferenceValidation(LegacyImportBatch $batch, string $sourceDataset, array $reference): void
    {
        MigrationValidationResult::query()->create([
            'legacy_import_batch_id' => $batch->id,
            'dataset_key' => $sourceDataset,
            'check_key' => 'reference_integrity:'.$reference['field'].'->'.$reference['target_dataset'],
            'status' => MigrationValidationStatus::Warning,
            'expected' => [
                'field' => $reference['field'],
                'target_dataset' => $reference['target_dataset'],
                'required' => $reference['required'],
                'cardinality' => $reference['cardinality'],
            ],
            'actual' => ['evaluated' => false],
            'details' => 'Reference validation was skipped because at least one dataset failed checksum, availability, or row-count validation.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{values: list<string>, missing: bool, invalid: bool}
     */
    private function extractReferenceValues(array $payload, string $field, string $cardinality): array
    {
        $rawValue = data_get($payload, $field);

        if ($rawValue === null || $rawValue === '') {
            return ['values' => [], 'missing' => true, 'invalid' => false];
        }

        if ($cardinality === 'one') {
            if (! is_string($rawValue) && ! is_int($rawValue)) {
                return ['values' => [], 'missing' => false, 'invalid' => true];
            }

            $value = trim((string) $rawValue);

            return [
                'values' => $value === '' ? [] : [$value],
                'missing' => $value === '',
                'invalid' => false,
            ];
        }

        if (! is_array($rawValue) || ! $this->isListTree($rawValue)) {
            return ['values' => [], 'missing' => false, 'invalid' => true];
        }

        $values = [];
        $invalid = false;

        foreach (Arr::flatten($rawValue) as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (! is_string($value) && ! is_int($value)) {
                $invalid = true;

                continue;
            }

            $normalized = trim((string) $value);

            if ($normalized !== '') {
                $values[] = $normalized;
            }
        }

        return ['values' => $values, 'missing' => $values === [], 'invalid' => $invalid];
    }

    /** @param array<array-key, mixed> $values */
    private function isListTree(array $values): bool
    {
        if (! array_is_list($values)) {
            return false;
        }

        foreach ($values as $value) {
            if (is_array($value) && ! $this->isListTree($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $values
     * @return array<string, true>
     */
    private function resolvedValues(LegacyImportBatch $batch, string $targetDataset, array $values): array
    {
        $resolved = [];

        foreach (array_chunk(array_values(array_unique($values)), 500) as $valueChunk) {
            $matches = LegacyRecord::query()
                ->whereBelongsTo($batch, 'importBatch')
                ->where('dataset_key', $targetDataset)
                ->whereIn('legacy_id', $valueChunk)
                ->pluck('legacy_id');

            foreach ($matches as $match) {
                $resolved[(string) $match] = true;
            }
        }

        return $resolved;
    }

    /**
     * @param  array{field: string, target_dataset: string, required: bool, cardinality: 'one'|'many'}  $reference
     */
    private function recordReferenceException(
        LegacyImportBatch $batch,
        LegacyRecord $record,
        array $reference,
        string $code,
        string $message,
        ?string $referenceValue = null,
    ): void {
        $context = [
            'field' => $reference['field'],
            'target_dataset' => $reference['target_dataset'],
            'required' => $reference['required'],
            'cardinality' => $reference['cardinality'],
        ];

        if ($referenceValue !== null) {
            $context['reference_sha256'] = hash('sha256', $referenceValue);
        }

        LegacyMigrationException::query()->create([
            'legacy_import_batch_id' => $batch->id,
            'legacy_record_id' => $record->id,
            'dataset_key' => $record->dataset_key,
            'line_number' => $record->line_number,
            'code' => $code,
            'severity' => MigrationExceptionSeverity::Error,
            'status' => MigrationExceptionStatus::Open,
            'message' => $message,
            'context' => $context,
        ]);
    }
}
