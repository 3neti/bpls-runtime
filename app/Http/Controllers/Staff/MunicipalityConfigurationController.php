<?php

namespace App\Http\Controllers\Staff;

use App\Actions\BuildMunicipalityConfiguration;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MunicipalityConfigurationController extends Controller
{
    public function index(BuildMunicipalityConfiguration $buildMunicipalityConfiguration): Response
    {
        Gate::authorize(UserPermission::ViewMunicipalityConfiguration->value);

        return Inertia::render(
            'municipality/Index',
            $buildMunicipalityConfiguration->handle(),
        );
    }
}
