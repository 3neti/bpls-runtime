<?php

namespace App\Http\Controllers\Staff;

use App\Actions\DescribeBillingGroupAbstractReportBoundary;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Models\BillingGroup;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class BillingGroupAbstractReportController extends Controller
{
    public function index(BillingGroup $billingGroup, DescribeBillingGroupAbstractReportBoundary $report): Response
    {
        Gate::authorize(UserPermission::ViewReports->value);

        return Inertia::render('reports/BillingGroupAbstract', $report->handle($billingGroup));
    }
}
