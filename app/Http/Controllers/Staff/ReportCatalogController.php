<?php

namespace App\Http\Controllers\Staff;

use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReportCatalogController extends Controller
{
    public function index(): Response
    {
        Gate::authorize(UserPermission::ViewReports->value);

        return Inertia::render('reports/Index');
    }
}
