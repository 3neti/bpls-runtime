<?php

namespace App\Http\Controllers\Staff;

use App\Actions\BuildBreakdownOfCollectiblesReport;
use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CollectiblesReportController extends Controller
{
    public function index(Request $request, BuildBreakdownOfCollectiblesReport $report): Response
    {
        Gate::authorize(UserPermission::ViewReports->value);

        $payload = $report->handle($this->validatedFilters($request));

        return Inertia::render('reports/BreakdownOfCollectibles', [
            'filters' => $payload['filters'],
            'summary' => $payload['summary'],
            'rows' => $payload['rows'],
            'types' => collect(PermitApplicationType::cases())
                ->map(fn (PermitApplicationType $type): array => [
                    'label' => str($type->value)->replace('_', ' ')->title()->toString(),
                    'value' => $type->value,
                ])
                ->values(),
        ]);
    }

    public function download(Request $request, BuildBreakdownOfCollectiblesReport $report): StreamedResponse
    {
        Gate::authorize(UserPermission::ViewReports->value);

        $payload = $report->handle($this->validatedFilters($request));
        $filename = 'breakdown-of-collectibles-'.$payload['filters']['year'].'.csv';

        return response()->streamDownload(function () use ($payload): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'Owner / Applicant', 'Business Name', 'Business Address', 'Application Number',
                'Application Type', 'Application Date', 'Capital Investment', 'Gross Sales',
                'Payment Mode', 'First Quarter', 'Second Quarter', 'Third Quarter',
                'Fourth Quarter', 'Unscheduled', 'Total Collectible',
            ]);

            foreach ($payload['rows'] as $row) {
                fputcsv($output, [
                    $row['owner_name'], $row['business_name'], $row['business_address'],
                    $row['application_number'], $row['application_type'], $row['application_date'],
                    number_format($row['capital_investment_cents'] / 100, 2, '.', ''),
                    number_format($row['gross_sales_cents'] / 100, 2, '.', ''),
                    implode('; ', $row['payment_modes']),
                    number_format($row['q1_amount_cents'] / 100, 2, '.', ''),
                    number_format($row['q2_amount_cents'] / 100, 2, '.', ''),
                    number_format($row['q3_amount_cents'] / 100, 2, '.', ''),
                    number_format($row['q4_amount_cents'] / 100, 2, '.', ''),
                    number_format($row['unscheduled_amount_cents'] / 100, 2, '.', ''),
                    number_format($row['total_amount_cents'] / 100, 2, '.', ''),
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{year?: int|string|null, type?: string|null, q?: string|null} */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'type' => ['nullable', Rule::enum(PermitApplicationType::class)],
            'q' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
