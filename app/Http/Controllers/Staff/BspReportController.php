<?php

namespace App\Http\Controllers\Staff;

use App\Actions\DescribeBspReportBoundary;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class BspReportController extends Controller
{
    public function index(DescribeBspReportBoundary $report): Response
    {
        Gate::authorize(UserPermission::ViewReports->value);

        return Inertia::render('reports/Bsp', $report->handle());
    }
}
