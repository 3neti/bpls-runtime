<?php

namespace App\Actions;

use App\Enums\AssessmentDecisionAction;
use App\Evaluation\BusinessPermitEvaluationResolver;
use App\LifecycleScenarios\LifecycleCleanroomDefinition;
use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\Models\BusinessPermitEvaluation;
use App\Models\LifecycleCleanroomRun;
use App\Models\PermitApplication;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ResolveLifecycleCleanroomState
{
    public function __construct(
        private readonly LifecycleCleanroomDefinition $definition,
        private readonly NewApplicationHappyPathDefinition $scenarioDefinition,
        private readonly BusinessPermitEvaluationResolver $evaluationResolver,
    ) {}

    /** @return array<string, mixed> */
    public function handle(LifecycleCleanroomRun $run): array
    {
        $run->load([
            'newApplication.business.owner',
            'newApplication.lines.lineOfBusiness',
            'newApplication.businessPermitEvaluation.currentVersion.counterCheck',
            'newApplication.businessPermitEvaluation.items.revisions.version',
            'newApplication.businessPermitEvaluation.items.revisions.actor',
            'newApplication.bploRoutingDetermination',
            'newApplication.assessments.decision',
            'newApplication.assessments.treasuryCounterCheck',
            'newApplication.paymentSchedules',
            'renewalApplication.business.owner',
            'renewalApplication.lines.lineOfBusiness',
            'renewalApplication.businessPermitEvaluation.currentVersion.counterCheck',
            'renewalApplication.businessPermitEvaluation.items.revisions.version',
            'renewalApplication.businessPermitEvaluation.items.revisions.actor',
            'renewalApplication.bploRoutingDetermination',
            'renewalApplication.assessments.decision',
            'renewalApplication.assessments.treasuryCounterCheck',
            'renewalApplication.paymentSchedules',
        ]);
        $newApplication = $run->newApplication;
        $renewalApplication = $run->renewalApplication;
        $newProjection = $this->projection($newApplication);
        $renewalProjection = $this->projection($renewalApplication);

        $steps = collect($this->definition->steps())->map(function (array $step) use ($newApplication, $newProjection, $renewalApplication, $renewalProjection): array {
            $application = $step['year'] === 2025 ? $newApplication : $renewalApplication;
            $projection = $step['year'] === 2025 ? $newProjection : $renewalProjection;

            return [
                ...$step,
                'completed' => $this->stepCompleted($step['key'], $application, $projection),
                'delta' => $this->delta($step['key']),
            ];
        })->values();
        $next = $steps->firstWhere('completed', false);
        $completedCount = $steps->where('completed', true)->count();

        return [
            'run' => [
                'id' => $run->id,
                'public_id' => $run->public_id,
                'status' => $run->status,
                'target_step' => $run->target_step,
                'closed_at' => $run->closed_at?->toIso8601String(),
            ],
            'progress' => [
                'completed_steps' => $completedCount,
                'total_steps' => $steps->count(),
                'complete' => $next === null,
                'next_step' => $next,
                'percent' => (int) round(($completedCount / max(1, $steps->count())) * 100),
            ],
            'steps' => $steps->map(fn (array $step): array => [
                ...$step,
                'status' => $step['completed'] ? 'completed' : (($next['key'] ?? null) === $step['key'] ? 'current' : 'pending'),
            ])->all(),
            'applications' => [
                'new' => $this->applicationSummary($newApplication, $newProjection),
                'renewal' => $this->applicationSummary($renewalApplication, $renewalProjection),
            ],
            'actors' => collect($run->actors())
                ->map(fn (array $actor, string $key): array => ['key' => $key, 'label' => $actor['label']])
                ->values()->all(),
            'safety' => [
                'classification' => 'Synthetic cleanroom only',
                'production_available' => false,
                'reset_available' => false,
                'cleanup_available' => false,
                'execution_boundary' => 'System steps invoke canonical Actions; human steps open the real product form as the exact cleanroom actor.',
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function projection(?PermitApplication $application): ?array
    {
        $evaluation = $application?->businessPermitEvaluation;

        return $evaluation instanceof BusinessPermitEvaluation
            ? $this->evaluationResolver->resolve($evaluation)
            : null;
    }

    /** @param array<string, mixed>|null $projection */
    private function stepCompleted(string $key, ?PermitApplication $application, ?array $projection): bool
    {
        if ($key === 'cleanroom_started') {
            return true;
        }

        $baseKey = Str::startsWith($key, 'renewal_') ? Str::after($key, 'renewal_') : $key;

        return match ($baseKey) {
            'citizen_intake' => $application instanceof PermitApplication,
            'application_submitted', 'lodged' => $application?->submitted_at !== null,
            'bplo_routing' => $application?->bploRoutingDetermination !== null,
            'evaluation_initialized' => $projection !== null && $this->responsibilityItems($projection)->count() === 6,
            'assessor_responsibilities' => $this->officeResolved($projection, 'assessor', 2),
            'engineering_responsibility' => $this->officeResolved($projection, 'engineering', 1),
            'health_responsibilities' => $this->officeResolved($projection, 'health', 2),
            'menro_responsibility' => $this->officeResolved($projection, 'menro', 1),
            'assessment_prepared' => $application?->assessments->whereNull('superseded_at')->isNotEmpty() ?? false,
            'treasury_counter_check' => $application?->assessments->whereNull('superseded_at')->first()?->treasuryCounterCheck !== null,
            'treasurer_approved' => $application?->assessments->whereNull('superseded_at')->first()?->decision?->action === AssessmentDecisionAction::Approved,
            'payable_created' => $application?->paymentSchedules->isNotEmpty() ?? false,
            default => false,
        };
    }

    /** @param array<string, mixed>|null $projection */
    private function officeResolved(?array $projection, string $office, int $expected): bool
    {
        $items = $this->responsibilityItems($projection)->where('responsible_party', $office);

        return $items->count() === $expected && $items->every(fn (array $item): bool => $item['resolution'] === 'resolved');
    }

    /**
     * @param  array<string, mixed>|null  $projection
     * @return Collection<int, array<string, mixed>>
     */
    private function responsibilityItems(?array $projection): Collection
    {
        $keys = collect($this->scenarioDefinition->responsibilities())->pluck('key');
        $rawItems = $projection['items'] ?? null;
        if (! is_array($rawItems)) {
            return collect();
        }
        $items = [];
        foreach ($rawItems as $item) {
            if (is_array($item)) {
                $normalizedItem = [];
                foreach ($item as $key => $value) {
                    if (is_string($key)) {
                        $normalizedItem[$key] = $value;
                    }
                }
                $items[] = $normalizedItem;
            }
        }

        return collect($items)->filter(fn (array $item): bool => $keys->contains($item['key'] ?? null))->values();
    }

    /**
     * @param  array<string, mixed>|null  $projection
     * @return array<string, mixed>|null
     */
    private function applicationSummary(?PermitApplication $application, ?array $projection): ?array
    {
        if (! $application instanceof PermitApplication) {
            return null;
        }

        $workingPaper = $projection['financial_working_paper'] ?? null;

        return [
            'id' => $application->id,
            'year' => $application->application_year,
            'type' => $application->type->value,
            'status' => $application->status->value,
            'submitted' => $application->submitted_at !== null,
            'business_name' => $application->business->name,
            'owner_name' => $application->business->owner->name,
            'evaluation_id' => $application->businessPermitEvaluation?->id,
            'assessment_id' => $application->assessments->whereNull('superseded_at')->first()?->id,
            'payment_schedule_id' => $application->paymentSchedules->first()?->id,
            'working_paper' => $workingPaper,
            'current_total_amount_cents' => $this->workingPaperTotal($workingPaper),
            'target_total_amount_cents' => NewApplicationHappyPathDefinition::ExpectedGrandTotalCents,
        ];
    }

    private function workingPaperTotal(mixed $workingPaper): int
    {
        if (! is_array($workingPaper)) {
            return 0;
        }

        $total = is_int($workingPaper['application_subtotal_amount_cents'] ?? null)
            ? $workingPaper['application_subtotal_amount_cents']
            : 0;
        $sections = $workingPaper['line_sections'] ?? null;
        if (! is_array($sections)) {
            return $total;
        }
        foreach ($sections as $section) {
            if (is_array($section) && is_int($section['subtotal_amount_cents'] ?? null)) {
                $total += $section['subtotal_amount_cents'];
            }
        }

        return $total;
    }

    /** @return array<string, string> */
    private function delta(string $key): array
    {
        $baseKey = Str::startsWith($key, 'renewal_') ? Str::after($key, 'renewal_') : $key;

        return match ($baseKey) {
            'cleanroom_started' => ['Cleanroom actors' => '0 → 9'],
            'citizen_intake' => ['Municipal Owners' => '0 → 1', 'Businesses' => '0 → 1', 'Drafts' => '0 → 1', 'Business activities' => '0 → 2'],
            'application_submitted', 'lodged' => ['Application' => 'Draft → Lodged'],
            'bplo_routing' => ['BPLO routing determination' => 'Pending → Recorded', 'Concerned offices' => '0 → BPLO selected'],
            'evaluation_initialized' => ['Concerned offices' => '0 → 4', 'Responsibilities' => '0 → 6'],
            'assessor_responsibilities' => ['Assessor contributions' => '₱0 → ₱550'],
            'engineering_responsibility' => ['Engineering contributions' => '₱0 → ₱90'],
            'health_responsibilities' => ['Health contributions' => '₱0 → ₱160'],
            'menro_responsibility' => ['MENRO contributions' => '₱0 → ₱70'],
            'assessment_prepared' => ['Assessment total' => '— → ₱1,220'],
            'treasury_counter_check' => ['Treasury result' => 'Pending → No correction'],
            'treasurer_approved' => ['Assessment decision' => 'Pending → Approved'],
            'payable_created' => ['Payable balance' => '₱0 → ₱1,220'],
            default => [],
        };
    }
}
