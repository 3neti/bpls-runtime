<?php

namespace App\Http\Controllers\Staff;

use App\Actions\RecordBploRoutingDetermination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\RecordBploRoutingDeterminationRequest;
use App\Models\PermitApplication;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use LogicException;

class BploRoutingDeterminationController extends Controller
{
    public function store(
        RecordBploRoutingDeterminationRequest $request,
        PermitApplication $permitApplication,
        RecordBploRoutingDetermination $recordRouting,
    ): RedirectResponse {
        try {
            $recordRouting->handle(
                $permitApplication,
                $request->user(),
                $request->validated('situational_context') ?? 'Concerned offices selected by BPLO checklist.',
                $request->validated('selected_work'),
            );
        } catch (LogicException $exception) {
            return back()->withErrors(['routing' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Concerned-office routing recorded. The office Payment Orders are ready.',
        ]);

        if (data_get($permitApplication->metadata, 'lifecycle_cleanroom.semantic_classification') === 'synthetic_only'
            && data_get($permitApplication->metadata, 'lifecycle_cleanroom.production_liability') === false) {
            return redirect()->route('staff.work.index');
        }

        return redirect()->route('staff.permit-applications.evaluation.show', $permitApplication);
    }
}
