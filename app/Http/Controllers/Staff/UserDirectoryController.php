<?php

namespace App\Http\Controllers\Staff;

use App\Actions\BuildUserDirectory;
use App\Enums\StakeholderPreviewPersona;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserDirectoryController extends Controller
{
    public function index(Request $request, BuildUserDirectory $buildUserDirectory, StakeholderPreviewSafety $previewSafety): Response
    {
        Gate::authorize(UserPermission::ViewUsers->value);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'string', 'max:80'],
        ]);

        $engineeringReviewer = $previewSafety->personaFor($request->user()) === StakeholderPreviewPersona::Management;

        return Inertia::render('users/Access', [
            ...$buildUserDirectory->handle(
                search: str($filters['q'] ?? '')->trim()->toString(),
                roleCode: $filters['role'] ?? null,
            ),
            'capabilities' => [
                'provision_users' => $request->user()->can(UserPermission::ProvisionUsers->value),
                'manage_user_access' => $request->user()->can(UserPermission::ManageUserAccess->value),
                'provision_laboratory_actors' => $request->user()->can(UserPermission::ProvisionLaboratoryActors->value)
                    && config('bpls_installation.seed_laboratory_actors') === true
                    && ! app()->isProduction()
                    && $engineeringReviewer,
            ],
        ]);
    }
}
