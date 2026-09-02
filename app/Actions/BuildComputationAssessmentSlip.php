<?php

namespace App\Actions;

use App\Assessment\AssessmentSnapshotFingerprint;
use App\Enums\AssessmentDecisionAction;
use App\Models\Assessment;
use App\Models\AssessmentLine;
use Illuminate\Support\Number;

class BuildComputationAssessmentSlip
{
    public function __construct(private readonly AssessmentSnapshotFingerprint $fingerprint) {}

    /** @return array<string, mixed> */
    public function handle(Assessment $assessment): array
    {
        $assessment->loadMissing([
            'assessedBy',
            'decision.decidedBy.role',
            'permitApplication.declaration',
            'permitApplication.business.owner',
            'permitApplication.lines.lineOfBusiness',
            'lines.lineOfBusiness',
            'lines.paperlessPaymentOrderLine.paymentOrder.routingWork',
            'paymentSchedules.lines',
        ]);

        $application = $assessment->permitApplication;
        $resolvedLineIds = data_get($assessment->source_snapshot, 'business_permit_evaluation.resolved_line_of_business_ids', []);
        $lineOrder = collect(is_array($resolvedLineIds) ? $resolvedLineIds : [])
            ->merge($application->lines->pluck('line_of_business_id'))
            ->merge($assessment->lines->pluck('line_of_business_id')->filter())
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $sections = $lineOrder->map(function (int $lineOfBusinessId) use ($assessment): ?array {
            $lines = $assessment->lines->where('line_of_business_id', $lineOfBusinessId)->sortBy('id')->values();

            return $lines->isEmpty() ? null : [
                'line_of_business_id' => $lineOfBusinessId,
                'line_of_business_name' => $lines->first()->lineOfBusiness?->name,
                'charges' => $lines->map(fn (AssessmentLine $line): array => $this->line($line))->all(),
                'subtotal_amount_cents' => (int) $lines->sum('amount_cents'),
            ];
        })->filter()->values();
        $applicationLines = $assessment->lines->whereNull('line_of_business_id')->sortBy('id')->values();
        $groupedTotal = (int) $sections->sum('subtotal_amount_cents') + (int) $applicationLines->sum('amount_cents');
        $schedule = $assessment->paymentSchedules()->exists()
            ? $assessment->paymentSchedules()->latest('sequence')->firstOrFail()
            : null;
        $paymentMode = $schedule === null
            ? data_get($application->declaration?->snapshot, 'application.mode_of_payment', 'not yet selected')
            : $schedule->payment_mode;

        return [
            'institution' => [
                'country' => 'Republic of the Philippines',
                'province' => 'Province of Zamboanga Sibugay',
                'municipality' => 'Municipality of Ipil',
                'title' => 'Computation/Assessment Slip',
            ],
            'reference' => [
                'assessment_id' => $assessment->id,
                'assessment_sequence' => $assessment->sequence,
                'official_number' => null,
                'official_number_status' => 'not_assigned_or_authorized',
                'snapshot_hash' => $this->fingerprint->hash($assessment),
            ],
            'transaction_type' => $application->type->value,
            'owner_proprietor' => $application->business->owner->name,
            'business_name' => $application->business->name,
            'business_address' => $application->business->address,
            'payment_mode' => $paymentMode,
            'line_of_businesses' => $application->lines->map(fn ($line): array => [
                'id' => $line->line_of_business_id,
                'code' => $line->lineOfBusiness?->code,
                'name' => $line->lineOfBusiness?->name,
            ])->values()->all(),
            'line_sections' => $sections->all(),
            'application_charges' => $applicationLines->map(fn (AssessmentLine $line): array => $this->line($line))->all(),
            'application_subtotal_amount_cents' => (int) $applicationLines->sum('amount_cents'),
            'grand_total_amount_cents' => $assessment->total_amount_cents,
            'grouped_total_amount_cents' => $groupedTotal,
            'reconciles' => $groupedTotal === $assessment->total_amount_cents,
            'in_words' => $this->inWords($assessment->total_amount_cents),
            'schedule_of_payments' => [
                'payment_mode' => $paymentMode,
                'allocation_status' => 'blocked_municipal_fiscal_decision',
                'allocation_note' => 'Quarterly schedule existence is accepted operational evidence; Q1-Q4 allocation is not computed without a municipal fiscal decision.',
                'quarters' => collect(['Q1', 'Q2', 'Q3', 'Q4'])->map(fn (string $quarter): array => [
                    'section' => $quarter,
                    'due_date' => null,
                    'amount_cents' => null,
                    'balance_cents' => null,
                ])->all(),
                'canonical_single_schedule' => $schedule === null ? null : [
                    'id' => $schedule->id,
                    'mode' => $schedule->payment_mode,
                    'total_amount_cents' => $schedule->total_amount_cents,
                    'paid_amount_cents' => $schedule->paid_amount_cents,
                ],
            ],
            'prepared_by' => [
                'name' => $assessment->assessedBy?->name,
                'prepared_at' => $assessment->assessed_at?->toIso8601String(),
                'role' => 'Assessment Officer',
            ],
            'approved_by' => $assessment->decision?->action !== AssessmentDecisionAction::Approved ? null : [
                'name' => $assessment->decision->decidedBy?->name,
                'approved_at' => $assessment->decision->decided_at->toIso8601String(),
                'role' => 'Municipal Treasurer',
                'snapshot_hash' => $assessment->decision->assessment_snapshot_hash,
            ],
            'acknowledged_by' => null,
            'acknowledgement_note' => 'No canonical acknowledgement fact is implemented in this slice.',
        ];
    }

    /** @return array<string, mixed> */
    private function line(AssessmentLine $line): array
    {
        $orderLine = $line->paperlessPaymentOrderLine;
        $order = $orderLine?->paymentOrder;

        return [
            'assessment_line_id' => $line->id,
            'code' => $line->code,
            'name' => $line->name,
            'amount_cents' => $line->amount_cents,
            'line_of_business_id' => $line->line_of_business_id,
            'source_type' => $orderLine === null ? 'governed_canonical_pricing' : 'paperless_payment_order',
            'paperless_payment_order' => $orderLine === null ? null : [
                'id' => $order?->id,
                'sequence' => $order?->sequence,
                'office_code' => $order?->routingWork?->office_code,
                'office_label' => $order?->routingWork?->office_label,
                'issued_at' => $order?->issued_at?->toIso8601String(),
            ],
        ];
    }

    private function inWords(int $amountCents): ?string
    {
        if ($amountCents % 100 !== 0) {
            return null;
        }

        $words = Number::spell(intdiv($amountCents, 100), locale: 'en');

        return $words === false ? null : str($words)->title().' Pesos';
    }
}
