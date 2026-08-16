<?php

namespace App\Http\Controllers\Staff;

use App\Actions\BuildUserDirectory;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserDirectoryController extends Controller
{
    public function index(Request $request, BuildUserDirectory $buildUserDirectory): Response
    {
        Gate::authorize(UserPermission::ViewUsers->value);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'string', 'max:80'],
        ]);

        return Inertia::render('users/Index', $buildUserDirectory->handle(
            search: str($filters['q'] ?? '')->trim()->toString(),
            roleCode: $filters['role'] ?? null,
        ));
    }
}
