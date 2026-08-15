<?php

namespace App\Http\Controllers\Staff;

use App\Actions\DescribePldsReportBoundary;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class PldsReportController extends Controller
{
    public function index(DescribePldsReportBoundary $report): Response
    {
        Gate::authorize(UserPermission::ViewReports->value);

        return Inertia::render('reports/Plds', $report->handle());
    }
}
