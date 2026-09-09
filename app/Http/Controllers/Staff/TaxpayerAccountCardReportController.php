<?php

namespace App\Http\Controllers\Staff;

use App\Actions\DescribeTaxpayerAccountCardReportBoundary;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class TaxpayerAccountCardReportController extends Controller
{
    public function index(DescribeTaxpayerAccountCardReportBoundary $report): Response
    {
        Gate::authorize(UserPermission::ViewReports->value);

        return Inertia::render('reports/TaxpayerAccountCard', $report->handle());
    }
}
