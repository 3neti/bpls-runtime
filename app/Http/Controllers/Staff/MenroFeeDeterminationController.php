<?php

namespace App\Http\Controllers\Staff;

use App\Actions\RecordProvisionalMenroFeeDetermination;
use App\Http\Controllers\Controller;
use App\Models\PermitApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LogicException;

class MenroFeeDeterminationController extends Controller
{
    public function store(
        Request $request,
        PermitApplication $permitApplication,
        RecordProvisionalMenroFeeDetermination $record,
    ): RedirectResponse {
        $facts = $request->validate([
            'scope' => ['required', 'string'],
            'fee_rule_id' => ['required', 'integer'],
            'code' => ['required', 'string'],
            'basis' => ['required', 'string'],
            'application_area_square_meters' => ['required', 'integer'],
            'calculation_basis_centi_square_meters' => ['required', 'integer'],
            'operative_range_min_centi_square_meters' => ['required', 'integer'],
            'operative_range_max_centi_square_meters' => ['required', 'integer'],
            'amount_minor' => ['required', 'integer'],
            'schedule_version' => ['required', 'string'],
            'source_evidence' => ['required', 'string'],
            'classification' => ['required', 'string'],
            'production_authority' => ['required', 'boolean'],
            'reason' => ['required', 'string'],
        ]);

        try {
            $record->handle($permitApplication, $request->user(), $facts);
        } catch (LogicException $exception) {
            return back()->withErrors(['menro_determination' => $exception->getMessage()]);
        }

        return back()->with('success', 'Provisional MENRO determination recorded. No Payment Order was created.');
    }
}
