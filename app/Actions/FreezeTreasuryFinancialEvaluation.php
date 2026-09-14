<?php

namespace App\Actions;

use App\Assessment\AssessmentPriceInputResolver;
use App\Assessment\Price\Price;
use App\Data\Assessment\AssessmentPriceInput;
use App\Enums\UserPermission;
use App\Evaluation\FrozenFinancialEvaluation;
use App\Models\BusinessPermitEvaluationVersion;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class FreezeTreasuryFinancialEvaluation
{
    public function __construct(
        private readonly AssessmentPriceInputResolver $inputs,
        private readonly FrozenFinancialEvaluation $frozen,
        private readonly BuildConcernedOfficePaymentOrderSummary $orders,
    ) {}

    public function handle(PermitApplication $application, User $actor): BusinessPermitEvaluationVersion
    {
        return DB::transaction(function () use ($application, $actor): BusinessPermitEvaluationVersion {
            $application = PermitApplication::query()->lockForUpdate()->findOrFail($application->id);
            if (! $actor->can(UserPermission::CorrectEvaluationLinesOfBusiness->value)
                || data_get($application->metadata, 'nelson_reconciliation_v1.commissioned_path') !== true) {
                throw new LogicException('Only the commissioned Treasury determination can freeze this financial Evaluation.');
            }
            if ($application->isHistoricalEvidenceOnly() || $application->submitted_at === null
                || $application->assessments()->exists() || $application->paymentSchedules()->exists()) {
                throw new LogicException('Financial Evaluation must be frozen prospectively before any Assessment exists.');
            }
            if ($this->orders->handle($application)['all_finalized'] !== true) {
                throw new LogicException('Financial Evaluation requires all finalized office Payment Orders.');
            }
            $evaluation = $application->businessPermitEvaluation()->firstOrCreate([], ['created_by_id' => $actor->id]);
            $current = $evaluation->currentVersion;
            if (data_get($current?->metadata, 'financial_snapshot.schema') === FrozenFinancialEvaluation::Schema) {
                $this->frozen->read($current);

                return $current;
            }
            $version = $evaluation->versions()->create([
                'sequence' => ($evaluation->versions()->max('sequence') ?? 0) + 1,
                'fingerprint' => str_repeat('0', 64),
                'reason' => 'finalized_office_and_treasury_financial_state',
                'created_by_id' => $actor->id,
            ]);
            $input = $this->inputs->resolve($application, null)->toArray();
            data_set($input, 'assessment_context.evaluation_version_id', $version->id);
            $report = Price::fromInput(AssessmentPriceInput::from($input))->report()->toArray();
            $assignments = $application->treasuryLineOfBusinessAssignments()->whereNull('removed_at')->orderBy('id')->get();
            $snapshot = [
                'schema' => FrozenFinancialEvaluation::Schema,
                'evaluation_id' => $evaluation->id,
                'actor_id' => $actor->id,
                'created_at' => $version->created_at->toIso8601String(),
                'input' => $input,
                'report' => $report,
                'paperless_payment_order_ids' => $application->paperlessPaymentOrders()->where('status', 'issued')->whereNull('superseded_at')->orderBy('id')->pluck('id')->all(),
                'treasury_assignments' => $assignments->map(fn ($assignment): array => [
                    'id' => $assignment->id,
                    'line_of_business_id' => $assignment->line_of_business_id,
                    'enterprise_determination' => data_get($assignment->source_snapshot, 'enterprise_determination'),
                ])->all(),
            ];
            $hash = $this->frozen->hash($snapshot);
            data_set($snapshot, 'input.assessment_context.evaluation_fingerprint', $hash);
            $version->update(['fingerprint' => $hash, 'metadata' => ['financial_snapshot' => $snapshot]]);

            return $version->fresh();
        });
    }
}
