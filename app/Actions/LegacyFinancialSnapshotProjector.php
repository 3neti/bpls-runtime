<?php

namespace App\Actions;

use App\Enums\AssessmentStatus;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Enums\LegacyFeeRuleReconciliationStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Enums\PaymentScheduleLineStatus;
use App\Enums\PaymentScheduleStatus;
use App\Models\Assessment;
use App\Models\FeeRule;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyFeeRuleReconciliation;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyFinancialMappingProposal;
use App\Models\LegacyRecord;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

class LegacyFinancialSnapshotProjector
{
    /**
     * @param  Collection<int, LegacyFinancialMappingProposal>  $feeProposals
     * @return array{
     *   application_mapping: LegacyApplicationIdMapping,
     *   permit_application: PermitApplication,
     *   assessment: array<string, mixed>,
     *   assessment_lines: list<array<string, mixed>>,
     *   payment_schedule: array<string, mixed>,
     *   payment_schedule_lines: list<array<string, mixed>>
     * }
     */
    public function project(
        LegacyFinancialMappingPlan $plan,
        LegacyFinancialMappingProposal $scheduleProposal,
        Collection $feeProposals,
    ): array {
        $schedule = $scheduleProposal->legacyRecord;

        if ($scheduleProposal->kind !== 'payment_schedule'
            || $scheduleProposal->status !== LegacyMappingProposalStatus::Ready
            || ! $schedule instanceof LegacyRecord) {
            throw new RuntimeException("Financial proposal [{$scheduleProposal->id}] is not a ready payment schedule.");
        }

        $datasets = $this->datasets($plan);
        $payload = $schedule->payload;
        $applicationId = $this->string($payload['applicationId'] ?? null);
        $application = $plan->importBatch->records()
            ->where('dataset_key', $datasets['applications'])
            ->where('legacy_id', $applicationId)
            ->first();

        if (! $application instanceof LegacyRecord) {
            throw new RuntimeException("Legacy schedule proposal [{$scheduleProposal->id}] has no exact application source record.");
        }

        $applicationMapping = LegacyApplicationIdMapping::query()
            ->whereBelongsTo($plan->importBatch, 'importBatch')
            ->where('legacy_source_id', $application->legacy_source_id)
            ->where('dataset_key', $application->dataset_key)
            ->where('legacy_id', $application->legacy_id)
            ->where('status', 'mapped')
            ->first();
        $permitApplication = $applicationMapping?->permitApplication()->first();

        if (! $applicationMapping instanceof LegacyApplicationIdMapping || ! $permitApplication instanceof PermitApplication) {
            throw new RuntimeException("Legacy schedule proposal [{$scheduleProposal->id}] has no accepted permit application mapping.");
        }

        $this->assertAnnualApplication($plan, $application, $schedule, $datasets);
        $scheduleProjection = $this->scheduleProjection($application, $schedule);

        if (! hash_equals($scheduleProposal->projection_hash, $this->hashCanonical($scheduleProjection))) {
            throw new RuntimeException("Financial schedule proposal [{$scheduleProposal->id}] no longer matches its staged projection.");
        }

        $fees = $this->items($payload['fees'] ?? null);
        if ($fees === [] || $feeProposals->count() !== count($fees)) {
            throw new RuntimeException("Legacy schedule [{$schedule->id}] must execute its complete fee proposal set atomically.");
        }

        $assessmentLines = [];
        $scheduleLines = [];
        $feeTotal = 0;

        foreach ($fees as $index => $fee) {
            $proposal = $feeProposals->firstWhere('item_key', "fee:{$index}");
            if (! $proposal instanceof LegacyFinancialMappingProposal
                || $proposal->status !== LegacyMappingProposalStatus::Ready
                || $proposal->kind !== 'payment_schedule_fee') {
                throw new RuntimeException("Legacy schedule [{$schedule->id}] has a missing or non-ready fee proposal at index [{$index}].");
            }

            [$feeRule, $amount] = $this->reconciledFee($proposal, $fee);
            $feeTotal += $amount;
            $assessmentLines[] = [
                'permit_application_line_id' => null,
                'fee_rule_id' => $feeRule->id,
                'line_of_business_id' => null,
                'code' => $feeRule->code,
                'name' => $feeRule->name,
                'category' => $feeRule->category,
                'calculation_type' => $feeRule->calculation_type,
                'basis' => 'historical_persisted_amount',
                'basis_amount_cents' => 0,
                'amount_cents' => $amount,
                'legal_basis' => $feeRule->legal_basis,
                'rule_snapshot' => [
                    'schema_version' => 'bpls.legacy-financial-snapshot-line.v1',
                    'source' => 'persisted_legacy_payment_schedule',
                    'fee_rule_id' => $feeRule->id,
                    'reconciliation_id' => $proposal->legacy_fee_rule_reconciliation_id,
                    'historical_amount_cents' => $amount,
                    'liability_recalculated' => false,
                ],
            ];
            $scheduleLines[] = [
                'assessment_line_index' => $index,
                'permit_application_line_id' => null,
                'line_of_business_id' => null,
                'code' => $feeRule->code,
                'name' => $feeRule->name,
                'category' => $feeRule->category,
                'due_on' => $scheduleProjection['due_on'],
                'status' => PaymentScheduleLineStatus::Pending,
                'amount_cents' => $amount,
                'paid_amount_cents' => 0,
                'source_snapshot' => [
                    'schema_version' => 'bpls.legacy-financial-snapshot-schedule-line.v1',
                    'source' => 'persisted_legacy_payment_schedule',
                    'fee_rule_id' => $feeRule->id,
                    'reconciliation_id' => $proposal->legacy_fee_rule_reconciliation_id,
                    'historical_amount_cents' => $amount,
                    'liability_recalculated' => false,
                ],
            ];
        }

        if ($feeTotal !== $scheduleProjection['total_amount_cents']) {
            throw new RuntimeException("Legacy schedule [{$schedule->id}] fee total no longer matches its persisted total.");
        }

        $assessedAt = $this->dateTime($payload['createdAt'] ?? null);
        $source = [
            'schema_version' => 'bpls.legacy-financial-snapshot.v1',
            'source' => 'persisted_legacy_annual_payment_schedule',
            'legacy_source_id' => $schedule->legacy_id,
            'legacy_record_id' => $schedule->id,
            'application_mapping_id' => $applicationMapping->id,
            'historical_amount_conversion_only' => true,
            'liability_recalculated' => false,
            'payment_status_inferred' => false,
            'collections_created' => false,
            'receipts_created' => false,
        ];

        return [
            'application_mapping' => $applicationMapping,
            'permit_application' => $permitApplication,
            'assessment' => [
                'permit_application_id' => $permitApplication->id,
                'assessed_by_id' => null,
                'sequence' => 1,
                'status' => AssessmentStatus::Computed,
                'assessed_at' => $assessedAt,
                'superseded_at' => null,
                'total_amount_cents' => $feeTotal,
                'source_snapshot' => $source,
                'legacy_source_id' => $schedule->legacy_id,
            ],
            'assessment_lines' => $assessmentLines,
            'payment_schedule' => [
                'permit_application_id' => $permitApplication->id,
                'prepared_by_id' => null,
                'sequence' => 1,
                'status' => PaymentScheduleStatus::Pending,
                'payment_mode' => 'annual',
                'due_on' => $scheduleProjection['due_on'],
                'total_amount_cents' => $feeTotal,
                'paid_amount_cents' => 0,
                'source_snapshot' => $source,
                'legacy_source_id' => $schedule->legacy_id,
            ],
            'payment_schedule_lines' => $scheduleLines,
        ];
    }

