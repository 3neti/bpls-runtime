<?php

namespace App\Actions;

use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationItemType;
use App\Enums\BusinessPermitEvaluationSource;
use App\LifecycleScenarios\LifecycleCleanroomDefinition;
use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\LifecycleScenarios\RenewalHappyPathDefinition;
use App\Models\BusinessPermitEvaluation;
use App\Models\FeeRule;
use App\Models\LifecycleCleanroomRun;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\User;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Support\Facades\DB;
use LogicException;

class AdvanceLifecycleCleanroom
{
    public function __construct(
        private readonly StakeholderPreviewSafety $safety,
        private readonly ResolveLifecycleCleanroomState $resolveState,
        private readonly NewApplicationHappyPathDefinition $definition,
        private readonly InitializeBusinessPermitEvaluation $initializeEvaluation,
        private readonly DefineBusinessPermitEvaluationItem $defineEvaluationItem,
        private readonly CreateRenewalPermitApplicationForExistingBusiness $createRenewal,
    ) {}

    public function handle(LifecycleCleanroomRun $run): LifecycleCleanroomRun
    {
        $this->safety->ensureReady();

        return DB::transaction(function () use ($run): LifecycleCleanroomRun {
            $run = LifecycleCleanroomRun::query()->whereKey($run)->lockForUpdate()->firstOrFail();
            if ($run->status !== 'active') {
                throw new LogicException('Only an active cleanroom can advance.');
            }
            $this->syncDeclarationOwnership($run);
            $next = data_get($this->resolveState->handle($run), 'progress.next_step');
            if (! is_array($next) || $next['mode'] !== 'system_action') {
                throw new LogicException('The next cleanroom step must be completed in its real product form.');
            }
            match ($next['key']) {
                'evaluation_initialized' => $this->initializeResponsibilities($run->newApplication()->sole(), $run, NewApplicationHappyPathDefinition::Id),
                'renewal_lodged' => $this->lodgeRenewal($run),
                'renewal_evaluation_initialized' => $this->initializeResponsibilities($run->renewalApplication()->sole(), $run, RenewalHappyPathDefinition::Id),
                default => throw new LogicException('Unsupported cleanroom system step.'),
            };

            return $run->fresh();
        }, 3);
    }

