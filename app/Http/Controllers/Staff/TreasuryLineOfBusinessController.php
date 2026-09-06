<?php

namespace App\Http\Controllers\Staff;

use App\Actions\AssignTreasuryLinesOfBusiness;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\AssignTreasuryLinesOfBusinessRequest;
use App\Models\PermitApplication;
use Illuminate\Http\RedirectResponse;

class TreasuryLineOfBusinessController extends Controller
{
    public function store(
        AssignTreasuryLinesOfBusinessRequest $request,
        PermitApplication $permitApplication,
        AssignTreasuryLinesOfBusiness $assign,
    ): RedirectResponse {
        $assign->handle($permitApplication, $request->validated('selections'), $request->user());

        return back()->with('success', 'Treasury Lines of Business assigned.');
    }
}
