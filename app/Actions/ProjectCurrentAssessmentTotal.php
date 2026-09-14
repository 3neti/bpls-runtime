<?php

namespace App\Actions;

use App\Assessment\AssessmentPriceInputResolver;
use App\Assessment\Price\Price;
use App\Models\PermitApplication;

final class ProjectCurrentAssessmentTotal
{
    public function __construct(private readonly AssessmentPriceInputResolver $priceInputResolver) {}

    public function handle(PermitApplication $application): ?int
    {
        if (data_get($application->metadata, 'nelson_reconciliation_v1.commissioned_path') !== true) {
            return null;
        }
        $assessment = $application->assessments()->whereNull('superseded_at')->first();
        if ($assessment !== null) {
            return $assessment->total_amount_cents;
        }
        $version = $application->businessPermitEvaluation?->currentVersion;
        if (data_get($version?->metadata, 'financial_snapshot.schema') !== null) {
            return data_get($version->metadata, 'financial_snapshot.report.total.minor');
        }

        $workIds = $application->bploRoutingDetermination?->works()->pluck('id');
        if ($workIds === null || $workIds->isEmpty()) {
            return null;
        }

        $orderCounts = $application->paperlessPaymentOrders()
            ->whereIn('bplo_routing_work_id', $workIds)
            ->where('status', 'issued')
            ->whereNull('superseded_at')
            ->pluck('bplo_routing_work_id')
            ->countBy();
        if ($workIds->contains(fn (int $workId): bool => $orderCounts->get($workId, 0) !== 1)) {
            return null;
        }

        $hasTreasuryPaymentItems = $application->treasuryLineOfBusinessAssignments()
            ->whereNull('removed_at')
            ->whereHas('items', fn ($query) => $query->whereNull('removed_at'))
            ->exists();
        if (! $hasTreasuryPaymentItems) {
            return null;
        }

        $priceInput = $this->priceInputResolver->resolve($application, null);

        return Price::fromInput($priceInput)->resolve()->totalMinor();
    }
}
