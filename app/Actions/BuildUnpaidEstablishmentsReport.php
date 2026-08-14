<?php

namespace App\Actions;

use App\Enums\PaymentScheduleStatus;
use App\Models\PaymentSchedule;
use Illuminate\Support\Collection;

final class BuildUnpaidEstablishmentsReport
{
    /**
     * @param  array{year?: int|string|null, type?: string|null, q?: string|null, status?: string|null}  $filters
     * @return array{
     *     filters: array{year: int, type: string|null, q: string|null, status: string|null},
     *     summary: array<string, mixed>,
     *     rows: Collection<int, array<string, mixed>>
     * }
     */
    public function handle(array $filters = []): array
    {
        $year = (int) ($filters['year'] ?? now()->year);
        $type = filled($filters['type'] ?? null) ? (string) $filters['type'] : null;
        $search = filled($filters['q'] ?? null) ? trim((string) $filters['q']) : null;
        $status = filled($filters['status'] ?? null) ? (string) $filters['status'] : null;

        $unpaidStatuses = [
            PaymentScheduleStatus::Pending->value,
            PaymentScheduleStatus::PartiallyPaid->value,
        ];

        $schedules = PaymentSchedule::query()
            ->with([
                'permitApplication.business.owner',
                'permitApplication.lines.lineOfBusiness',
            ])
            ->whereIn('status', $unpaidStatuses)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
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
                'status' => $status,
            ],
            'summary' => [
                'row_count' => $rows->count(),
                'business_count' => $rows->pluck('business_id')->unique()->count(),
                'total_amount_cents' => $rows->sum('total_amount_cents'),
                'paid_amount_cents' => $rows->sum('paid_amount_cents'),
                'outstanding_amount_cents' => $rows->sum('outstanding_amount_cents'),
                'partially_paid_count' => $rows->where('schedule_status', PaymentScheduleStatus::PartiallyPaid->value)->count(),
                'date_basis' => 'permit_application_year',
                'scope' => 'Pending and partially paid permit payment schedules for the selected application year.',
                'policy_note' => 'This report is an unpaid-establishment foundation only. It does not calculate legal delinquency, penalties, surcharge, interest, PIL, enforceability, or final official masterlist acceptance.',
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
        $outstandingAmountCents = max(0, $schedule->total_amount_cents - $schedule->paid_amount_cents);

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
            'total_amount_cents' => $schedule->total_amount_cents,
            'paid_amount_cents' => $schedule->paid_amount_cents,
            'outstanding_amount_cents' => $outstandingAmountCents,
            'schedule_status' => $schedule->status->value,
            'due_on' => $schedule->due_on?->toDateString(),
        ];
    }
}
