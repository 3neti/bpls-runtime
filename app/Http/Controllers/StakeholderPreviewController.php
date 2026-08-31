<?php

namespace App\Http\Controllers;

use App\Actions\AuthenticateStakeholderPreviewPersona;
use App\Actions\AuthenticateStakeholderPreviewSpecimenCitizen;
use App\Enums\StakeholderPreviewPersona;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StakeholderPreviewController extends Controller
{
    public function __construct(private StakeholderPreviewSafety $safety) {}

    public function index(AuthenticateStakeholderPreviewSpecimenCitizen $specimenCitizens): Response
    {
        return Inertia::render('stakeholder-preview/Launcher', [
            'personas' => $this->safety->personas(),
            'citizenSpecimens' => $specimenCitizens->entries(),
        ]);
    }

    public function walkthrough(): Response
    {
        return Inertia::render('stakeholder-preview/Walkthrough', [
            'personas' => $this->safety->personas(),
        ]);
    }

    public function enter(
        Request $request,
        StakeholderPreviewPersona $persona,
        AuthenticateStakeholderPreviewPersona $authenticate,
    ): RedirectResponse {
        $authenticate->handle($request, $persona);

        return to_route('dashboard');
    }

    public function switch(
        Request $request,
        StakeholderPreviewPersona $persona,
        AuthenticateStakeholderPreviewPersona $authenticate,
    ): RedirectResponse {
        $authenticate->handle($request, $persona, requireCurrentPreviewAccount: true);

        return to_route('dashboard');
    }
}
