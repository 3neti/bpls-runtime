<?php

namespace App\Http\Controllers\Staff;

use App\Actions\BuildUnpaidEstablishmentsReport;
use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnpaidEstablishmentReportController extends Controller
{
    public function index(Request $request, BuildUnpaidEstablishmentsReport $report): Response
    {
        Gate::authorize(UserPermission::ViewReports->value);

        $payload = $report->handle($this->validatedFilters($request));

        return Inertia::render('reports/UnpaidEstablishments', [
            'filters' => $payload['filters'],
            'summary' => $payload['summary'],
            'rows' => $payload['rows'],
            'types' => collect(PermitApplicationType::cases())
                ->map(fn (PermitApplicationType $type): array => [
                    'label' => str($type->value)->replace('_', ' ')->title()->toString(),
                    'value' => $type->value,
                ])
                ->values(),
            'statuses' => collect([
                PaymentScheduleStatus::Pending,
                PaymentScheduleStatus::PartiallyPaid,
            ])
                ->map(fn (PaymentScheduleStatus $status): array => [
                    'label' => str($status->value)->replace('_', ' ')->title()->toString(),
                    'value' => $status->value,
                ])
                ->values(),
        ]);
    }

    public function download(Request $request, BuildUnpaidEstablishmentsReport $report): StreamedResponse
    {
        Gate::authorize(UserPermission::ViewReports->value);

        $payload = $report->handle($this->validatedFilters($request));
        $filename = 'unpaid-establishments-'.$payload['filters']['year'].'.csv';

        return response()->streamDownload(function () use ($payload): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'Application Number',
                'Application Type',
                'Application Status',
                'Business Name',
                'Trade Name',
                'Owner',
                'Registration Number',
                'Barangay',
                'Lines of Business',
                'Total Amount',
                'Paid Amount',
                'Outstanding Amount',
                'Payment Status',
                'Due On',
            ]);

            foreach ($payload['rows'] as $row) {
                fputcsv($output, [
                    $row['application_number'],
                    $row['application_type'],
                    $row['application_status'],
                    $row['business_name'],
                    $row['trade_name'],
                    $row['owner_name'],
                    $row['registration_number'],
                    $row['barangay'],
                    implode('; ', $row['line_of_businesses']),
                    number_format($row['total_amount_cents'] / 100, 2, '.', ''),
                    number_format($row['paid_amount_cents'] / 100, 2, '.', ''),
                    number_format($row['outstanding_amount_cents'] / 100, 2, '.', ''),
                    $row['schedule_status'],
                    $row['due_on'],
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{year?: int|string|null, type?: string|null, q?: string|null, status?: string|null}
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'type' => ['nullable', Rule::enum(PermitApplicationType::class)],
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([
                PaymentScheduleStatus::Pending->value,
                PaymentScheduleStatus::PartiallyPaid->value,
            ])],
        ]);
    }
}
