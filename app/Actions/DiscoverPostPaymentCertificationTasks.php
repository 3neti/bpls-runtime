<?php

namespace App\Actions;

use App\Enums\PermitApplicationStatus;
use App\Models\PermitApplication;
use LogicException;

final class DiscoverPostPaymentCertificationTasks
{
    public function __construct(
        private readonly PostPaymentCertificationEligibility $eligibility,
        private readonly CommissionPostPaymentOfficeCertifications $commission,
    ) {}

    public function forOffice(string $officeCode): void
    {
        PermitApplication::query()
            ->where('status', PermitApplicationStatus::PendingPayment)
            ->whereNotNull('submitted_at')
            ->whereHas('bploRoutingDetermination.works', fn ($query) => $query->where('office_code', $officeCode))
            ->whereDoesntHave('postPaymentOfficeCertifications', fn ($query) => $query->where('office_code', $officeCode))
            ->whereHas('paymentSchedules.treasuryCollections.receipts')
            ->eachById(function (PermitApplication $application): void {
                if (! $this->eligibility->ordinaryUat($application)) {
                    return;
                }

                try {
                    $this->commission->handle($application);
                } catch (LogicException) {
                    // Discovery is fail-closed: incomplete or conflicting evidence
                    // never becomes actionable municipal work.
                }
            });
    }
}
