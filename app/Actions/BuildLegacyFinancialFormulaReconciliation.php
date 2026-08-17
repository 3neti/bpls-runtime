<?php

namespace App\Actions;

use App\Enums\LegacyImportBatchStatus;
use App\Models\LegacyImportBatch;
use RuntimeException;
use ZipArchive;

final class BuildLegacyFinancialFormulaReconciliation
{
    public const SchemaVersion = 'bpls.production-financial-formula-reconciliation.v1';

    private const EvaluatorEntries = [
        'fee_calculator' => 'bpls-system-main/apps/admin/lib/utils/fee-calculator.ts',
        'surcharge_penalty_calculator' => 'bpls-system-main/apps/admin/lib/utils/surcharge-penalty-calculator.ts',
        'payment_schedules' => 'bpls-system-main/packages/backend/convex/paymentSchedules.ts',
    ];

    private const StandardFeeVariables = [
        'numberOfEmployees',
        'maleEmployeeCount',
        'femaleEmployeeCount',
        'businessArea',
        'floorArea',
        'capitalInvestment',
        'grossSales',
        'businessAnnualRevenue',
    ];

    /** @return array<string, mixed> */
    public function handle(LegacyImportBatch $batch, string $runReference, string $legacyArchive): array
    {
        $this->assertReady($batch, $runReference, $legacyArchive);
        $batch->loadMissing('source');
        $evaluators = $this->evaluatorEvidence($legacyArchive);
        $uomVariables = $this->uomVariables($batch);
        $historical = $this->historicalScheduleEvidence($batch);
        $feeMatrix = [];

        foreach ($batch->records()->where('dataset_key', 'fees')->orderBy('id')->cursor() as $record) {
            $name = $this->string($record->payload['name'] ?? null);
            $formula = $this->string($record->payload['formula'] ?? null);
            $ranges = $this->items($record->payload['ranges'] ?? null);
            $rangeFormulas = array_values(array_filter(array_map(
                fn (array $range): string => $this->string($range['formula'] ?? null),
                $ranges,
            )));
            $formulaTokens = $this->formulaTokens([$formula, ...$rangeFormulas]);
            $recognizedVariables = array_values(array_intersect($formulaTokens, [...self::StandardFeeVariables, ...$uomVariables]));
            $unknownVariables = array_values(array_diff($formulaTokens, $recognizedVariables, ['Math', 'min', 'max', 'floor', 'ceil', 'round', 'abs', 'pow', 'sqrt']));
            $nameEvidence = $historical['by_name'][$name] ?? ['item_count' => 0, 'edited_count' => 0, 'amount_fingerprint_count' => 0];

            $feeMatrix[] = [
                'source_fee_id' => $record->legacy_id,
                'source_fee_id_sha256' => hash('sha256', $record->legacy_id),
                'name' => $name,
                'fee_type' => $this->string($record->payload['feeType'] ?? null),
                'fee_category' => $this->string($record->payload['feeCategory'] ?? null),
                'application_types' => $this->strings($record->payload['applicationType'] ?? null),
                'configuration' => [
                    'amount' => $record->payload['amount'] ?? null,
                    'range_field' => $this->string($record->payload['rangeField'] ?? null) ?: null,
                    'ranges' => $ranges,
                    'formula' => $formula === '' ? null : $formula,
                    'formula_sha256' => $formula === '' ? null : hash('sha256', $formula),
                    'formula_tokens' => $formulaTokens,
                    'recognized_variables' => $recognizedVariables,
                    'unrecognized_tokens' => $unknownVariables,
                ],
                'reconciliation' => [
                    'revenue_code_status' => 'unresolved_no_authoritative_crosswalk',
                    'production_configuration_status' => 'observed',
                    'legacy_evaluator_status' => $formula === '' && $rangeFormulas === [] ? 'not_formula_bearing' : 'characterized_not_authorized',
                    'historical_exact_fee_link_count' => 0,
                    'historical_name_signal_count' => $nameEvidence['item_count'],
                    'historical_name_signal_edited_count' => $nameEvidence['edited_count'],
                    'historical_amount_fingerprint_count' => $nameEvidence['amount_fingerprint_count'],
                    'name_signal_establishes_identity' => false,
                    'accepted_interpretation' => false,
                    'executable_policy' => false,
                ],
                'blockers' => array_values(array_filter([
                    'municipal_fee_identity_crosswalk_required',
                    'revenue_code_crosswalk_required',
                    $nameEvidence['item_count'] > 0 ? 'historical_schedule_items_have_name_signal_only' : 'no_historical_schedule_signal',
                    $unknownVariables !== [] ? 'formula_contains_unreconciled_tokens' : null,
                    $formula !== '' || $rangeFormulas !== [] ? 'formula_semantics_and_rounding_require_acceptance' : null,
                ])),
            ];
        }

        $overrides = $this->overrideEvidence($batch);
        $surcharge = $this->surchargeEvidence($batch);
        $formulaBearing = count(array_filter($feeMatrix, fn (array $fee): bool => $fee['configuration']['formula'] !== null));
        $rangeBased = count(array_filter($feeMatrix, fn (array $fee): bool => $fee['fee_type'] === 'Range'));

        return [
            'schema_version' => self::SchemaVersion,
            'run_id' => $runReference,
            'source' => [
                'key' => $batch->source->key,
                'archive_sha256' => $batch->source->archive_checksum,
                'batch_id' => $batch->id,
                'manifest_sha256' => $batch->manifest_checksum,
                'legacy_archive_sha256' => hash_file('sha256', $legacyArchive),
            ],
            'evaluator_evidence' => $evaluators,
            'summary' => [
                'fee_definition_count' => count($feeMatrix),
                'formula_bearing_fee_count' => $formulaBearing,
                'range_based_fee_count' => $rangeBased,
                'fee_override_count' => count($overrides),
                'uom_record_count' => $historical['uom_record_count'],
                'uom_variable_count' => count($uomVariables),
                'payment_schedule_count' => $historical['schedule_count'],
                'payment_count' => $batch->records()->where('dataset_key', 'payments')->count(),
                'schedule_fee_item_count' => $historical['fee_item_count'],
                'schedule_fee_item_with_exact_fee_id_count' => $historical['exact_fee_id_count'],
                'schedule_fee_distinct_name_count' => count($historical['by_name']),
                'schedule_fee_edited_item_count' => $historical['edited_item_count'],
                'historical_formula_attribution_status' => $historical['exact_fee_id_count'] === 0
                    ? 'blocked_missing_exact_fee_identity'
                    : 'partial_exact_identity_available',
                'accepted_interpretation_count' => 0,
                'executable_policy_count' => 0,
            ],
            'fee_matrix' => $feeMatrix,
            'fee_overrides' => $overrides,
            'surcharge_penalty_configuration' => $surcharge,
            'historical_outcome_evidence' => [
                'schedule_count' => $historical['schedule_count'],
                'fee_item_count' => $historical['fee_item_count'],
                'exact_fee_id_count' => $historical['exact_fee_id_count'],
                'distinct_fee_name_count' => count($historical['by_name']),
                'edited_item_count' => $historical['edited_item_count'],
                'exact_formula_attribution_possible' => $historical['exact_fee_id_count'] > 0,
                'name_matching_authorized' => false,
                'historical_liability_recalculated' => false,
            ],
            'safety' => [
                'formulas_evaluated' => false,
                'historical_liability_recalculated' => false,
                'historical_amounts_rewritten' => false,
                'fee_identity_inferred_from_name' => false,
                'ordinance_authority_inferred' => false,
                'production_configuration_accepted_as_policy' => false,
                'financial_domain_writes' => false,
                'migration_executed' => false,
                'cutover_authorized' => false,
            ],
            'completed_at' => $batch->completed_at?->toIso8601String(),
        ];
    }

