<?php

namespace App\Http\Controllers\Citizen;

use App\Actions\BuildCitizenProfile;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(private readonly BuildCitizenProfile $buildCitizenProfile) {}

    public function __invoke(Request $request): Response
    {
        Gate::authorize(UserPermission::ViewOwnPermitApplications->value);

        return Inertia::render('citizen/profile/Show', [
            'profile' => $this->buildCitizenProfile->handle(
                $request->user(),
                $request->user()->can(UserPermission::ViewOwnPermitApplicationFinancials->value),
            ),
        ]);
    }
}
