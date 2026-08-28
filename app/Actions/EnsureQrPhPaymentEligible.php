<?php

namespace App\Actions;

use App\Assessment\AssessmentSnapshotFingerprint;
use App\Enums\AssessmentDecisionAction;
use App\Enums\AssessmentStatus;
use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationStatus;
use App\Models\PaymentSchedule;
use LogicException;

final class EnsureQrPhPaymentEligible
{
    public function __construct(private readonly AssessmentSnapshotFingerprint $fingerprint) {}

    public function handle(PaymentSchedule $paymentSchedule): PaymentSchedule
    {
        $paymentSchedule->loadMissing(['assessment.decision', 'assessment.lines', 'permitApplication']);
        $assessment = $paymentSchedule->assessment;

        if ($paymentSchedule->status !== PaymentScheduleStatus::Pending
            || $paymentSchedule->paid_amount_cents !== 0
            || $paymentSchedule->total_amount_cents <= 0) {
            throw new LogicException('QR Ph is available only for the full unpaid approved schedule.');
        }

        if ($assessment->status !== AssessmentStatus::Computed || $assessment->superseded_at !== null) {
            throw new LogicException('QR Ph is unavailable because the assessment is no longer current.');
        }

        if ($assessment->decision?->action !== AssessmentDecisionAction::Approved
            || $assessment->decision->total_amount_cents !== $assessment->total_amount_cents
            || ! hash_equals($assessment->decision->assessment_snapshot_hash, $this->fingerprint->hash($assessment))) {
            throw new LogicException('QR Ph requires the exact current Municipal Treasurer approval.');
        }

        if ($paymentSchedule->assessment_id !== $assessment->id
            || $paymentSchedule->total_amount_cents !== $assessment->total_amount_cents
            || $paymentSchedule->permitApplication->status !== PermitApplicationStatus::PendingPayment) {
            throw new LogicException('QR Ph requires the exact approved BPLS payment obligation.');
        }

        return $paymentSchedule;
    }
}
