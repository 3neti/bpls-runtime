<?php

namespace App\Http\Controllers\Staff;

use App\Actions\BuildPaymentSummaryReport;
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

class PaymentSummaryReportController extends Controller
{
    public function index(Request $request, BuildPaymentSummaryReport $report): Response
    {
        Gate::authorize(UserPermission::ViewReports->value);

        $payload = $report->handle($this->validatedFilters($request));

        return Inertia::render('reports/PaymentSummary', [
            'filters' => $payload['filters'],
            'summary' => $payload['summary'],
            'rows' => $payload['rows'],
            'types' => collect(PermitApplicationType::cases())
                ->map(fn (PermitApplicationType $type): array => [
                    'label' => str($type->value)->replace('_', ' ')->title()->toString(),
                    'value' => $type->value,
                ])
                ->values(),
            'statuses' => collect(PaymentScheduleStatus::cases())
                ->map(fn (PaymentScheduleStatus $status): array => [
                    'label' => str($status->value)->replace('_', ' ')->title()->toString(),
                    'value' => $status->value,
                ])
                ->values(),
        ]);
    }

    public function download(Request $request, BuildPaymentSummaryReport $report): StreamedResponse
    {
        Gate::authorize(UserPermission::ViewReports->value);

        $payload = $report->handle($this->validatedFilters($request));
        $filename = 'payment-summary-'.$payload['filters']['year'].'.csv';

        return response()->streamDownload(function () use ($payload): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'Payment Schedule ID', 'Sequence', 'Payment Status', 'Payment Mode', 'Due On',
                'Application Number', 'Application Type', 'Application Status', 'Business Name',
                'Trade Name', 'Owner', 'Registration Number', 'Total Amount', 'Paid Amount',
                'Outstanding Amount', 'Collection Amount', 'Collection Difference', 'Collection Count',
                'Receipted Amount', 'Receipted Count', 'Pending Receipt Amount', 'Pending Receipt Count',
                'Collection Methods', 'Latest Receipt Number', 'Latest Receipt Issued At',
            ]);

            foreach ($payload['rows'] as $row) {
                fputcsv($output, [
                    $row['payment_schedule_id'], $row['schedule_sequence'], $row['schedule_status'],
                    $row['payment_mode'], $row['due_on'], $row['application_number'], $row['application_type'],
                    $row['application_status'], $row['business_name'], $row['trade_name'], $row['owner_name'],
                    $row['registration_number'], number_format($row['total_amount_cents'] / 100, 2, '.', ''),
                    number_format($row['paid_amount_cents'] / 100, 2, '.', ''),
                    number_format($row['outstanding_amount_cents'] / 100, 2, '.', ''),
                    number_format($row['collection_amount_cents'] / 100, 2, '.', ''),
                    number_format($row['collection_difference_cents'] / 100, 2, '.', ''),
                    $row['collection_count'], number_format($row['receipted_amount_cents'] / 100, 2, '.', ''),
                    $row['receipted_count'], number_format($row['pending_receipt_amount_cents'] / 100, 2, '.', ''),
                    $row['pending_receipt_count'], implode('; ', $row['collection_methods']),
                    $row['latest_receipt_number'], $row['latest_receipt_issued_at'],
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{year?: int|string|null, type?: string|null, status?: string|null, q?: string|null} */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'type' => ['nullable', Rule::enum(PermitApplicationType::class)],
            'status' => ['nullable', Rule::enum(PaymentScheduleStatus::class)],
            'q' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
