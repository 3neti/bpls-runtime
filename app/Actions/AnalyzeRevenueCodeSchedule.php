<?php

namespace App\Actions;

use App\Enums\RevenueCodeProvisionRowStatus;
use App\Enums\RevenueCodeProvisionStatus;
use App\Models\RevenueCodeProvision;
use App\Models\RevenueCodeProvisionRow;

class AnalyzeRevenueCodeSchedule
{
    /**
     * @return array{
     *     summary: array{row_count: int, exact_row_count: int, reconciliation_required_count: int, overlap_count: int, gap_count: int, ceiling_count: int, execution_ready: bool},
     *     rows: array<int, array{id: int, code: string, issues: array<int, array{type: string, related_row_code?: string}>}>
     * }
     */
    public function handle(RevenueCodeProvision $provision): array
    {
        $rows = $provision->rows()->orderBy('sequence')->get();
        $analyzedRows = [];
        $overlapCount = 0;
        $gapCount = 0;

        foreach ($rows as $index => $row) {
            $issues = [];

            if ($row->normalization_status === RevenueCodeProvisionRowStatus::ReconciliationRequired) {
                $issues[] = ['type' => 'normalization_required'];
            }

            if ($row->is_ceiling) {
                $issues[] = ['type' => 'ceiling_not_exact'];
            }

            $previous = $rows->get($index - 1);

            if ($previous instanceof RevenueCodeProvisionRow
                && $previous->basis_below_cents !== null
                && $row->basis_from_cents !== null) {
                if ($row->basis_from_cents < $previous->basis_below_cents) {
                    $issues[] = ['type' => 'overlap', 'related_row_code' => $previous->code];
                    $overlapCount++;
                } elseif ($row->basis_from_cents > $previous->basis_below_cents) {
                    $issues[] = ['type' => 'gap', 'related_row_code' => $previous->code];
                    $gapCount++;
                }
            }

            $analyzedRows[] = [
                'id' => $row->id,
                'code' => $row->code,
                'issues' => $issues,
            ];
        }

        $reconciliationRequiredCount = $rows
            ->where('normalization_status', RevenueCodeProvisionRowStatus::ReconciliationRequired)
            ->count();
        $ceilingCount = $rows->where('is_ceiling', true)->count();

        return [
            'summary' => [
                'row_count' => $rows->count(),
                'exact_row_count' => $rows->count() - $reconciliationRequiredCount,
                'reconciliation_required_count' => $reconciliationRequiredCount,
                'overlap_count' => $overlapCount,
                'gap_count' => $gapCount,
                'ceiling_count' => $ceilingCount,
                'execution_ready' => $rows->isNotEmpty()
                    && $provision->reconciliation_status === RevenueCodeProvisionStatus::Reconciled
                    && $reconciliationRequiredCount === 0
                    && $overlapCount === 0
                    && $gapCount === 0
                    && $ceilingCount === 0,
            ],
            'rows' => $analyzedRows,
        ];
    }
}
