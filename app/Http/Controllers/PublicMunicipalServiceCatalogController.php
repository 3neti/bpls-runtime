<?php

namespace App\Http\Controllers;

use App\Actions\BuildMunicipalScheduleOfFees;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PublicMunicipalServiceCatalogController extends Controller
{
    public function __invoke(BuildMunicipalScheduleOfFees $buildSchedule): Response
    {
        return Inertia::render('public/ServicesAndFees', [
            'scheduleOfFees' => $buildSchedule->handle(),
        ]);
    }

    public function citizen(): RedirectResponse
    {
        return to_route('services-and-fees.index');
    }
}
