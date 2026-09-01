<?php

namespace App\Http\Controllers;

use App\Actions\AuthenticateLifecycleScenarioActor;
use App\Actions\BuildLifecycleLaboratory;
use App\Actions\ExecutePersistedLifecycleScenario;
use App\Http\Requests\RunLifecycleLaboratoryMilestoneRequest;
use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\LifecycleScenarios\RenewalHappyPathDefinition;
use App\LifecycleScenarios\ScenarioArtifactStore;
use App\Models\LifecycleScenarioSpecimen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LifecycleLaboratoryController extends Controller
{
    public function index(BuildLifecycleLaboratory $buildLaboratory): Response
    {
        return Inertia::render('stakeholder-preview/LifecycleLaboratory', [
            'laboratory' => $buildLaboratory->handle(),
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
