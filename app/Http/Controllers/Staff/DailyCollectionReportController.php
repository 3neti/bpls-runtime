<?php

namespace App\Http\Controllers\Staff;

use App\Actions\BuildDailyCollectionsReport;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailyCollectionReportController extends Controller
{
    public function index(Request $request, BuildDailyCollectionsReport $report): Response
    {
        Gate::authorize(UserPermission::ViewReports->value);

        $filters = $this->validatedFilters($request);
        $payload = $report->handle($filters);

        return Inertia::render('reports/DailyCollections', [
            'filters' => $payload['filters'],
            'summary' => $payload['summary'],
            'rows' => $payload['rows'],
        ]);
    }

    public function download(Request $request, BuildDailyCollectionsReport $report): StreamedResponse
    {
        Gate::authorize(UserPermission::ViewReports->value);

        $payload = $report->handle($this->validatedFilters($request));
        $filename = 'daily-collections-'.$payload['filters']['date_from'].'-to-'.$payload['filters']['date_to'].'.csv';

        return response()->streamDownload(function () use ($payload): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'Collection ID',
                'Received At',
                'Receipt Number',
                'Receipt Issued At',
                'Application Number',
                'Business',
                'Owner',
                'Payer',
                'Method',
                'Channel',
                'Collection Status',
                'Receipt Status',
                'Amount',
                'Reference Number',
            ]);

            foreach ($payload['rows'] as $row) {
                fputcsv($output, [
                    $row['collection_id'],
                    $row['received_at'],
                    $row['receipt_number'],
                    $row['receipt_issued_at'],
                    $row['application_number'],
                    $row['business_name'],
                    $row['owner_name'],
                    $row['payer_name'],
                    $row['method'],
                    $row['channel'],
                    $row['collection_status'],
                    $row['receipt_status'],
                    number_format($row['amount_cents'] / 100, 2, '.', ''),
                    $row['reference_number'],
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{date_from?: string|null, date_to?: string|null}
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);
    }
}
