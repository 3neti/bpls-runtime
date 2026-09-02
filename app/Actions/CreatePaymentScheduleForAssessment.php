<?php

namespace App\Actions;

use App\Assessment\AssessmentSnapshotFingerprint;
use App\Enums\AssessmentDecisionAction;
use App\Enums\AssessmentStatus;
use App\Enums\PaymentScheduleLineStatus;
use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationStatus;
use App\Models\Assessment;
use App\Models\AssessmentLine;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class CreatePaymentScheduleForAssessment
{
    public function __construct(
        private readonly AssessmentSnapshotFingerprint $fingerprint,
        private readonly PermitApplicationStatusMutation $statusMutation,
    ) {}

    public function handle(Assessment $assessment, ?User $preparedBy = null): PaymentSchedule
    {
        return DB::transaction(function () use ($assessment, $preparedBy): PaymentSchedule {
            $assessment = Assessment::query()
                ->whereKey($assessment->id)
                ->lockForUpdate()
                ->firstOrFail();
            $assessment->load(['decision', 'permitApplication.business.owner', 'lines.lineOfBusiness']);

            if ($assessment->permitApplication->isHistoricalEvidenceOnly()) {
                throw new LogicException("Historical evidence application [{$assessment->permit_application_id}] cannot receive an operational payment schedule.");
            }

            if ($assessment->status !== AssessmentStatus::Computed) {
                throw new LogicException("Payment schedule cannot be prepared for assessment [{$assessment->id}] with status [{$assessment->status->value}].");
            }

            if ($assessment->superseded_at !== null) {
                throw new LogicException("Payment schedule cannot be prepared for superseded assessment [{$assessment->id}].");
            }

            if ($assessment->decision?->action !== AssessmentDecisionAction::Approved) {
                throw new LogicException("Payment schedule cannot be prepared until assessment [{$assessment->id}] is approved by the Municipal Treasurer.");
            }

            if ($assessment->decision->total_amount_cents !== $assessment->total_amount_cents
                || ! hash_equals($assessment->decision->assessment_snapshot_hash, $this->fingerprint->hash($assessment))) {
                throw new LogicException("Payment schedule cannot be prepared because assessment [{$assessment->id}] no longer matches its Treasurer approval.");
            }

            $existingSchedule = PaymentSchedule::query()
                ->whereBelongsTo($assessment)
                ->with(['lines.lineOfBusiness', 'preparedBy', 'assessment.permitApplication.business.owner'])
                ->first();

            if ($existingSchedule instanceof PaymentSchedule) {
                $this->markPermitApplicationPendingPayment($assessment->permitApplication, $preparedBy);

                return $existingSchedule;
            }

            if ($assessment->permitApplication->status !== PermitApplicationStatus::Approval) {
                throw new LogicException("Payment schedule cannot be prepared because application [{$assessment->permit_application_id}] is not in the approved pre-payment state.");
            }

            $schedule = PaymentSchedule::query()->create([
                'permit_application_id' => $assessment->permit_application_id,
                'assessment_id' => $assessment->id,
                'prepared_by_id' => $preparedBy?->id,
                'sequence' => ($assessment->permitApplication->paymentSchedules()->max('sequence') ?? 0) + 1,
                'status' => PaymentScheduleStatus::Pending,
                'payment_mode' => 'single',
                'due_on' => null,
                'total_amount_cents' => $assessment->total_amount_cents,
                'paid_amount_cents' => 0,
                'source_snapshot' => $this->sourceSnapshot($assessment),
            ]);

            $assessment->lines
                ->sortBy('code')
                ->each(fn (AssessmentLine $line): mixed => $schedule->lines()->create([
                    'assessment_line_id' => $line->id,
                    'permit_application_line_id' => $line->permit_application_line_id,
                    'line_of_business_id' => $line->line_of_business_id,
                    'code' => $line->code,
                    'name' => $line->name,
                    'category' => $line->category,
                    'due_on' => null,
                    'status' => PaymentScheduleLineStatus::Pending,
                    'amount_cents' => $line->amount_cents,
                    'paid_amount_cents' => 0,
                    'source_snapshot' => $this->lineSourceSnapshot($line),
                ]));

            $this->markPermitApplicationPendingPayment($assessment->permitApplication, $preparedBy);

            return $schedule->load(['lines.lineOfBusiness', 'preparedBy', 'assessment.permitApplication.business.owner']);
        });
    }

    private function markPermitApplicationPendingPayment(PermitApplication $permitApplication, ?User $preparedBy): void
    {
        $permitApplication->refresh();

        if ($permitApplication->status === PermitApplicationStatus::PendingPayment) {
            return;
        }

        if (isset($permitApplication->metadata['terminal_state'])) {
            throw new LogicException("Payment schedule cannot advance terminal permit application [{$permitApplication->id}].");
        }

        $metadata = $permitApplication->metadata ?? [];
        $metadata['status_history'] = [
            ...($metadata['status_history'] ?? []),
            [
                'from' => $permitApplication->status->value,
                'to' => PermitApplicationStatus::PendingPayment->value,
                'actor_id' => $preparedBy?->id,
                'reason' => 'Payment schedule prepared from the exact Treasurer-approved assessment snapshot.',
                'occurred_at' => now()->toIso8601String(),
            ],
        ];

        $this->statusMutation->persistStatusConsequence($permitApplication, PermitApplicationStatus::PendingPayment, [
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceSnapshot(Assessment $assessment): array
    {
        return [
            'assessment_id' => $assessment->id,
            'assessment_sequence' => $assessment->sequence,
            'assessment_status' => $assessment->status->value,
            'assessed_at' => $assessment->assessed_at?->toIso8601String(),
            'total_amount_cents' => $assessment->total_amount_cents,
            'treasurer_approval' => [
                'assessment_decision_id' => $assessment->decision?->id,
                'approved_by_id' => $assessment->decision?->decided_by_id,
                'approved_at' => $assessment->decision?->decided_at->toIso8601String(),
                'assessment_snapshot_hash' => $assessment->decision?->assessment_snapshot_hash,
                'approved_total_amount_cents' => $assessment->decision?->total_amount_cents,
            ],
            'permit_application' => [
                'id' => $assessment->permitApplication->id,
                'application_number' => $assessment->permitApplication->application_number,
                'type' => $assessment->permitApplication->type->value,
                'status' => $assessment->permitApplication->status->value,
                'application_year' => $assessment->permitApplication->application_year,
                'business_name' => $assessment->permitApplication->business->name,
                'owner_name' => $assessment->permitApplication->business->owner->name,
            ],
            'policy' => [
                'payment_mode' => 'single',
                'due_on' => null,
                'unresolved_boundaries' => [
                    'installment_due_date_policy',
                    'surcharge_policy',
                    'interest_policy',
                    'presumptive_income_level_policy',
                    'deficiency_tax_policy',
                    'receipt_policy',
                    'reconciliation_policy',
                ],
                'note' => 'Prepared as a full-assessment schedule. Installment, due-date, surcharge, interest, PIL, deficiency tax, receipt, and reconciliation policy remain explicit later decisions.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lineSourceSnapshot(AssessmentLine $line): array
    {
        return [
            'assessment_line_id' => $line->id,
            'fee_rule_id' => $line->fee_rule_id,
            'permit_application_line_id' => $line->permit_application_line_id,
            'line_of_business_id' => $line->line_of_business_id,
            'code' => $line->code,
            'name' => $line->name,
            'category' => $line->category->value,
            'amount_cents' => $line->amount_cents,
            'basis' => $line->basis,
            'basis_amount_cents' => $line->basis_amount_cents,
            'legal_basis' => $line->legal_basis,
            'rule_snapshot' => $line->rule_snapshot,
        ];
    }
}
