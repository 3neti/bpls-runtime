<?php

namespace App\Actions;

use App\Enums\AssessmentDecisionAction;
use App\Evaluation\BusinessPermitEvaluationResolver;
use App\LifecycleScenarios\LifecycleCleanroomApplicationContract;
use App\LifecycleScenarios\LifecycleCleanroomDefinition;
use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\Models\BusinessPermitEvaluation;
use App\Models\LifecycleCleanroomRun;
use App\Models\PermitApplication;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use UnexpectedValueException;

class ResolveLifecycleCleanroomState
{
    public function __construct(
        private readonly LifecycleCleanroomDefinition $definition,
        private readonly LifecycleCleanroomApplicationContract $applicationContract,
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
        $newProfile = $this->profile($newApplication);
        $renewalProfile = $this->profile($renewalApplication);

        $stepDefinitions = collect($this->definition->steps());
        if (($newProfile['scope'] ?? null) === 'single_source_application') {
            $payableIndex = $stepDefinitions->search(fn (array $step): bool => $step['key'] === 'payable_created');
            $stepDefinitions = $stepDefinitions->take(is_int($payableIndex) ? $payableIndex + 1 : 0);
        }

        $steps = $stepDefinitions->map(function (array $step) use ($newApplication, $newProjection, $newProfile, $renewalApplication, $renewalProjection, $renewalProfile): array {
            $application = $step['year'] === 2025 ? $newApplication : $renewalApplication;
            $projection = $step['year'] === 2025 ? $newProjection : $renewalProjection;
            $profile = $step['year'] === 2025 ? $newProfile : $renewalProfile;

            return [
                ...$this->stepPresentation($step, $profile),
                'completed' => $this->stepCompleted($step['key'], $application, $projection, $profile),
                'delta' => $this->delta($step['key'], $application, $profile),
            ];
        })->values();
        $next = $steps->firstWhere('completed', false);
        $completedCount = $steps->where('completed', true)->count();
        $blocker = $this->blocker($next, $newApplication, $renewalApplication);

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
                'blocked' => is_string($blocker),
                'blocker' => $blocker,
                'profile_kind' => $newProfile['kind'] ?? 'pending_intake',
                'profile_statement' => $newProfile['statement'] ?? null,
                'completion_message' => ($newProfile['scope'] ?? null) === 'single_source_application'
                    ? 'The source-backed 2025 registry specimen reached an approved Payable and its Assessment Reconciliation is ready for review.'
                    : 'The 2025 New application and 2026 Renewal both reached approved Payable.',
                'next_step' => $next,
                'percent' => (int) round(($completedCount / max(1, $steps->count())) * 100),
            ],
            'steps' => $steps->map(fn (array $step): array => [
                ...$step,
                'status' => $step['completed'] ? 'completed' : (($next['key'] ?? null) === $step['key'] ? 'current' : 'pending'),
            ])->all(),
            'applications' => [
                'new' => $this->applicationSummary($newApplication, $newProjection, $newProfile),
                'renewal' => $this->applicationSummary($renewalApplication, $renewalProjection, $renewalProfile),
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
    private function profile(?PermitApplication $application): ?array
    {
        if (! $application instanceof PermitApplication) {
            return null;
        }

        try {
            return $this->applicationContract->profile($application);
        } catch (UnexpectedValueException) {
            return null;
        }
    }

    /** @param array<string, mixed>|null $next */
    private function blocker(?array $next, ?PermitApplication $newApplication, ?PermitApplication $renewalApplication): ?string
    {
        return match ($next['key'] ?? null) {
            'evaluation_initialized' => $this->applicationContract->blocker($newApplication),
            'renewal_evaluation_initialized' => $this->applicationContract->blocker($renewalApplication),
            default => null,
        };
    }

    /** @return array<string, mixed>|null */
    private function projection(?PermitApplication $application): ?array
    {
        $evaluation = $application?->businessPermitEvaluation;

        return $evaluation instanceof BusinessPermitEvaluation
            ? $this->evaluationResolver->resolve($evaluation)
            : null;
    }

    /**
     * @param  array<string, mixed>|null  $projection
     * @param  array<string, mixed>|null  $profile
     */
    private function stepCompleted(string $key, ?PermitApplication $application, ?array $projection, ?array $profile): bool
    {
        if ($key === 'cleanroom_started') {
            return true;
        }

        $baseKey = Str::startsWith($key, 'renewal_') ? Str::after($key, 'renewal_') : $key;

        return match ($baseKey) {
            'citizen_intake' => $application instanceof PermitApplication,
            'application_submitted', 'lodged' => $application?->submitted_at !== null,
            'bplo_routing' => $application?->bploRoutingDetermination !== null,
            'evaluation_initialized' => $projection !== null
                && $this->responsibilityItems($projection, $profile)->count() === $this->expectedResponsibilities($profile)->count(),
            'assessor_responsibilities' => $this->officeResolved($projection, $profile, 'assessor'),
            'engineering_responsibility' => $this->officeResolved($projection, $profile, 'engineering'),
            'health_responsibilities' => $this->officeResolved($projection, $profile, 'health'),
            'menro_responsibility' => $this->officeResolved($projection, $profile, 'menro'),
            'assessment_prepared' => $application?->assessments->whereNull('superseded_at')->isNotEmpty() ?? false,
            'treasury_counter_check' => $application?->assessments->whereNull('superseded_at')->first()?->treasuryCounterCheck !== null,
            'treasurer_approved' => $application?->assessments->whereNull('superseded_at')->first()?->decision?->action === AssessmentDecisionAction::Approved,
            'payable_created' => $application?->paymentSchedules->isNotEmpty() ?? false,
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>|null  $projection
     * @param  array<string, mixed>|null  $profile
     */
    private function officeResolved(?array $projection, ?array $profile, string $office): bool
    {
        $expected = $this->expectedResponsibilities($profile)->where('department', $office)->count();
        $items = $this->responsibilityItems($projection, $profile)->where('responsible_party', $office);

        return $items->count() === $expected && $items->every(fn (array $item): bool => $item['resolution'] === 'resolved');
    }

    /**
     * @param  array<string, mixed>|null  $projection
     * @param  array<string, mixed>|null  $profile
     * @return Collection<int, array<string, mixed>>
     */
    private function responsibilityItems(?array $projection, ?array $profile): Collection
    {
        $keys = $this->expectedResponsibilities($profile)->pluck('key');
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
     * @param  array<string, mixed>|null  $profile
     * @return Collection<int, array<string, mixed>>
     */
    private function expectedResponsibilities(?array $profile): Collection
    {
        $responsibilities = $profile['responsibilities'] ?? null;
        if (! is_array($responsibilities)) {
            return collect();
        }
        $normalized = [];
        foreach ($responsibilities as $responsibility) {
            if (! is_array($responsibility)) {
                continue;
            }
            $item = [];
            foreach ($responsibility as $key => $value) {
                if (is_string($key)) {
                    $item[$key] = $value;
                }
            }
            $normalized[] = $item;
        }

        return collect($normalized);
    }

    /**
     * @param  array<string, mixed>|null  $projection
     * @param  array<string, mixed>|null  $profile
     * @return array<string, mixed>|null
     */
    private function applicationSummary(?PermitApplication $application, ?array $projection, ?array $profile): ?array
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
            'target_total_amount_cents' => $profile['expected_total_amount_cents'] ?? NewApplicationHappyPathDefinition::ExpectedGrandTotalCents,
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

    /**
     * @param  array<string, mixed>|null  $profile
     * @return array<string, string>
     */
    private function delta(string $key, ?PermitApplication $application, ?array $profile): array
    {
        $baseKey = Str::startsWith($key, 'renewal_') ? Str::after($key, 'renewal_') : $key;
        $responsibilities = $this->expectedResponsibilities($profile);
        $isRegistryProfile = ($profile['kind'] ?? null) === 'registry_source_replay';

        return match ($baseKey) {
            'cleanroom_started' => ['Cleanroom actors' => '0 → 9'],
            'citizen_intake' => ['Municipal Owners' => '0 → 1', 'Businesses' => '0 → 1', 'Drafts' => '0 → 1', 'Business activities' => '0 → '.($application?->lines->count() ?? 2)],
            'application_submitted', 'lodged' => ['Application' => 'Draft → Lodged'],
            'bplo_routing' => ['BPLO routing determination' => 'Pending → Recorded', 'Concerned offices' => '0 → BPLO selected'],
            'evaluation_initialized' => ['Concerned offices' => '0 → '.$responsibilities->pluck('department')->unique()->count(), 'Responsibilities' => '0 → '.$responsibilities->count()],
            'assessor_responsibilities' => ['Assessor contributions' => '₱0 → '.$this->pesos((int) $responsibilities->where('department', 'assessor')->sum('amount_cents'))],
            'engineering_responsibility' => ['Engineering contributions' => '₱0 → '.$this->pesos((int) $responsibilities->where('department', 'engineering')->sum('amount_cents'))],
            'health_responsibilities' => ['Health contributions' => '₱0 → '.$this->pesos((int) $responsibilities->where('department', 'health')->sum('amount_cents'))],
            'menro_responsibility' => ['MENRO contributions' => '₱0 → '.$this->pesos((int) $responsibilities->where('department', 'menro')->sum('amount_cents'))],
            'assessment_prepared' => ['Assessment total' => $isRegistryProfile ? '— → source-backed instant audit' : '— → ₱1,220'],
            'treasury_counter_check' => ['Treasury result' => 'Pending → No correction'],
            'treasurer_approved' => ['Assessment decision' => 'Pending → Approved'],
            'payable_created' => ['Payable balance' => $isRegistryProfile ? '₱0 → reconciled Assessment' : '₱0 → ₱1,220'],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>|null  $profile
     * @return array<string, mixed>
     */
    private function stepPresentation(array $step, ?array $profile): array
    {
        if (($profile['kind'] ?? null) !== 'registry_source_replay') {
            return $step;
        }

        $responsibilities = $this->expectedResponsibilities($profile);
        $office = match (true) {
            str_contains($step['key'], 'assessor_responsibilities') => 'assessor',
            str_contains($step['key'], 'engineering_responsibility') => 'engineering',
            str_contains($step['key'], 'health_responsibilities') => 'health',
            str_contains($step['key'], 'menro_responsibility') => 'menro',
            default => null,
        };
        if (is_string($office)) {
            $count = $responsibilities->where('department', $office)->count();
            $step['description'] = "The responsible office confirms {$count} checksum-bound source component(s) through the normal Evaluation and Paperless Payment Order form.";
        }
        if ($step['key'] === 'evaluation_initialized') {
            $step['description'] = 'The canonical Evaluation action creates '.$responsibilities->count().' source-backed provisional responsibilities from the immutable specimen and explicit BPLO route.';
        }
        if ($step['key'] === 'assessment_prepared') {
            $step['description'] = 'The Assessment Officer consolidates the confirmed source-backed components through the one canonical Assessment path, then exposes the instant audit comparison.';
        }

        return $step;
    }

    private function pesos(int $amountCents): string
    {
        return number_format($amountCents / 100, $amountCents % 100 === 0 ? 0 : 2);
    }
}
