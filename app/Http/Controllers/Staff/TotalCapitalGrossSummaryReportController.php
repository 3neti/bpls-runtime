<?php

namespace App\Http\Controllers\Staff;

use App\Actions\BuildTotalCapitalGrossSummaryReport;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TotalCapitalGrossSummaryReportController extends Controller
{
    public function index(Request $request, BuildTotalCapitalGrossSummaryReport $report): Response
    {
        Gate::authorize(UserPermission::ViewReports->value);

        return Inertia::render('reports/TotalCapitalGrossSummary', $report->handle($this->validatedFilters($request)));
    }

    public function download(Request $request, BuildTotalCapitalGrossSummaryReport $report): StreamedResponse
    {
        Gate::authorize(UserPermission::ViewReports->value);

        $payload = $report->handle($this->validatedFilters($request));
        $dateFrom = $payload['filters']['date_from'] ?? 'all';
        $dateTo = $payload['filters']['date_to'] ?? 'all';

        return response()->streamDownload(function () use ($payload): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'Owner Name', 'Business Name', 'Capital', 'Gross', 'OR Number',
                'Payment Date', 'Payment Amount', 'Remaining Balance', 'Payment Status',
            ]);

            foreach ($payload['rows'] as $row) {
                fputcsv($output, [
                    $row['owner_name'],
                    $row['business_name'],
                    number_format($row['capital_investment_cents'] / 100, 2, '.', ''),
                    number_format($row['gross_sales_cents'] / 100, 2, '.', ''),
                    $row['latest_receipt_number'],
                    $row['latest_payment_date'],
                    number_format($row['payment_amount_cents'] / 100, 2, '.', ''),
                    number_format($row['remaining_balance_cents'] / 100, 2, '.', ''),
                    $row['payment_status'],
                ]);
            }

            fputcsv($output, [
                'TOTAL',
                '',
                number_format($payload['summary']['capital_investment_cents'] / 100, 2, '.', ''),
                number_format($payload['summary']['gross_sales_cents'] / 100, 2, '.', ''),
                '',
                '',
                number_format($payload['summary']['payment_amount_cents'] / 100, 2, '.', ''),
                number_format($payload['summary']['remaining_balance_cents'] / 100, 2, '.', ''),
                '',
            ]);
            fclose($output);
        }, "total-capital-gross-summary-{$dateFrom}-to-{$dateTo}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return array{date_from?: string|null, date_to?: string|null} */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }
}
