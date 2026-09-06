<?php

namespace App\Actions;

use App\Enums\AssessmentDecisionAction;
use App\Enums\ReceiptStatus;
use App\Models\PermitApplication;
use App\Models\Receipt;

class ProjectPermitReadiness
{
    /** @return array<string, mixed> */
    public function handle(PermitApplication $permitApplication): array
    {
        $permitApplication->loadMissing([
            'bploRoutingDetermination.works',
            'postPaymentOfficeCertifications',
            'paymentSchedules.treasuryCollections.allocations',
            'paymentSchedules.treasuryCollections.receipts.treasuryCollection',
            'assessments.decision',
        ]);

        $requiredOffices = $permitApplication->bploRoutingDetermination?->works
            ->pluck('office_code')->unique()->sort()->values() ?? collect();
        $certifications = $permitApplication->postPaymentOfficeCertifications;
        $certifiedOffices = $certifications->where('status', 'completed')->where('result', 'certified')->pluck('office_code')->unique()->sort()->values();
        $collections = $permitApplication->paymentSchedules
            ->flatMap(fn ($schedule) => $schedule->treasuryCollections)
            ->sortByDesc('id')->values();
        $collection = $collections->first();
        $requiredReceiptGroups = $collection?->allocations->pluck('receipt_group_key')->unique()->sort()->values() ?? collect();
        if ($collection !== null && $requiredReceiptGroups->isEmpty()) {
            $requiredReceiptGroups = collect(['municipal_consolidated']);
        }
        $issuedReceipts = $collection?->receipts
            ->filter(fn (Receipt $receipt): bool => $receipt->status === ReceiptStatus::Issued && filled($receipt->receipt_number))
            ->values() ?? collect();
        $issuedReceiptGroups = $issuedReceipts->pluck('receipt_group_key')->unique()->sort()->values();
        $receiptTotal = (int) $issuedReceipts->sum('amount_cents');
        $completeReceiptCoverage = $collection !== null
            && $requiredReceiptGroups->isNotEmpty()
            && $requiredReceiptGroups->diff($issuedReceiptGroups)->isEmpty()
            && $issuedReceiptGroups->diff($requiredReceiptGroups)->isEmpty()
            && $receiptTotal === $collection->amount_cents;
        $assessment = $permitApplication->assessments->whereNull('superseded_at')->sortByDesc('sequence')->first();
        $syntheticAuthority = data_get($permitApplication->metadata, 'lifecycle_cleanroom.semantic_classification') === 'synthetic_only'
            && data_get($permitApplication->metadata, 'lifecycle_cleanroom.production_liability') === false;

        $prerequisites = [
            'application_eligible' => $permitApplication->submitted_at !== null
                && ! $permitApplication->isHistoricalEvidenceOnly()
                && $assessment?->decision?->action === AssessmentDecisionAction::Approved,
            'canonical_collection' => $collection !== null,
            'issued_official_receipts' => $issuedReceipts->isNotEmpty(),
            'issued_official_receipt' => $issuedReceipts->isNotEmpty(),
            'complete_receipt_coverage' => $completeReceiptCoverage,
            'official_receipt_totals_reconcile' => $collection !== null && $receiptTotal === $collection->amount_cents,
            'post_payment_certifications_commissioned' => $requiredOffices->isNotEmpty()
                && $certifications->count() === $requiredOffices->count(),
            'all_required_post_payment_certifications' => $requiredOffices->isNotEmpty()
                && $requiredOffices->every(fn (string $office): bool => $certifiedOffices->contains($office)),
            'synthetic_issuance_authority_available' => $syntheticAuthority,
        ];
        $ready = collect($prerequisites)->every(fn (bool $passed): bool => $passed);

        return [
            'schema_version' => 'bpls.permit-readiness.v1',
            'ready' => $ready,
            'state' => $ready ? 'ready' : 'blocked',
            'prerequisites' => $prerequisites,
            'blocked_by' => array_keys(array_filter($prerequisites, fn (bool $passed): bool => ! $passed)),
            'required_offices' => $requiredOffices->all(),
            'certified_offices' => $certifiedOffices->all(),
            'required_receipt_groups' => $requiredReceiptGroups->all(),
            'issued_receipt_groups' => $issuedReceiptGroups->all(),
            'receipt_total_cents' => $receiptTotal,
            'collection_total_cents' => $collection?->amount_cents,
            'receipt_ids' => $issuedReceipts->pluck('id')->all(),
            'receipt_numbers' => $issuedReceipts->pluck('receipt_number')->all(),
            // Compatibility aliases for the pre-Nelson single-receipt projection.
            'receipt_id' => $issuedReceipts->count() === 1 ? $issuedReceipts->first()?->id : null,
            'receipt_number' => $issuedReceipts->count() === 1 ? $issuedReceipts->first()?->receipt_number : null,
            'semantic_classification' => $syntheticAuthority ? 'synthetic_only' : 'production_pending',
            'production_authority' => false,
        ];
    }
}
