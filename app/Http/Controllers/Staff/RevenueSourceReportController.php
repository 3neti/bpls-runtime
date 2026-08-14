<?php

namespace App\Http\Controllers\Staff;

use App\Actions\BuildCollectionsByRevenueSourceReport;
use App\Enums\FeeRuleCategory;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RevenueSourceReportController extends Controller
{
    public function index(Request $request, BuildCollectionsByRevenueSourceReport $report): Response
    {
        Gate::authorize(UserPermission::ViewReports->value);

        $payload = $report->handle($this->validatedFilters($request));

        return Inertia::render('reports/RevenueSources', [
            'filters' => $payload['filters'],
            'summary' => $payload['summary'],
            'rows' => $payload['rows'],
            'categories' => collect(FeeRuleCategory::cases())
                ->map(fn (FeeRuleCategory $category): array => [
                    'label' => str($category->value)->replace('_', ' ')->title()->toString(),
                    'value' => $category->value,
                ])
                ->values(),
        ]);
    }

    public function download(Request $request, BuildCollectionsByRevenueSourceReport $report): StreamedResponse
    {
        Gate::authorize(UserPermission::ViewReports->value);

        $payload = $report->handle($this->validatedFilters($request));
        $filename = 'collections-by-revenue-source-'.$payload['filters']['date_from'].'-to-'.$payload['filters']['date_to'].'.csv';

        return response()->streamDownload(function () use ($payload): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'Category',
                'Source Code',
                'Source Name',
                'Line of Business',
                'Allocations',
                'Receipts',
                'Amount',
            ]);

            foreach ($payload['rows'] as $row) {
                fputcsv($output, [
                    $row['category'],
                    $row['code'],
                    $row['name'],
                    $row['line_of_business'],
                    $row['allocation_count'],
                    $row['receipt_count'],
                    number_format($row['amount_cents'] / 100, 2, '.', ''),
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{date_from?: string|null, date_to?: string|null, category?: string|null}
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'category' => ['nullable', Rule::enum(FeeRuleCategory::class)],
        ]);
    }
}
