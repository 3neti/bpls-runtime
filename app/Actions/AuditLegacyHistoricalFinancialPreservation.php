<?php

namespace App\Actions;

use App\Enums\LegacyMappingExecutionStatus;
use App\Models\LegacyHistoricalFinancialPreservationExecution;
use RuntimeException;

class AuditLegacyHistoricalFinancialPreservation
{
    public function __construct(private LegacyHistoricalFinancialPreservationProjector $projector) {}

    /** @return array<string, mixed> */
    public function handle(LegacyHistoricalFinancialPreservationExecution $execution): array
    {
        if ($execution->status !== LegacyMappingExecutionStatus::Completed) {
            throw new RuntimeException("Historical preservation execution [{$execution->run_reference}] is not completed.");
        }

        $execution->loadMissing(['preservationPlan.financialMappingPlan', 'bundles.proposal.legacyRecord']);
        $financialProposals = $execution->preservationPlan->financialMappingPlan->proposals()
            ->with('legacyRecord')
            ->whereIn('kind', ['payment_schedule', 'payment_schedule_fee', 'payment', 'receipt_claim'])
            ->orderBy('id')
            ->get();
        $sourceTotals = $this->emptyTotals();
        $targetTotals = $this->emptyTotals();
        $checks = [];

        foreach ($execution->bundles as $bundle) {
            $result = $this->projector->project(
                $execution->preservationPlan->financialMappingPlan,
                $bundle->proposal->legacyRecord,
                $financialProposals,
            );
            $source = $result['projection'];
            $target = $bundle->snapshot;
            $passed = $result['reasons'] === []
                && hash_equals($bundle->source_projection_hash, $this->projector->hash($source))
                && hash_equals($bundle->bundle_snapshot_hash, $this->projector->hash($target))
                && hash_equals($this->projector->hash($source), $this->projector->hash($target));
            $checks[] = ['bundle_id' => $bundle->id, 'passed' => $passed];
            $sourceTotals = $this->addTotals($sourceTotals, $source);
            $targetTotals = $this->addTotals($targetTotals, $target);
        }

        $operationalBefore = $execution->metadata['operational_counts_before'] ?? [];
        $operationalAfter = $execution->metadata['operational_counts_after'] ?? [];
        $passed = ! in_array(false, array_column($checks, 'passed'), true)
            && $sourceTotals === $targetTotals
            && $operationalBefore === $operationalAfter
            && $execution->bundles->count() === $execution->created_count;

        return [
            'schema_version' => 'bpls.historical-financial-preservation-audit.v1',
            'passed' => $passed,
            'execution_id' => $execution->id,
            'run_id' => $execution->run_reference,
            'bundle_count' => $execution->bundles->count(),
            'source_totals' => $sourceTotals,
            'target_totals' => $targetTotals,
            'operational_financial_counts_unchanged' => $operationalBefore === $operationalAfter,
            'bundle_checks' => $checks,
            'safety' => [
                'historical_financial_fact' => true,
                'future_policy_executable' => false,
                'fee_identity_inferred' => false,
                'operational_financial_record' => false,
            ],
        ];
    }

    /** @return array{applications: int, schedules: int, fee_lines: int, payments: int, scheduled_amount_cents: int, fee_amount_cents: int, paid_amount_cents: int, payment_amount_cents: int} */
    private function emptyTotals(): array
    {
        return ['applications' => 0, 'schedules' => 0, 'fee_lines' => 0, 'payments' => 0, 'scheduled_amount_cents' => 0, 'fee_amount_cents' => 0, 'paid_amount_cents' => 0, 'payment_amount_cents' => 0];
    }

    /**
     * @param  array{applications: int, schedules: int, fee_lines: int, payments: int, scheduled_amount_cents: int, fee_amount_cents: int, paid_amount_cents: int, payment_amount_cents: int}  $totals
     * @param  array<string, mixed>  $projection
     * @return array{applications: int, schedules: int, fee_lines: int, payments: int, scheduled_amount_cents: int, fee_amount_cents: int, paid_amount_cents: int, payment_amount_cents: int}
     */
    private function addTotals(array $totals, array $projection): array
    {
        $history = $projection['financial_history'] ?? null;
        $source = is_array($history) ? ($history['totals'] ?? null) : null;
        if (! is_array($source)) {
            throw new RuntimeException('Historical preservation projection totals are missing.');
        }
        $totals['applications']++;
        $totals['schedules'] += $this->exactInteger($source, 'schedule_count');
        $totals['fee_lines'] += $this->exactInteger($source, 'fee_line_count');
        $totals['payments'] += $this->exactInteger($source, 'payment_count');
        $totals['scheduled_amount_cents'] += $this->exactInteger($source, 'scheduled_amount_cents');
        $totals['fee_amount_cents'] += $this->exactInteger($source, 'fee_amount_cents');
        $totals['paid_amount_cents'] += $this->exactInteger($source, 'paid_amount_cents');
        $totals['payment_amount_cents'] += $this->exactInteger($source, 'payment_amount_cents');

        return $totals;
    }

    /** @param array<array-key, mixed> $values */
    private function exactInteger(array $values, string $key): int
    {
        $value = $values[$key] ?? null;
        if (! is_int($value)) {
            throw new RuntimeException("Historical preservation total [{$key}] is not an integer.");
        }

        return $value;
    }
}