    /** @return array<string, mixed> */
    private function scheduleProjection(LegacyRecord $application, LegacyRecord $schedule): array
    {
        $payload = $schedule->payload;
        $section = $payload['sectionNumber'] ?? null;
        $dueOn = $this->date($payload['dueDate'] ?? null);
        $status = $this->string($payload['status'] ?? null);
        $total = $this->money($payload['totalAmount'] ?? null);
        $paid = $this->money($payload['paidAmount'] ?? null);
        $surcharge = $this->money($payload['surcharge'] ?? 0);
        $penalty = $this->money($payload['penalty'] ?? 0);

        if ($section !== 1 || $dueOn === null || $status !== 'pending' || $total === null || $paid !== 0 || $surcharge !== 0 || $penalty !== 0) {
            throw new RuntimeException("Legacy schedule [{$schedule->id}] is not an exact annual single-section unpaid snapshot.");
        }

        return [
            'application_source_record_id' => $application->id,
            'section' => 1,
            'due_on' => $dueOn,
            'status' => 'pending',
            'total_amount_cents' => $total,
            'paid_amount_cents' => 0,
            'surcharge_cents' => 0,
            'penalty_cents' => 0,
        ];
    }

    /** @param array<string, mixed> $fee
     * @return array{FeeRule, int}
     */
    private function reconciledFee(LegacyFinancialMappingProposal $proposal, array $fee): array
    {
        $feeRule = $proposal->feeRule;
        $reconciliation = $proposal->feeReconciliation;
        $original = $this->money($fee['originalAmount'] ?? null);
        $section = $this->money($fee['sectionAmount'] ?? null);
        $category = $this->string($fee['feeCategory'] ?? null);

        if (! $feeRule instanceof FeeRule
            || ! $feeRule->is_active
            || $feeRule->scope !== FeeRuleScope::Application
            || ! $reconciliation instanceof LegacyFeeRuleReconciliation
            || $reconciliation->status !== LegacyFeeRuleReconciliationStatus::Accepted
            || $reconciliation->decision_authority === null
            || $reconciliation->evidence_reference === null
            || ($fee['isEdited'] ?? false) === true
            || $original === null
            || $original !== $section
            || $category !== 'Tax'
            || $feeRule->category !== FeeRuleCategory::Tax) {
            throw new RuntimeException("Financial fee proposal [{$proposal->id}] is not an exact, unedited, application-scoped historical amount.");
        }

        $projection = [
            'fee_rule_id' => $feeRule->id,
            'source_category_sha256' => hash('sha256', $category),
            'original_amount_cents' => $original,
            'section_amount_cents' => $section,
        ];

        if (! hash_equals($proposal->projection_hash, $this->hashCanonical($projection))) {
            throw new RuntimeException("Financial fee proposal [{$proposal->id}] no longer matches its staged projection.");
        }

        return [$feeRule, $section];
    }

