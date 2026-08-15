<?php

namespace App\Http\Controllers\Staff;

use App\Actions\DescribeCmciLdcsReportBoundary;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class CmciLdcsReportController extends Controller
{
    public function index(DescribeCmciLdcsReportBoundary $report): Response
    {
        Gate::authorize(UserPermission::ViewReports->value);

        return Inertia::render('reports/CmciLdcs', $report->handle());
    }
}