    private function assertReady(LegacyImportBatch $batch, string $runReference, string $legacyArchive): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Production financial reconciliation is restricted to local or testing environments.');
        }
        if ($runReference === '') {
            throw new RuntimeException('A stable run reference is required.');
        }
        if (! in_array($batch->status, [LegacyImportBatchStatus::Staged, LegacyImportBatchStatus::StagedWithExceptions], true)) {
            throw new RuntimeException('The legacy batch must be completely staged before financial reconciliation.');
        }
        if (! is_file($legacyArchive) || ! is_readable($legacyArchive)) {
            throw new RuntimeException('The authoritative legacy source archive is unavailable.');
        }
        foreach (['fees', 'fee_overrides', 'payment_schedules', 'payments', 'unitsOfMeasurement', 'surcharge_penalty_config'] as $dataset) {
            if (! $batch->records()->where('dataset_key', $dataset)->exists()) {
                throw new RuntimeException("Required staged dataset [{$dataset}] is unavailable.");
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function evaluatorEvidence(string $legacyArchive): array
    {
        $zip = new ZipArchive;
        if ($zip->open($legacyArchive, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The authoritative legacy source archive is not a readable ZIP file.');
        }

        try {
            $sources = [];
            foreach (self::EvaluatorEntries as $key => $entry) {
                $contents = $zip->getFromName($entry);
                if (! is_string($contents)) {
                    throw new RuntimeException("Required legacy evaluator source [{$entry}] is unavailable.");
                }
                $sources[$key] = ['path' => $entry, 'sha256' => hash('sha256', $contents), 'contents' => $contents];
            }
        } finally {
            $zip->close();
        }

        return [
            [
                'component' => 'fee_calculator',
                'path' => $sources['fee_calculator']['path'],
                'sha256' => $sources['fee_calculator']['sha256'],
                'observed_behavior' => [
                    'formula_uses_dynamic_function' => str_contains($sources['fee_calculator']['contents'], 'Function(`"use strict"; return (${expression})`)()'),
                    'invalid_or_missing_formula_returns_zero' => str_contains($sources['fee_calculator']['contents'], 'return 0'),
                    'negative_result_clamped_to_zero' => str_contains($sources['fee_calculator']['contents'], 'Math.max(0, result)'),
                    'formula_result_rounded_by_evaluator' => false,
                ],
            ],
            [
                'component' => 'surcharge_penalty_calculator',
                'path' => $sources['surcharge_penalty_calculator']['path'],
                'sha256' => $sources['surcharge_penalty_calculator']['sha256'],
                'observed_behavior' => [
                    'formula_uses_dynamic_function' => str_contains($sources['surcharge_penalty_calculator']['contents'], 'Function(`"use strict"; return (${expression})`)()'),
                    'due_date_treated_as_surcharge_eligible' => str_contains($sources['surcharge_penalty_calculator']['contents'], 'daysDiff >= 0'),
                    'month_count_uses_thirty_day_ceiling' => str_contains($sources['surcharge_penalty_calculator']['contents'], '/ 30'),
                    'result_uses_two_decimal_rounding' => str_contains($sources['surcharge_penalty_calculator']['contents'], 'roundTo2Decimals'),
                ],
            ],
            [
                'component' => 'payment_schedules',
                'path' => $sources['payment_schedules']['path'],
                'sha256' => $sources['payment_schedules']['sha256'],
                'observed_behavior' => [
                    'fee_identity_optional' => str_contains($sources['payment_schedules']['contents'], 'feeId?:'),
                    'tax_split_rounds_to_two_decimals' => str_contains($sources['payment_schedules']['contents'], 'Math.round((fee.totalAmount / sectionCount) * 100) / 100'),
                    'section_total_rounds_to_two_decimals' => str_contains($sources['payment_schedules']['contents'], 'Math.round(sectionFees.reduce'),
                    'status_compares_two_decimal_values' => str_contains($sources['payment_schedules']['contents'], 'roundedPaid >= roundedTotal'),
                ],
            ],
        ];
    }

    /** @return list<string> */
    private function uomVariables(LegacyImportBatch $batch): array
    {
        $variables = [];
        foreach ($batch->records()->where('dataset_key', 'unitsOfMeasurement')->orderBy('id')->cursor() as $record) {
            $variable = $this->string($record->payload['variableName'] ?? null);
            if ($variable !== '') {
                $variables[$variable] = true;
            }
        }

        $variables = array_keys($variables);
        sort($variables);

        return $variables;
    }

    /** @return array{schedule_count: int, fee_item_count: int, exact_fee_id_count: int, edited_item_count: int, uom_record_count: int, by_name: array<string, array{item_count: int, edited_count: int, amount_fingerprint_count: int}>} */
    private function historicalScheduleEvidence(LegacyImportBatch $batch): array
    {
        $scheduleCount = 0;
        $feeItemCount = 0;
        $exactFeeIdCount = 0;
        $editedItemCount = 0;
        $byName = [];

        foreach ($batch->records()->where('dataset_key', 'payment_schedules')->orderBy('id')->cursor() as $record) {
            $scheduleCount++;
            foreach ($this->items($record->payload['fees'] ?? null) as $fee) {
                $feeItemCount++;
                $name = $this->string($fee['feeName'] ?? null);
                $feeId = $this->string($fee['feeId'] ?? null);
                $edited = ($fee['isEdited'] ?? false) === true;
                $amountFingerprint = hash('sha256', json_encode([
                    'original' => $fee['originalAmount'] ?? null,
                    'section' => $fee['sectionAmount'] ?? null,
                ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

                $exactFeeIdCount += $feeId === '' ? 0 : 1;
                $editedItemCount += $edited ? 1 : 0;
                $byName[$name]['item_count'] = ($byName[$name]['item_count'] ?? 0) + 1;
                $byName[$name]['edited_count'] = ($byName[$name]['edited_count'] ?? 0) + ($edited ? 1 : 0);
                $byName[$name]['amount_fingerprints'][$amountFingerprint] = true;
            }
        }

        foreach ($byName as &$evidence) {
            $evidence['amount_fingerprint_count'] = count($evidence['amount_fingerprints']);
            unset($evidence['amount_fingerprints']);
        }
        unset($evidence);

        return [
            'schedule_count' => $scheduleCount,
            'fee_item_count' => $feeItemCount,
            'exact_fee_id_count' => $exactFeeIdCount,
            'edited_item_count' => $editedItemCount,
            'uom_record_count' => $batch->records()->where('dataset_key', 'unitsOfMeasurement')->count(),
            'by_name' => $byName,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function overrideEvidence(LegacyImportBatch $batch): array
    {
        $overrides = [];
        foreach ($batch->records()->where('dataset_key', 'fee_overrides')->orderBy('id')->cursor() as $record) {
            $feeId = $this->string($record->payload['feeId'] ?? null);
            $divisionGroupId = $this->string($record->payload['divisionGroupId'] ?? null);
            $overrides[] = [
                'source_override_id' => $record->legacy_id,
                'source_override_id_sha256' => hash('sha256', $record->legacy_id),
                'fee_id' => $feeId,
                'fee_id_sha256' => $feeId === '' ? null : hash('sha256', $feeId),
                'division_group_id' => $divisionGroupId,
                'division_group_id_sha256' => $divisionGroupId === '' ? null : hash('sha256', $divisionGroupId),
                'override_amount' => $record->payload['overrideAmount'] ?? null,
                'override_range_field' => $this->string($record->payload['overrideRangeField'] ?? null) ?: null,
                'override_ranges' => $this->items($record->payload['overrideRanges'] ?? null),
                'fee_reference_resolved' => $feeId !== '' && $batch->records()->where('dataset_key', 'fees')->where('legacy_id', $feeId)->exists(),
                'accepted_interpretation' => false,
                'executable_policy' => false,
            ];
        }

        return $overrides;
    }

    /** @return list<array<string, mixed>> */
    private function surchargeEvidence(LegacyImportBatch $batch): array
    {
        $configurations = [];
        foreach ($batch->records()->where('dataset_key', 'surcharge_penalty_config')->orderBy('id')->cursor() as $record) {
            $surchargeFormula = $this->string($record->payload['surchargeFormula'] ?? null);
            $penaltyFormula = $this->string($record->payload['penaltyFormula'] ?? null);
            $configurations[] = [
                'source_configuration_id' => $record->legacy_id,
                'source_configuration_id_sha256' => hash('sha256', $record->legacy_id),
                'active' => ($record->payload['isActive'] ?? false) === true,
                'surcharge_formula' => $surchargeFormula === '' ? null : $surchargeFormula,
                'surcharge_formula_sha256' => $surchargeFormula === '' ? null : hash('sha256', $surchargeFormula),
                'penalty_formula' => $penaltyFormula === '' ? null : $penaltyFormula,
                'penalty_formula_sha256' => $penaltyFormula === '' ? null : hash('sha256', $penaltyFormula),
                'due_dates' => [
                    'annual' => $record->payload['annualDueDate'] ?? null,
                    'semi_annual' => $record->payload['semiAnnualDueDates'] ?? null,
                    'quarterly' => $record->payload['quarterlyDueDates'] ?? null,
                ],
                'accepted_interpretation' => false,
                'executable_policy' => false,
            ];
        }

        return $configurations;
    }

    /** @param list<string> $formulas
     * @return list<string>
     */
    private function formulaTokens(array $formulas): array
    {
        $tokens = [];
        foreach ($formulas as $formula) {
            preg_match_all('/[a-zA-Z_][a-zA-Z0-9_]*/', $formula, $matches);
            foreach ($matches[0] as $token) {
                $tokens[$token] = true;
            }
        }
        $tokens = array_keys($tokens);
        sort($tokens);

        return $tokens;
    }

    /** @return list<array<string, mixed>> */
    private function items(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_array(...)));
    }

    /** @return list<string> */
    private function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
