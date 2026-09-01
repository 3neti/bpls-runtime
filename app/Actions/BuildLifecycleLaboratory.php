<?php

namespace App\Actions;

use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\LifecycleScenarios\RenewalHappyPathDefinition;
use App\Models\LifecycleScenarioSpecimen;

final class BuildLifecycleLaboratory
{
    public function __construct(
        private readonly NewApplicationHappyPathDefinition $newApplication,
        private readonly RenewalHappyPathDefinition $renewal,
        private readonly AuthenticateLifecycleScenarioActor $authenticateActor,
    ) {}

    /** @return array<string, mixed> */
    public function handle(): array
    {
        $specimens = LifecycleScenarioSpecimen::query()
            ->with([
                'permitApplication.business.owner',
                'permitApplication.lines',
                'permitApplication.businessPermitEvaluation.items',
                'permitApplication.assessments.decision',
                'permitApplication.assessments.treasuryCounterCheck',
                'permitApplication.paymentSchedules',
            ])
            ->whereIn('scenario_id', ExecutePersistedLifecycleScenario::scenarioIds())
            ->get()
            ->keyBy('scenario_id');

        $newSpecimen = $specimens->get(NewApplicationHappyPathDefinition::Id);
        $renewalSpecimen = $specimens->get(RenewalHappyPathDefinition::Id);

        return [
            'safety' => [
                'classification' => 'Synthetic Stakeholder Preview only',
                'production_available' => false,
                'reset_available' => false,
                'execution_boundary' => 'The browser invokes the same certified scenario runner and canonical Actions as the CLI.',
            ],
            'progress' => [
                'completed_scenarios' => $specimens->count(),
                'total_scenarios' => 2,
                'next_scenario_id' => ! $newSpecimen instanceof LifecycleScenarioSpecimen
                    ? NewApplicationHappyPathDefinition::Id
                    : (! $renewalSpecimen instanceof LifecycleScenarioSpecimen ? RenewalHappyPathDefinition::Id : null),
                'complete' => $newSpecimen instanceof LifecycleScenarioSpecimen && $renewalSpecimen instanceof LifecycleScenarioSpecimen,
            ],
            'scenarios' => [
                $this->scenario($this->newApplication->describe(), $newSpecimen, false),
                $this->scenario($this->renewal->describe(), $renewalSpecimen, true),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function scenario(array $definition, mixed $candidate, bool $renewal): array
    {
        $specimen = $candidate instanceof LifecycleScenarioSpecimen ? $candidate : null;
        $completed = $specimen instanceof LifecycleScenarioSpecimen;
        $application = $specimen?->permitApplication;
        $assessment = $application?->assessments->firstWhere('superseded_at', null);
        $schedule = $application?->paymentSchedules->first();

        return [
            'id' => $definition['id'],
            'label' => $definition['label'],
            'effective_date' => $definition['effective_date'],
            'application_year' => $definition['application_year'],
            'status' => $completed ? 'completed' : 'ready',
            'specimen_id' => $specimen?->id,
            'summary' => $renewal
                ? 'Renewal on the exact Scenario 01 Municipal Owner and Business.'
                : 'Brand-new Citizen establishes the Municipal Owner and first Business.',
            'milestone' => 'Approved payable',
            'events' => $this->events($completed, $renewal),
            'financial_working_paper' => [
                'lines' => [
                    ['label' => 'Retail Trading', 'amount_cents' => 33_000],
                    ['label' => 'Food Service', 'amount_cents' => 54_000],
                    ['label' => 'Business Inspection Fee', 'amount_cents' => 35_000],
                ],
                'total_amount_cents' => $definition['expected']['grand_total_amount_cents'],
                'assessment_total_amount_cents' => $assessment?->total_amount_cents,
                'payable_balance_cents' => $schedule === null ? null : $schedule->total_amount_cents - $schedule->paid_amount_cents,
            ],
            'application' => $application === null ? null : [
                'id' => $application->id,
                'business_id' => $application->business_id,
                'business_name' => $application->business->name,
                'owner_name' => $application->business->owner->name,
                'type' => $application->type->value,
                'status' => $application->status->value,
                'assessment_id' => $assessment?->id,
                'payment_schedule_id' => $schedule?->id,
            ],
            'actors' => $specimen === null ? [] : $this->authenticateActor->entries($specimen),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function events(bool $completed, bool $renewal): array
    {
        $status = $completed ? 'completed' : 'pending';
        $events = $renewal ? [
            ['key' => 'renewal_lodged', 'label' => '2026 Renewal lodged', 'description' => 'Canonical Renewal intake reused the same Municipal Owner and Business.', 'delta' => ['Applications' => '1 → 2', 'Businesses' => '1 → 1']],
        ] : [
            ['key' => 'citizen_created', 'label' => 'Citizen created', 'description' => 'The certified synthetic Citizen actor is established.', 'delta' => ['Scenario Citizens' => '0 → 1']],
            ['key' => 'owner_established', 'label' => 'Municipal Owner established', 'description' => 'Citizen identity links to the legal BusinessOwner registry identity.', 'delta' => ['Municipal Owners' => '0 → 1']],
            ['key' => 'business_created', 'label' => 'Business created', 'description' => 'The first Business is established under that Municipal Owner.', 'delta' => ['Businesses' => '0 → 1']],
            ['key' => 'application_lodged', 'label' => '2025 New Business Permit lodged', 'description' => 'Citizen draft and formal submission cross the canonical intake boundary.', 'delta' => ['Applications' => '0 → 1']],
        ];

        $events = [...$events,
            ['key' => 'lobs_declared', 'label' => 'Business activities declared', 'description' => 'Retail Trading and Food Service are preserved on the application.', 'delta' => ['Business activities' => '0 → 2']],
            ['key' => 'routing_resolved', 'label' => 'Municipal routing resolved', 'description' => 'The scenario definition resolves the concerned offices for the declared activities.', 'delta' => ['Concerned offices' => '0 → 4']],
            ['key' => 'responsibilities_created', 'label' => 'Departmental responsibilities created', 'description' => 'Canonical Evaluation items are created for each required office contribution.', 'delta' => ['Assessor' => '0 → 2', 'Engineering' => '0 → 1', 'Health' => '0 → 2', 'MENRO' => '0 → 1']],
            ['key' => 'responsibilities_resolved', 'label' => 'Departmental work resolved', 'description' => 'Each exact scenario office actor completes only its own canonical responsibilities.', 'delta' => ['Resolved responsibilities' => '0 → 6']],
            ['key' => 'evaluation_ready', 'label' => 'Evaluation ready', 'description' => 'The financial working paper is ready for Assessment.', 'delta' => ['Evaluation' => 'In progress → Ready for Assessment']],
            ['key' => 'assessment_prepared', 'label' => 'Assessment prepared', 'description' => 'Assessment Officer creates the immutable Assessment from the current Evaluation snapshot.', 'delta' => ['Assessment total' => '— → ₱1,220']],
            ['key' => 'treasury_checked', 'label' => 'Treasury counter-check complete', 'description' => 'Treasury records no correction against the exact Assessment and source Evaluation version.', 'delta' => ['Treasury result' => 'Pending → No correction']],
            ['key' => 'treasurer_approved', 'label' => 'Municipal Treasurer exact approval', 'description' => 'A distinct Municipal Treasurer approves the immutable Assessment fingerprint.', 'delta' => ['Assessment decision' => 'Pending → Approved']],
            ['key' => 'payable_created', 'label' => 'Payable created', 'description' => 'The approved Assessment becomes one pending Payment Schedule.', 'delta' => ['Payable balance' => '₱0 → ₱1,220']],
        ];

        return array_values(collect($events)->map(fn (array $event, int $index): array => [
            ...$event,
            'sequence' => $index + 1,
            'status' => $status,
        ])->all());
    }
}
