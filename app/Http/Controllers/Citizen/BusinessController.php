<?php

namespace App\Http\Controllers\Citizen;

use App\Actions\BuildCitizenBusinessDetail;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    public function __construct(private readonly BuildCitizenBusinessDetail $buildCitizenBusinessDetail) {}

    public function __invoke(Request $request, int $business): Response
    {
        Gate::authorize(UserPermission::ViewOwnPermitApplications->value);

        $businessDetail = $this->buildCitizenBusinessDetail->handle(
            $request->user(),
            $business,
            $request->user()->can(UserPermission::ViewOwnPermitApplicationFinancials->value),
            $request->user()->can(UserPermission::ViewOwnPermitApplicationDocuments->value),
        );

        abort_if($businessDetail === null, 404);

        return Inertia::render('citizen/businesses/Show', [
            'business' => $businessDetail,
        ]);
    }
}
