<?php

namespace App\Actions;

use App\Enums\LegacyMappingExecutionStatus;
use App\Models\Assessment;
use App\Models\AssessmentLine;
use App\Models\LegacyHistoricalFinancialMappingSet;
use App\Models\LegacyHistoricalFinancialPreservationExecution;
use App\Models\PaymentSchedule;
use App\Models\PaymentScheduleLine;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use RuntimeException;

class AuditLegacyHistoricalFinancialPreservationRestoration
{
    public function __construct(private AcceptLegacyHistoricalFinancialCohortMappings $mappingAcceptance) {}

    /** @return array<string, mixed> */
    public function handle(
        LegacyHistoricalFinancialPreservationExecution $execution,
        LegacyHistoricalFinancialMappingSet $mappingSet,
    ): array {
        if ($execution->status !== LegacyMappingExecutionStatus::RolledBack) {
            throw new RuntimeException('Restoration audit requires a rolled-back historical preservation execution.');
        }
        if ($execution->preservationPlan->legacy_financial_mapping_plan_id !== $mappingSet->legacy_financial_mapping_plan_id) {
            throw new RuntimeException('Execution and frozen mapping set do not share the same financial plan.');
        }
        $this->mappingAcceptance->audit($mappingSet);
        $execution->loadMissing('bundles');
        $before = data_get($execution->metadata, 'operational_counts_before');
        $current = $this->operationalCounts();
        $rollbackCount = data_get($execution->metadata, 'rollback_bundle_count');
        $passed = $execution->bundles->isEmpty()
            && is_array($before)
            && $before === $current
            && $rollbackCount === $execution->created_count
            && data_get($execution->metadata, 'source_records_deleted') === false
            && data_get($execution->metadata, 'application_mappings_deleted') === false
            && data_get($execution->metadata, 'operational_financial_records_deleted') === false;

        return [
            'schema_version' => 'bpls.historical-financial-preservation-restoration-audit.v1',
            'passed' => $passed,
            'execution_id' => $execution->id,
            'mapping_set_id' => $mappingSet->id,
            'mapping_set_sha256' => $mappingSet->accepted_mapping_set_sha256,
            'remaining_bundle_count' => $execution->bundles->count(),
            'rollback_bundle_count' => $rollbackCount,
            'operational_counts_before' => $before,
            'operational_counts_after_restoration' => $current,
            'accepted_mapping_set_intact' => true,
            'source_records_deleted' => false,
            'accepted_application_mappings_deleted' => false,
            'safety' => [
                'historical_recalculation' => false,
                'operational_financial_mutation' => false,
                'production_execution_authorized' => false,
            ],
        ];
    }

    /** @return array<string, int> */
    private function operationalCounts(): array
    {
        return [
            'assessments' => Assessment::query()->count(),
            'assessment_lines' => AssessmentLine::query()->count(),
            'payment_schedules' => PaymentSchedule::query()->count(),
            'payment_schedule_lines' => PaymentScheduleLine::query()->count(),
            'treasury_collections' => TreasuryCollection::query()->count(),
            'receipts' => Receipt::query()->count(),
        ];
    }
}
