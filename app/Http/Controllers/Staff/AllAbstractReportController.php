<?php

namespace App\Http\Controllers\Staff;

use App\Actions\DescribeAllAbstractReportBoundary;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class AllAbstractReportController extends Controller
{
    public function index(DescribeAllAbstractReportBoundary $report): Response
    {
        Gate::authorize(UserPermission::ViewReports->value);

        return Inertia::render('reports/AllAbstract', $report->handle());
    }
}
