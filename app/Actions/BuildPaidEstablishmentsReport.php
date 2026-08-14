<?php

namespace App\Actions;

use App\Enums\PaymentScheduleStatus;
use App\Enums\ReceiptStatus;
use App\Models\PaymentSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class BuildPaidEstablishmentsReport
{
    /**
     * @param  array{year?: int|string|null, type?: string|null, q?: string|null}  $filters
     * @return array{
     *     filters: array{year: int, type: string|null, q: string|null},
     *     summary: array<string, mixed>,
     *     rows: Collection<int, array<string, mixed>>
     * }
     */
    public function handle(array $filters = []): array
    {
        $year = (int) ($filters['year'] ?? now()->year);
        $type = filled($filters['type'] ?? null) ? (string) $filters['type'] : null;
        $search = filled($filters['q'] ?? null) ? trim((string) $filters['q']) : null;

        $schedules = PaymentSchedule::query()
            ->with([
                'permitApplication.business.owner',
                'permitApplication.lines.lineOfBusiness',
                'treasuryCollections.receipt',
            ])
            ->where('status', PaymentScheduleStatus::Paid)
            ->whereHas('permitApplication', function ($query) use ($year, $type, $search): void {
                $query->where('application_year', $year)
                    ->when($type !== null, fn ($query) => $query->where('type', $type))
                    ->when($search !== null, function ($query) use ($search): void {
                        $query->where(function ($query) use ($search): void {
                            $query
                                ->where('application_number', 'like', '%'.$search.'%')
                                ->orWhereHas('business', function ($query) use ($search): void {
                                    $query
                                        ->where('name', 'like', '%'.$search.'%')
                                        ->orWhere('trade_name', 'like', '%'.$search.'%')
                                        ->orWhere('registration_number', 'like', '%'.$search.'%')
                                        ->orWhere('barangay', 'like', '%'.$search.'%')
                                        ->orWhereHas('owner', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
                                });
                        });
                    });
            })
            ->orderBy('id')
            ->get();

        $rows = $schedules
            ->map(fn (PaymentSchedule $schedule): array => $this->row($schedule))
            ->sortBy([
                ['business_name', 'asc'],
                ['application_number', 'asc'],
            ])
            ->values();

        return [
            'filters' => [
                'year' => $year,
                'type' => $type,
                'q' => $search,
            ],
            'summary' => [
                'row_count' => $rows->count(),
                'business_count' => $rows->pluck('business_id')->unique()->count(),
                'paid_amount_cents' => $rows->sum('paid_amount_cents'),
                'receipted_count' => $rows->where('receipt_status', ReceiptStatus::Issued->value)->count(),
                'date_basis' => 'permit_application_year',
                'scope' => 'Paid permit payment schedules for the selected application year.',
                'policy_note' => 'This report is a paid-establishment foundation only. It does not imply permit issuance, release, current validity, final official masterlist acceptance, or non-permit Treasury coverage.',
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(PaymentSchedule $schedule): array
    {
        $permitApplication = $schedule->permitApplication;
        $business = $permitApplication->business;
        $receipt = $schedule->treasuryCollections
            ->pluck('receipt')
            ->filter()
            ->where('status', ReceiptStatus::Issued)
            ->sortBy('issued_at')
            ->last();

        return [
            'payment_schedule_id' => $schedule->id,
            'application_id' => $permitApplication->id,
            'application_number' => $permitApplication->application_number,
            'application_type' => $permitApplication->type->value,
            'application_status' => $permitApplication->status->value,
            'application_year' => $permitApplication->application_year,
            'business_id' => $business->id,
            'business_name' => $business->name,
            'trade_name' => $business->trade_name,
            'registration_number' => $business->registration_number,
            'barangay' => $business->barangay,
            'owner_name' => $business->owner->name,
            'line_of_businesses' => $permitApplication->lines
                ->map(fn ($line): ?string => $line->lineOfBusiness?->name)
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'paid_amount_cents' => $schedule->paid_amount_cents,
            'schedule_status' => $schedule->status->value,
            'receipt_number' => $receipt?->receipt_number,
            'receipt_status' => $receipt?->status->value,
            'receipt_issued_at' => $receipt?->issued_at instanceof Carbon
                ? $receipt->issued_at->toIso8601String()
                : null,
        ];
    }
}
