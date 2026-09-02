<?php

namespace App\Http\Controllers\Staff;

use App\Actions\RecordBploRoutingDetermination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\RecordBploRoutingDeterminationRequest;
use App\Models\PermitApplication;
use Illuminate\Http\RedirectResponse;
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
                $request->validated('situational_context'),
                $request->validated('selected_work'),
            );
        } catch (LogicException $exception) {
            return back()->withErrors(['routing' => $exception->getMessage()]);
        }

        return back();
    }
}
