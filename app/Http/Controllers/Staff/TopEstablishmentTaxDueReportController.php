<?php

namespace App\Http\Controllers\Staff;

use App\Actions\BuildTopEstablishmentsTaxDueReport;
use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TopEstablishmentTaxDueReportController extends Controller
{
    public function index(Request $request, BuildTopEstablishmentsTaxDueReport $report): Response
    {
        Gate::authorize(UserPermission::ViewReports->value);

        $payload = $report->handle($this->validatedFilters($request));

        return Inertia::render('reports/TopEstablishmentsTaxDue', [
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

    public function download(Request $request, BuildTopEstablishmentsTaxDueReport $report): StreamedResponse
    {
        Gate::authorize(UserPermission::ViewReports->value);

        $payload = $report->handle($this->validatedFilters($request));
        $filename = 'top-establishments-tax-due-'.$payload['filters']['year'].'.csv';

        return response()->streamDownload(function () use ($payload): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'Rank',
                'Application Number',
                'Application Type',
                'Application Status',
                'Business Name',
                'Trade Name',
                'Owner',
                'Registration Number',
                'Barangay',
                'Lines of Business',
                'Tax Codes',
                'Tax Due',
                'Tax Line Count',
                'Payment Status',
                'Schedule Total',
                'Paid Amount',
                'Outstanding Amount',
            ]);

            foreach ($payload['rows'] as $index => $row) {
                fputcsv($output, [
                    $index + 1,
                    $row['application_number'],
                    $row['application_type'],
                    $row['application_status'],
                    $row['business_name'],
                    $row['trade_name'],
                    $row['owner_name'],
                    $row['registration_number'],
                    $row['barangay'],
                    implode('; ', $row['line_of_businesses']),
                    implode('; ', $row['tax_codes']),
                    number_format($row['tax_due_cents'] / 100, 2, '.', ''),
                    $row['tax_line_count'],
                    $row['schedule_status'],
                    number_format($row['total_schedule_amount_cents'] / 100, 2, '.', ''),
                    number_format($row['paid_amount_cents'] / 100, 2, '.', ''),
                    number_format($row['outstanding_amount_cents'] / 100, 2, '.', ''),
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{year?: int|string|null, type?: string|null, q?: string|null, limit?: int|string|null}
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'type' => ['nullable', Rule::enum(PermitApplicationType::class)],
            'q' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }
}