    /** @param array{applications: string, schedules: string, payments: string|null} $datasets */
    private function assertAnnualApplication(LegacyFinancialMappingPlan $plan, LegacyRecord $application, LegacyRecord $schedule, array $datasets): void
    {
        if ($this->string($application->payload['modeOfPayment'] ?? null) !== 'Annually') {
            throw new RuntimeException("Legacy application record [{$application->id}] is not explicitly annual.");
        }

        $applicationTotal = $this->money($application->payload['totalFees'] ?? null);
        $scheduleTotal = $this->money($schedule->payload['totalAmount'] ?? null);
        if ($applicationTotal === null || $applicationTotal !== $scheduleTotal) {
            throw new RuntimeException("Legacy application record [{$application->id}] total does not reconcile to its annual schedule.");
        }

        $applicationSchedules = $plan->importBatch->records()
            ->where('dataset_key', $datasets['schedules'])
            ->get()
            ->filter(fn (LegacyRecord $record): bool => $this->string($record->payload['applicationId'] ?? null) === $application->legacy_id);
        if ($applicationSchedules->count() !== 1 || $applicationSchedules->sole()->isNot($schedule)) {
            throw new RuntimeException("Legacy application record [{$application->id}] does not have exactly one annual schedule section.");
        }

        if ($datasets['payments'] !== null) {
            $hasPaymentEvidence = $plan->importBatch->records()
                ->where('dataset_key', $datasets['payments'])
                ->get()
                ->contains(fn (LegacyRecord $record): bool => $this->string($record->payload['applicationId'] ?? null) === $application->legacy_id
                    || $this->string($record->payload['scheduleId'] ?? null) === $schedule->legacy_id);
            if ($hasPaymentEvidence) {
                throw new RuntimeException("Legacy schedule [{$schedule->id}] has payment evidence and cannot use the unpaid snapshot executor.");
            }
        }
    }

