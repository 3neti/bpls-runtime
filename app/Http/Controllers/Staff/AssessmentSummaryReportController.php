<?php

namespace App\Http\Controllers\Staff;

use App\Actions\BuildAssessmentSummaryReport;
use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssessmentSummaryReportController extends Controller
{
    public function index(Request $request, BuildAssessmentSummaryReport $report): Response
    {
        Gate::authorize(UserPermission::ViewReports->value);

        $payload = $report->handle($this->validatedFilters($request));

        return Inertia::render('reports/AssessmentSummary', [
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

    public function download(Request $request, BuildAssessmentSummaryReport $report): StreamedResponse
    {
        Gate::authorize(UserPermission::ViewReports->value);

        $payload = $report->handle($this->validatedFilters($request));
        $filename = 'assessment-summary-'.$payload['filters']['year'].'.csv';

        return response()->streamDownload(function () use ($payload): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'Assessment ID',
                'Sequence',
                'Assessed At',
                'Assessed By',
                'Application Number',
                'Application Type',
                'Application Status',
                'Business Name',
                'Trade Name',
                'Owner',
                'Registration Number',
                'Lines of Business',
                'Assessment Lines',
                'Tax Amount',
                'Fee Amount',
                'Clearance Amount',
                'Other Amount',
                'Total Amount',
            ]);

            foreach ($payload['rows'] as $row) {
                fputcsv($output, [
                    $row['assessment_id'],
                    $row['assessment_sequence'],
                    $row['assessed_at'],
                    $row['assessed_by'],
                    $row['application_number'],
                    $row['application_type'],
                    $row['application_status'],
                    $row['business_name'],
                    $row['trade_name'],
                    $row['owner_name'],
                    $row['registration_number'],
                    implode('; ', $row['line_of_businesses']),
                    $row['line_count'],
                    number_format($row['tax_amount_cents'] / 100, 2, '.', ''),
                    number_format($row['fee_amount_cents'] / 100, 2, '.', ''),
                    number_format($row['clearance_amount_cents'] / 100, 2, '.', ''),
                    number_format($row['other_amount_cents'] / 100, 2, '.', ''),
                    number_format($row['total_amount_cents'] / 100, 2, '.', ''),
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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
