<?php

namespace App\Http\Controllers\Staff;

use App\Actions\BuildBusinessTaxByMajorTypeReport;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessTaxByMajorTypeReportController extends Controller
{
    public function index(Request $request, BuildBusinessTaxByMajorTypeReport $report): Response
    {
        Gate::authorize(UserPermission::ViewReports->value);

        $payload = $report->handle($this->validatedFilters($request));

        return Inertia::render('reports/BusinessTaxByMajorType', $payload);
    }

    public function download(Request $request, BuildBusinessTaxByMajorTypeReport $report): StreamedResponse
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

            fputcsv($output, ['Major Type', 'Allocation Count', 'Receipt Count', 'Amount']);

            foreach ($payload['rows'] as $row) {
                fputcsv($output, [
                    $row['major_type'],
                    $row['allocation_count'],
                    $row['receipt_count'],
                    number_format($row['amount_cents'] / 100, 2, '.', ''),
                ]);
            }

            fputcsv($output, ['Total Amount', $payload['summary']['allocation_count'], $payload['summary']['receipt_count'], number_format($payload['summary']['total_amount_cents'] / 100, 2, '.', '')]);
            fclose($output);
        }, "business-tax-by-major-type-{$dateFrom}-to-{$dateTo}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return array{date_from?: string|null, date_to?: string|null, receipt_from?: string|null, receipt_to?: string|null} */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'receipt_from' => ['nullable', 'string', 'max:100'],
            'receipt_to' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
