<?php

namespace App\Http\Controllers;

use App\Actions\AuthenticateStakeholderPreviewSpecimenCitizen;
use App\Models\LifecycleScenarioSpecimen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StakeholderPreviewSpecimenController extends Controller
{
    public function __invoke(
        Request $request,
        LifecycleScenarioSpecimen $lifecycleScenarioSpecimen,
        AuthenticateStakeholderPreviewSpecimenCitizen $authenticate,
    ): RedirectResponse {
        $authenticate->handle($request, $lifecycleScenarioSpecimen);

        return to_route('citizen.profile.show');
    }
}
