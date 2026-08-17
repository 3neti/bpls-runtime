<?php

namespace App\Actions;

use App\Models\LegacyFinancialMappingProposal;
use Illuminate\Support\Collection;

class BuildLegacyHistoricalFinancialProposalIndex
{
    /**
     * @param  Collection<int, LegacyFinancialMappingProposal>  $proposals
     * @return array<int, Collection<int, LegacyFinancialMappingProposal>>
     */
    public function handle(Collection $proposals): array
    {
        $bySourceRecord = $proposals->groupBy('legacy_record_id');
        $schedulesByApplication = $proposals
            ->where('kind', 'payment_schedule')
            ->groupBy(fn (LegacyFinancialMappingProposal $proposal): int => (int) ($proposal->metadata['application_source_record_id'] ?? 0));
        $paymentsByApplication = $proposals
            ->where('kind', 'payment')
            ->groupBy(fn (LegacyFinancialMappingProposal $proposal): int => (int) ($proposal->metadata['application_source_record_id'] ?? 0));
        $result = [];

        foreach ($schedulesByApplication as $applicationId => $schedules) {
            if ((int) $applicationId < 1) {
                continue;
            }
            $applicationProposals = collect();
            foreach ($schedules as $schedule) {
                $applicationProposals->push(...($bySourceRecord->get($schedule->legacy_record_id) ?? collect()));
            }
            foreach ($paymentsByApplication->get($applicationId, collect()) as $payment) {
                $applicationProposals->push(...($bySourceRecord->get($payment->legacy_record_id) ?? collect()));
            }
            $result[(int) $applicationId] = $applicationProposals->unique('id')->sortBy('id')->values();
        }

        ksort($result);

        return $result;
    }
}
