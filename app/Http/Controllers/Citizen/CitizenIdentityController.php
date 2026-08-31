<?php

namespace App\Http\Controllers\Citizen;

use App\Actions\BuildCitizenIdentityDetail;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CitizenIdentityController extends Controller
{
    public function __construct(private readonly BuildCitizenIdentityDetail $buildCitizenIdentityDetail) {}

    public function __invoke(Request $request): Response
    {
        Gate::authorize(UserPermission::ViewOwnPermitApplications->value);

        return Inertia::render('citizen/profile/Identity', [
            'identity' => $this->buildCitizenIdentityDetail->handle($request->user()),
        ]);
    }
}
