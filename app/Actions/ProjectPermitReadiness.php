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
            'paymentSchedules.treasuryCollections.receipt.treasuryCollection',
            'assessments.decision',
        ]);

        $requiredOffices = $permitApplication->bploRoutingDetermination?->works
            ->pluck('office_code')->unique()->sort()->values() ?? collect();
        $certifications = $permitApplication->postPaymentOfficeCertifications;
        $certifiedOffices = $certifications->where('status', 'completed')->where('result', 'certified')->pluck('office_code')->unique()->sort()->values();
        $issuedReceipts = $permitApplication->paymentSchedules
            ->flatMap(fn ($schedule) => $schedule->treasuryCollections)
            ->pluck('receipt')
            ->filter(fn ($receipt): bool => $receipt instanceof Receipt && $receipt->status === ReceiptStatus::Issued && filled($receipt->receipt_number))
            ->values();
        $receipt = $issuedReceipts->count() === 1 ? $issuedReceipts->first() : null;
        $assessment = $permitApplication->assessments->whereNull('superseded_at')->sortByDesc('sequence')->first();
        $syntheticAuthority = data_get($permitApplication->metadata, 'lifecycle_cleanroom.semantic_classification') === 'synthetic_only'
            && data_get($permitApplication->metadata, 'lifecycle_cleanroom.production_liability') === false;

        $prerequisites = [
            'application_eligible' => $permitApplication->submitted_at !== null
                && ! $permitApplication->isHistoricalEvidenceOnly()
                && $assessment?->decision?->action === AssessmentDecisionAction::Approved,
            'canonical_collection' => $receipt?->treasuryCollection !== null,
            'issued_official_receipt' => $receipt !== null,
            'official_receipt_number_bound' => filled($receipt?->receipt_number),
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
            'receipt_id' => $receipt?->id,
            'receipt_number' => $receipt?->receipt_number,
            'semantic_classification' => $syntheticAuthority ? 'synthetic_only' : 'production_pending',
            'production_authority' => false,
        ];
    }
}
