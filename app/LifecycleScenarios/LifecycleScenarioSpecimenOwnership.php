<?php

namespace App\LifecycleScenarios;

use App\Models\Assessment;
use App\Models\AssessmentDecision;
use App\Models\AssessmentLine;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\BusinessPermitEvaluation;
use App\Models\BusinessPermitEvaluationCounterCheck;
use App\Models\BusinessPermitEvaluationItem;
use App\Models\BusinessPermitEvaluationItemRevision;
use App\Models\BusinessPermitEvaluationVersion;
use App\Models\CollectionAllocation;
use App\Models\LifecycleScenarioSpecimen;
use App\Models\OfficeChargeContribution;
use App\Models\PaymentSchedule;
use App\Models\PaymentScheduleLine;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDeclaration;
use App\Models\PermitApplicationLine;
use App\Models\PermitClearance;
use App\Models\ProvisionalUatPermitCompletion;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class LifecycleScenarioSpecimenOwnership
{
    /**
     * @var array<string, class-string<Model>>
     */
    private const array TransactionResources = [
        'business_owner_ids' => BusinessOwner::class,
        'business_ids' => Business::class,
        'permit_application_ids' => PermitApplication::class,
        'permit_application_declaration_ids' => PermitApplicationDeclaration::class,
        'permit_application_line_ids' => PermitApplicationLine::class,
        'permit_clearance_ids' => PermitClearance::class,
        'office_charge_contribution_ids' => OfficeChargeContribution::class,
        'evaluation_ids' => BusinessPermitEvaluation::class,
        'evaluation_version_ids' => BusinessPermitEvaluationVersion::class,
        'evaluation_item_ids' => BusinessPermitEvaluationItem::class,
        'evaluation_revision_ids' => BusinessPermitEvaluationItemRevision::class,
        'treasury_counter_check_ids' => BusinessPermitEvaluationCounterCheck::class,
        'assessment_ids' => Assessment::class,
        'assessment_line_ids' => AssessmentLine::class,
        'assessment_decision_ids' => AssessmentDecision::class,
        'payment_schedule_ids' => PaymentSchedule::class,
        'payment_schedule_line_ids' => PaymentScheduleLine::class,
        'treasury_collection_ids' => TreasuryCollection::class,
        'collection_allocation_ids' => CollectionAllocation::class,
        'receipt_ids' => Receipt::class,
        'provisional_permit_completion_ids' => ProvisionalUatPermitCompletion::class,
    ];

    public function assertTransactionalResidueIsExplicitlyOwned(): void
    {
        $specimens = LifecycleScenarioSpecimen::query()
            ->lockForUpdate()
            ->get(['id', 'scenario_id', 'owned_resource_manifest']);

        foreach (self::TransactionResources as $manifestKey => $modelClass) {
            $claimedIds = [];

            foreach ($specimens as $specimen) {
                foreach ($this->manifestIds($specimen->owned_resource_manifest, $manifestKey) as $id) {
                    if (isset($claimedIds[$id])) {
                        throw new LogicException("Lifecycle specimens [{$claimedIds[$id]}] and [{$specimen->scenario_id}] both claim {$manifestKey} [{$id}].");
                    }

                    $claimedIds[$id] = $specimen->scenario_id;
                }
            }

            $model = new $modelClass;
            $actualIds = $modelClass::query()
                ->pluck($model->getKeyName())
                ->map(fn (mixed $id): string => (string) $id)
                ->sort()
                ->values()
                ->all();
            $ownedIds = collect(array_keys($claimedIds))
                ->map(fn (int|string $id): string => (string) $id)
                ->sort()
                ->values()
                ->all();

            if ($actualIds !== $ownedIds) {
                throw new LogicException("Transactional residue for [{$manifestKey}] is not exactly owned by the persisted lifecycle specimen manifests. Actual [".implode(', ', $actualIds).']; owned ['.implode(', ', $ownedIds).'].');
            }
        }
    }

    /** @param array<string, mixed> $manifest */
    public function assertDisjointFromPersistedSpecimens(array $manifest): void
    {
        $existingClaims = [];

        foreach (LifecycleScenarioSpecimen::query()->lockForUpdate()->get() as $specimen) {
            foreach (array_keys(self::TransactionResources) as $manifestKey) {
                $ids = $specimen->owned_resource_manifest[$manifestKey] ?? [];
                foreach ($this->manifestIds([$manifestKey => $ids], (string) $manifestKey) as $id) {
                    $existingClaims[$manifestKey][$id] = $specimen->scenario_id;
                }
            }
        }

        foreach (array_keys(self::TransactionResources) as $manifestKey) {
            $ids = $manifest[$manifestKey] ?? [];
            foreach ($this->manifestIds([$manifestKey => $ids], (string) $manifestKey) as $id) {
                if (isset($existingClaims[$manifestKey][$id])) {
                    throw new LogicException("New lifecycle specimen would claim {$manifestKey} [{$id}] already owned by [{$existingClaims[$manifestKey][$id]}].");
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<string>
     */
    private function manifestIds(array $manifest, string $key): array
    {
        $ids = $manifest[$key] ?? [];

        if (! is_array($ids)) {
            return [];
        }

        return array_values(collect($ids)
            ->filter(fn (mixed $id): bool => (is_int($id) && $id > 0) || (is_string($id) && $id !== ''))
            ->map(fn (int|string $id): string => (string) $id)
            ->unique()
            ->values()
            ->all());
    }
}
