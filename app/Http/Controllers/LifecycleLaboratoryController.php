<?php

namespace App\Http\Controllers;

use App\Actions\AuthenticateLifecycleScenarioActor;
use App\Actions\BuildLifecycleCleanroom;
use App\Actions\BuildLifecycleLaboratory;
use App\Actions\ExecutePersistedLifecycleScenario;
use App\Data\Application\ApplicationDataResolver;
use App\Http\Requests\RunLifecycleLaboratoryMilestoneRequest;
use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\LifecycleScenarios\RenewalHappyPathDefinition;
use App\LifecycleScenarios\ScenarioArtifactStore;
use App\Models\LifecycleScenarioSpecimen;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LifecycleLaboratoryController extends Controller
{
    public function index(
        Request $request,
        BuildLifecycleLaboratory $buildLaboratory,
        BuildLifecycleCleanroom $buildCleanroom,
        StakeholderPreviewSafety $previewSafety,
    ): Response {
        return Inertia::render('stakeholder-preview/LifecycleLaboratory', [
            'laboratory' => $buildLaboratory->handle(),
            'cleanroom' => $buildCleanroom->handle($request->user()),
            'authorizedLegacyReview' => $previewSafety->allowsAuthorizedLegacySpecimens(),
        ]);
    }

    public function runNext(ExecutePersistedLifecycleScenario $execute): RedirectResponse
    {
        $completed = LifecycleScenarioSpecimen::query()
            ->whereIn('scenario_id', ExecutePersistedLifecycleScenario::scenarioIds())
            ->pluck('scenario_id');
        $scenarioId = collect(ExecutePersistedLifecycleScenario::scenarioIds())->first(
            fn (string $candidate): bool => ! $completed->contains($candidate),
        );

        if (is_string($scenarioId)) {
            $this->executeAndRecord($execute, $scenarioId);
        }

        return to_route('stakeholder-preview.lifecycle-laboratory.index')
            ->with('success', is_string($scenarioId) ? 'Certified milestone completed.' : 'The two-year chronology is already complete.');
    }

    public function runToMilestone(
        RunLifecycleLaboratoryMilestoneRequest $request,
        ExecutePersistedLifecycleScenario $execute,
    ): RedirectResponse {
        $target = $request->string('scenario_id')->toString();
        $scenarioIds = $target === RenewalHappyPathDefinition::Id
            ? [NewApplicationHappyPathDefinition::Id, RenewalHappyPathDefinition::Id]
            : [NewApplicationHappyPathDefinition::Id];

        foreach ($scenarioIds as $scenarioId) {
            $this->executeAndRecord($execute, $scenarioId);
        }

        return to_route('stakeholder-preview.lifecycle-laboratory.index')
            ->with('success', 'Certified milestone completed through '.$target.'.');
    }

    public function enterActor(
        Request $request,
        LifecycleScenarioSpecimen $lifecycleScenarioSpecimen,
        string $actor,
        AuthenticateLifecycleScenarioActor $authenticate,
    ): RedirectResponse {
        return redirect()->to($authenticate->handle($request, $lifecycleScenarioSpecimen, $actor));
    }

    public function showApplication(
        Request $request,
        LifecycleScenarioSpecimen $lifecycleScenarioSpecimen,
        ApplicationDataResolver $resolver,
    ): Response {
        $manifest = $lifecycleScenarioSpecimen->owned_resource_manifest;
        $actorIdPayload = data_get($manifest, 'actor_user_ids', []);
        $actorIds = is_array($actorIdPayload)
            ? array_values(array_filter($actorIdPayload, fn (mixed $id): bool => is_int($id)))
            : [];
        abort_unless(
            data_get($manifest, 'semantic_classification') === 'synthetic_only'
            && data_get($manifest, 'production_liability') === false
            && in_array($request->user()?->id, $actorIds, true),
            404,
        );

        $application = $lifecycleScenarioSpecimen->permitApplication()->firstOrFail();

        return Inertia::render('stakeholder-preview/LifecycleApplication', [
            'application' => $resolver->resolve($application, $request->user())->toArray(),
            'focus' => $request->string('focus')->toString(),
            'scenario' => [
                'id' => $lifecycleScenarioSpecimen->scenario_id,
                'revision' => $lifecycleScenarioSpecimen->scenario_revision,
            ],
        ]);
    }

    private function executeAndRecord(ExecutePersistedLifecycleScenario $execute, string $scenarioId): void
    {
        $result = $execute->handle($scenarioId);
        $runId = $scenarioId === NewApplicationHappyPathDefinition::Id
            ? NewApplicationHappyPathDefinition::RunId
            : RenewalHappyPathDefinition::RunId;
        $artifactStore = new ScenarioArtifactStore($scenarioId, $runId);
        $artifactStore->putJson('result.json', $result);
        $artifactStore->putJson('action-trace.json', ['actions' => $result['action_trace']]);
    }
}
