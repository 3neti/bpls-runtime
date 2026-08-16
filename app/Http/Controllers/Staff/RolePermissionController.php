<?php

namespace App\Http\Controllers\Staff;

use App\Actions\BuildRolePermissionMatrix;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RolePermissionController extends Controller
{
    public function index(BuildRolePermissionMatrix $buildRolePermissionMatrix): Response
    {
        Gate::authorize(UserPermission::ViewRoles->value);

        return Inertia::render('roles/Index', $buildRolePermissionMatrix->handle());
    }
}