    private function syncDeclarationOwnership(LifecycleCleanroomRun $run): void
    {
        $declarationIds = PermitApplication::query()
            ->whereIn('id', $run->ownedPermitApplicationIds())
            ->with('declaration')
            ->get()
            ->pluck('declaration.id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->sort()
            ->values()
            ->all();
        $manifest = $run->owned_resource_manifest;
        if (($manifest['permit_application_declaration_ids'] ?? []) === $declarationIds) {
            return;
        }

        $manifest['permit_application_declaration_ids'] = $declarationIds;
        $run->update(['owned_resource_manifest' => $manifest]);
    }

    private function initializeResponsibilities(PermitApplication $application, LifecycleCleanroomRun $run, string $scenarioId): void
    {
        $evaluation = $this->initializeEvaluation->handle($application, $this->actor($run, 'assessment_officer'));
        if ($this->hasDeclaredResponsibilities($evaluation)) {
            return;
        }
        $lines = LineOfBusiness::query()->whereIn('code', collect($this->definition->linesOfBusiness())->pluck('code'))->get()->keyBy('code');
        foreach ($this->definition->responsibilities() as $responsibility) {
            $lineOfBusiness = $lines->get($responsibility['line_of_business_code']);
            if (! $lineOfBusiness instanceof LineOfBusiness) {
                throw new LogicException('The certified cleanroom Line of Business catalog is incomplete.');
            }
            $applicationLine = $application->lines()->where('line_of_business_id', $lineOfBusiness->id)->sole();
            $actor = $this->actor($run, $responsibility['department']);
            $this->defineEvaluationItem->handle(
                $evaluation,
                $responsibility['key'],
                BusinessPermitEvaluationItemType::Charge,
                $responsibility['department'],
                true,
                true,
                BusinessPermitEvaluationApplicability::Applicable,
                ['amount_cents' => $responsibility['amount_cents'], 'inspection' => ['required' => $responsibility['inspection_required'], 'completed' => false]],
                BusinessPermitEvaluationSource::ProvisionalUat,
                $actor,
                $responsibility['reason'],
                [
                    'scenario_id' => $scenarioId,
                    'cleanroom_run_id' => $run->public_id,
                    'semantic_classification' => 'provisional_uat',
                    'production_liability' => false,
                    'authorized_actor_id' => $actor->id,
                    'charge_scope' => 'line_of_business',
                    'line_of_business_id' => $lineOfBusiness->id,
                    'permit_application_line_id' => $applicationLine->id,
                    'code' => $responsibility['code'],
                    'label' => $responsibility['label'],
                    'department_selection_reason' => $responsibility['reason'],
                    'inspection_required' => $responsibility['inspection_required'],
                ],
            );
        }
        foreach ([
            ['key' => 'policy.new-micro-industry-mayors-permit.not-applicable', 'code' => 'MRC-3A-02-NEW-MAYORS-PERMIT-MICRO', 'reason' => 'The cleanroom business is not asserted to be a micro-industry; the unresolved enterprise-scale rule remains outside this provisional UAT payable.'],
            ['key' => 'policy.business-registration-plate.not-applicable', 'code' => 'MRC-3A-05-BUSINESS-REGISTRATION-PLATE', 'reason' => 'The statutory ceiling is not an executable exact charge; the cleanroom records no registration-plate liability and does not infer a price.'],
        ] as $boundary) {
            $feeRule = FeeRule::query()->where('code', $boundary['code'])->sole();
            $this->defineEvaluationItem->handle(
                $evaluation,
                $boundary['key'],
                BusinessPermitEvaluationItemType::Charge,
                'assessor',
                true,
                false,
                BusinessPermitEvaluationApplicability::NotApplicable,
                null,
                BusinessPermitEvaluationSource::ProvisionalUat,
                $this->actor($run, 'assessor'),
                $boundary['reason'],
                ['scenario_id' => $scenarioId, 'cleanroom_run_id' => $run->public_id, 'semantic_classification' => 'provisional_uat', 'production_liability' => false, 'fee_rule_id' => $feeRule->id, 'code' => $boundary['code'], 'label' => $feeRule->name, 'policy_boundary' => true],
            );
        }
    }

    private function lodgeRenewal(LifecycleCleanroomRun $run): void
    {
        if ($run->renewal_application_id !== null) {
            return;
        }
        $new = $run->newApplication()->with(['business', 'lines'])->sole();
        $lines = [];
        foreach ($new->lines as $line) {
            if (! is_int($line->line_of_business_id)) {
                throw new LogicException('A cleanroom Renewal requires every declared activity to retain its catalog identity.');
            }
            $lines[] = [
                'line_of_business_id' => $line->line_of_business_id,
                'declared_gross_sales_cents' => $line->declared_gross_sales_cents,
                'essential_gross_sales_cents' => $line->essential_gross_sales_cents ?? 0,
                'non_essential_gross_sales_cents' => $line->non_essential_gross_sales_cents ?? $line->declared_gross_sales_cents,
                'capital_investment_cents' => $line->capital_investment_cents,
                'quantity' => $line->quantity,
            ];
        }
        $renewal = $this->createRenewal->handle($new->business, RenewalHappyPathDefinition::ApplicationYear, $lines, $this->actor($run, 'intake'));
        $metadata = $renewal->metadata ?? [];
        $metadata['lifecycle_cleanroom'] = [
            'run_id' => $run->public_id,
            'definition_revision' => LifecycleCleanroomDefinition::Revision,
            'scenario_id' => RenewalHappyPathDefinition::Id,
            'semantic_classification' => 'synthetic_only',
            'production_liability' => false,
            'predecessor_permit_application_id' => $new->id,
        ];
        $metadata['business_permit_evaluation'] = ['semantic_classification' => 'provisional_uat', 'scenario_id' => RenewalHappyPathDefinition::Id, 'cleanroom_run_id' => $run->public_id, 'production_liability' => false];
        $renewal->forceFill(['metadata' => $metadata])->save();
        $manifest = $run->owned_resource_manifest;
        $permitApplicationIds = array_values(array_unique([...$run->ownedPermitApplicationIds(), $renewal->id]));
        sort($permitApplicationIds);
        $manifest['permit_application_ids'] = $permitApplicationIds;
        $manifest['permit_application_declaration_ids'] = array_values(array_unique([
            ...($manifest['permit_application_declaration_ids'] ?? []),
            ...$renewal->declaration()->pluck('id')->all(),
        ]));
        $run->update(['renewal_application_id' => $renewal->id, 'owned_resource_manifest' => $manifest]);
    }

    private function actor(LifecycleCleanroomRun $run, string $key): User
    {
        $actor = $run->actor($key);
        if ($actor === null) {
            throw new LogicException("Cleanroom actor [{$key}] is not in the ownership manifest.");
        }

        return User::query()->whereKey($actor['user_id'])->firstOrFail();
    }

    private function hasDeclaredResponsibilities(BusinessPermitEvaluation $evaluation): bool
    {
        return $evaluation->items()->whereIn('key', collect($this->definition->responsibilities())->pluck('key'))->exists();
    }
}
