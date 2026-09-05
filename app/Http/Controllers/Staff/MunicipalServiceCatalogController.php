<?php

namespace App\Http\Controllers\Staff;

use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MunicipalServiceCatalogController extends Controller
{
    public function index(): RedirectResponse
    {
        Gate::authorize(UserPermission::AccessStaff->value);

        return Gate::allows(UserPermission::ViewFeeRules->value)
            ? to_route('staff.fee-rules.index')
            : to_route('dashboard');
    }
}