    /** @return array{applications: string, schedules: string, payments: string|null} */
    private function datasets(LegacyFinancialMappingPlan $plan): array
    {
        $datasets = $plan->metadata['datasets'] ?? null;
        $applications = is_array($datasets) ? ($datasets['applications'] ?? null) : null;
        $schedules = is_array($datasets) ? ($datasets['schedules'] ?? null) : null;
        $payments = is_array($datasets) ? ($datasets['payments'] ?? null) : null;

        if (! is_string($applications) || ! is_string($schedules) || ($payments !== null && ! is_string($payments))) {
            throw new RuntimeException("Financial mapping plan [{$plan->id}] does not declare exact financial datasets.");
        }

        return compact('applications', 'schedules', 'payments');
    }

    /** @return list<array<string, mixed>> */
    private function items(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        return array_map(fn (mixed $item): array => is_array($item) ? $item : [], $value);
    }

    private function money(mixed $value): ?int
    {
        if (is_float($value)) {
            if (! is_finite($value)) {
                return null;
            }
            $value = rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $normalized = str_replace([',', ' '], '', (string) $value);
        if (preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized) !== 1) {
            return null;
        }
        [$whole, $decimal] = array_pad(explode('.', $normalized, 2), 2, '');
        if (strlen($whole) > 16) {
            return null;
        }

        return ((int) $whole * 100) + (int) str_pad($decimal, 2, '0');
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function dateTime(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->format(DATE_ATOM);
        } catch (Throwable) {
            return null;
        }
    }

    private function string(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }

