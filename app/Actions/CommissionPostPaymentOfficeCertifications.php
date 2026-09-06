<?php

namespace App\Actions;

use App\Enums\ReceiptStatus;
use App\Models\PermitApplication;
use App\Models\PostPaymentOfficeCertification;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;
use LogicException;

class CommissionPostPaymentOfficeCertifications
{
    /** @return list<PostPaymentOfficeCertification> */
    public function handle(PermitApplication $permitApplication): array
    {
        return DB::transaction(function () use ($permitApplication): array {
            $application = PermitApplication::query()->whereKey($permitApplication)->lockForUpdate()->firstOrFail();
            $application->load(['bploRoutingDetermination.works', 'paymentSchedules.treasuryCollections.allocations', 'paymentSchedules.treasuryCollections.receipts']);

            if (data_get($application->metadata, 'lifecycle_cleanroom.semantic_classification') !== 'synthetic_only'
                || data_get($application->metadata, 'lifecycle_cleanroom.production_liability') !== false) {
                throw new LogicException('Post-payment certification commissioning is available only for an explicitly synthetic cleanroom Application.');
            }

            $routing = $application->bploRoutingDetermination;
            if ($routing === null || $routing->works->isEmpty()) {
                throw new LogicException('Post-payment offices must come from the Application actual BPLO routing.');
            }

            $collections = $application->paymentSchedules->flatMap(fn ($schedule) => $schedule->treasuryCollections);
            $receipts = $collections
                ->flatMap(fn ($collection) => $collection->receipts)
                ->filter(fn (Receipt $receipt): bool => $receipt->status === ReceiptStatus::Issued && filled($receipt->receipt_number))
                ->values();
            $requiredGroups = $collections
                ->flatMap(fn ($collection) => $collection->allocations)
                ->pluck('receipt_group_key')
                ->filter()
                ->unique()
                ->values();
            if ($receipts->isEmpty()
                || ($requiredGroups->isNotEmpty() && $requiredGroups->diff($receipts->pluck('receipt_group_key'))->isNotEmpty())
                || $receipts->sum('amount_cents') !== $collections->sum('amount_cents')) {
                throw new LogicException('Complete reconciled canonical Official Receipt coverage is required before post-payment office certification.');
            }

            $records = [];
            foreach ($routing->works->groupBy('office_code') as $officeCode => $works) {
                $receipt = $receipts->firstWhere('receipt_group_key', 'office:'.$officeCode)
                    ?? ($receipts->count() === 1 ? $receipts->sole() : null);
                if (! $receipt instanceof Receipt) {
                    throw new LogicException("The routed {$officeCode} office has no canonical Official Receipt group to certify.");
                }
                $workIds = $works->pluck('id')->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();
                $records[] = PostPaymentOfficeCertification::query()->firstOrCreate(
                    ['permit_application_id' => $application->id, 'office_code' => $officeCode],
                    [
                        'bplo_routing_determination_id' => $routing->id,
                        'receipt_id' => $receipt->id,
                        'office_label' => (string) $works->first()->office_label,
                        'routing_work_ids' => $workIds,
                        'status' => 'pending',
                        'evidence' => [
                            'source' => 'lifecycle_cleanroom_post_payment_commission',
                            'receipt_review_required' => true,
                            'receipt_number' => $receipt->receipt_number,
                            'receipt_group_key' => $receipt->receipt_group_key,
                            'routing_determination_id' => $routing->id,
                            'routing_work_ids' => $workIds,
                            'exact_per_office_production_semantics' => 'unresolved',
                            'real_office_certification' => false,
                        ],
                        'semantic_classification' => 'synthetic_only',
                        'production_authority' => false,
                    ],
                );
            }

            return $records;
        }, 3);
    }
}
