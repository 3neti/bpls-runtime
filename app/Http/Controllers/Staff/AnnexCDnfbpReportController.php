<?php

namespace App\Http\Controllers\Staff;

use App\Actions\DescribeAnnexCDnfbpReportBoundary;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class AnnexCDnfbpReportController extends Controller
{
    public function index(DescribeAnnexCDnfbpReportBoundary $report): Response
    {
        Gate::authorize(UserPermission::ViewReports->value);

        return Inertia::render('reports/AnnexCDnfbp', $report->handle());
    }
}