    /** @param array<array-key, mixed> $value */
    public function hashCanonical(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function targetSnapshotHash(Assessment $assessment, PaymentSchedule $schedule): string
    {
        $assessment->loadMissing('lines');
        $schedule->loadMissing('lines');

        return $this->hashCanonical([
            'assessment' => $assessment->only(['permit_application_id', 'assessed_by_id', 'sequence', 'status', 'assessed_at', 'superseded_at', 'total_amount_cents', 'source_snapshot', 'legacy_source_id']),
            'assessment_lines' => $assessment->lines->sortBy('id')->map->only(['permit_application_line_id', 'fee_rule_id', 'line_of_business_id', 'code', 'name', 'category', 'calculation_type', 'basis', 'basis_amount_cents', 'amount_cents', 'legal_basis', 'rule_snapshot'])->values()->all(),
            'payment_schedule' => $schedule->only(['permit_application_id', 'assessment_id', 'prepared_by_id', 'sequence', 'status', 'payment_mode', 'due_on', 'total_amount_cents', 'paid_amount_cents', 'source_snapshot', 'legacy_source_id']),
            'payment_schedule_lines' => $schedule->lines->sortBy('id')->map->only(['assessment_line_id', 'permit_application_line_id', 'line_of_business_id', 'code', 'name', 'category', 'due_on', 'status', 'amount_cents', 'paid_amount_cents', 'source_snapshot'])->values()->all(),
        ]);
    }

    /** @param array<string, mixed> $projection */
    public function projectionSnapshotHash(array $projection): string
    {
        return $this->hashCanonical([
            'assessment' => $this->normalizedAssessment($projection['assessment']),
            'assessment_lines' => array_map(fn (array $line): array => $this->normalizedAssessmentLine($line), $projection['assessment_lines']),
            'payment_schedule' => $this->normalizedSchedule($projection['payment_schedule']),
            'payment_schedule_lines' => array_map(fn (array $line): array => $this->normalizedScheduleLine($line), $projection['payment_schedule_lines']),
        ]);
    }

    public function targetProjectionHash(Assessment $assessment, PaymentSchedule $schedule): string
    {
        $assessment->loadMissing('lines');
        $schedule->loadMissing('lines');

        return $this->hashCanonical([
            'assessment' => $this->normalizedAssessment($assessment->attributesToArray()),
            'assessment_lines' => $assessment->lines->sortBy('id')->map(fn ($line): array => $this->normalizedAssessmentLine($line->attributesToArray()))->values()->all(),
            'payment_schedule' => $this->normalizedSchedule($schedule->attributesToArray()),
            'payment_schedule_lines' => $schedule->lines->sortBy('id')->map(fn ($line): array => $this->normalizedScheduleLine($line->attributesToArray()))->values()->all(),
        ]);
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function normalizedAssessment(array $attributes): array
    {
        $status = $attributes['status'] ?? null;
        $assessedAt = $attributes['assessed_at'] ?? null;

        return [
            'permit_application_id' => $attributes['permit_application_id'] ?? null,
            'assessed_by_id' => $attributes['assessed_by_id'] ?? null,
            'sequence' => $attributes['sequence'] ?? null,
            'status' => $status instanceof AssessmentStatus ? $status->value : $status,
            'assessed_at' => $assessedAt instanceof \DateTimeInterface ? $assessedAt->format(DATE_ATOM) : $assessedAt,
            'superseded_at' => null,
            'total_amount_cents' => $attributes['total_amount_cents'] ?? null,
            'source_snapshot' => $this->jsonValue($attributes['source_snapshot'] ?? null),
            'legacy_source_id' => $attributes['legacy_source_id'] ?? null,
        ];
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function normalizedAssessmentLine(array $attributes): array
    {
        $category = $attributes['category'] ?? null;
        $calculationType = $attributes['calculation_type'] ?? null;

        return [
            'permit_application_line_id' => $attributes['permit_application_line_id'] ?? null,
            'fee_rule_id' => $attributes['fee_rule_id'] ?? null,
            'line_of_business_id' => $attributes['line_of_business_id'] ?? null,
            'code' => $attributes['code'] ?? null,
            'name' => $attributes['name'] ?? null,
            'category' => $category instanceof FeeRuleCategory ? $category->value : $category,
            'calculation_type' => $calculationType instanceof \BackedEnum ? $calculationType->value : $calculationType,
            'basis' => $attributes['basis'] ?? null,
            'basis_amount_cents' => $attributes['basis_amount_cents'] ?? null,
            'amount_cents' => $attributes['amount_cents'] ?? null,
            'legal_basis' => $attributes['legal_basis'] ?? null,
            'rule_snapshot' => $this->jsonValue($attributes['rule_snapshot'] ?? null),
        ];
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function normalizedSchedule(array $attributes): array
    {
        $status = $attributes['status'] ?? null;
        $dueOn = $attributes['due_on'] ?? null;

        return [
            'permit_application_id' => $attributes['permit_application_id'] ?? null,
            'prepared_by_id' => $attributes['prepared_by_id'] ?? null,
            'sequence' => $attributes['sequence'] ?? null,
            'status' => $status instanceof PaymentScheduleStatus ? $status->value : $status,
            'payment_mode' => $attributes['payment_mode'] ?? null,
            'due_on' => $dueOn instanceof \DateTimeInterface ? $dueOn->format('Y-m-d') : $dueOn,
            'total_amount_cents' => $attributes['total_amount_cents'] ?? null,
            'paid_amount_cents' => $attributes['paid_amount_cents'] ?? null,
            'source_snapshot' => $this->jsonValue($attributes['source_snapshot'] ?? null),
            'legacy_source_id' => $attributes['legacy_source_id'] ?? null,
        ];
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function normalizedScheduleLine(array $attributes): array
    {
        $category = $attributes['category'] ?? null;
        $status = $attributes['status'] ?? null;
        $dueOn = $attributes['due_on'] ?? null;

        return [
            'permit_application_line_id' => $attributes['permit_application_line_id'] ?? null,
            'line_of_business_id' => $attributes['line_of_business_id'] ?? null,
            'code' => $attributes['code'] ?? null,
            'name' => $attributes['name'] ?? null,
            'category' => $category instanceof FeeRuleCategory ? $category->value : $category,
            'due_on' => $dueOn instanceof \DateTimeInterface ? $dueOn->format('Y-m-d') : $dueOn,
            'status' => $status instanceof PaymentScheduleLineStatus ? $status->value : $status,
            'amount_cents' => $attributes['amount_cents'] ?? null,
            'paid_amount_cents' => $attributes['paid_amount_cents'] ?? null,
            'source_snapshot' => $this->jsonValue($attributes['source_snapshot'] ?? null),
        ];
    }

    private function jsonValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $value;
        }
    }
}
