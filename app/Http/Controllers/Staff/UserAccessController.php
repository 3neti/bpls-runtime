<?php

namespace App\Http\Controllers\Staff;

use App\Actions\ProvisionLifecycleLaboratoryActors;
use App\Actions\ProvisionStaffUserAccess;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffUserRequest;
use App\Http\Requests\UpdateStaffUserAccessRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class UserAccessController extends Controller
{
    public function store(StoreStaffUserRequest $request, ProvisionStaffUserAccess $provision): RedirectResponse
    {
        Gate::authorize(UserPermission::ProvisionUsers->value);
        $validated = $request->validated();
        $provision->create(
            $request->user(),
            $validated['name'],
            $validated['email'],
            $validated['password'],
            $validated['roles'],
            $validated['reason'],
            isset($validated['access_expires_at']) ? Carbon::parse($validated['access_expires_at']) : null,
        );

        return back()->with('success', 'Staff account provisioned.');
    }

    public function update(UpdateStaffUserAccessRequest $request, User $user, ProvisionStaffUserAccess $provision): RedirectResponse
    {
        Gate::authorize(UserPermission::ManageUserAccess->value);
        $validated = $request->validated();
        $provision->update(
            $request->user(),
            $user,
            $validated['roles'],
            $validated['access_status'],
            $validated['reason'],
            isset($validated['access_expires_at']) ? Carbon::parse($validated['access_expires_at']) : null,
        );

        return back()->with('success', 'Account access updated.');
    }

    public function provisionLaboratory(Request $request, ProvisionLifecycleLaboratoryActors $provision): RedirectResponse
    {
        Gate::authorize(UserPermission::ProvisionLaboratoryActors->value);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $actors = $provision->handle($request->user(), $validated['reason']);

        return back()->with('success', count($actors).' laboratory actors are ready.');
    }
}
