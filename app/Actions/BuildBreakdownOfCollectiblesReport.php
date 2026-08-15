<?php

namespace App\Actions;

use App\Enums\PaymentScheduleStatus;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use Illuminate\Support\Collection;

final class BuildBreakdownOfCollectiblesReport
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
            ->whereIn('status', [
                PaymentScheduleStatus::Pending,
                PaymentScheduleStatus::PartiallyPaid,
            ])
            ->where(function ($query) use ($year): void {
                $query
                    ->whereYear('due_on', $year)
                    ->orWhere(function ($query) use ($year): void {
                        $query->whereNull('due_on')
                            ->whereHas('permitApplication', fn ($query) => $query->where('application_year', $year));
                    });
            })
            ->whereHas('permitApplication', function ($query) use ($type, $search): void {
                $query
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
                                        ->orWhere('address', 'like', '%'.$search.'%')
                                        ->orWhere('barangay', 'like', '%'.$search.'%')
                                        ->orWhereHas('owner', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
                                });
                        });
                    });
            })
            ->orderBy('permit_application_id')
            ->orderBy('sequence')
            ->get();

        $schedulesByApplication = [];

        foreach ($schedules as $schedule) {
            $schedulesByApplication[$schedule->permit_application_id][] = $schedule;
        }

        $permitApplications = PermitApplication::query()
            ->with(['business.owner', 'lines'])
            ->whereKey(array_keys($schedulesByApplication))
            ->get();

        $rows = $permitApplications
            ->map(fn (PermitApplication $permitApplication): array => $this->row(
                collect($schedulesByApplication[$permitApplication->id]),
                $permitApplication,
            ))
            ->sortBy([
                ['owner_name', 'asc'],
                ['business_name', 'asc'],
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
                'schedule_count' => $rows->sum('schedule_count'),
                'q1_amount_cents' => $rows->sum('q1_amount_cents'),
                'q2_amount_cents' => $rows->sum('q2_amount_cents'),
                'q3_amount_cents' => $rows->sum('q3_amount_cents'),
                'q4_amount_cents' => $rows->sum('q4_amount_cents'),
                'unscheduled_amount_cents' => $rows->sum('unscheduled_amount_cents'),
                'total_amount_cents' => $rows->sum('total_amount_cents'),
                'date_basis' => 'schedule_due_year_with_unscheduled_application_year_fallback',
                'grain' => 'one_row_per_permit_application',
                'scope' => 'Outstanding balances from pending and partially paid permit payment schedules for the selected year, grouped by permit application.',
                'policy_note' => 'Quarter columns use persisted schedule due dates only. Schedules without an authorized due date remain visible as Unscheduled; this report does not invent installments, due dates, delinquency, surcharge, interest, PIL, enforceability, or official report acceptance.',
                'legacy_discrepancy' => 'The legacy query omitted schedules without a due date. Laravel preserves those outstanding balances explicitly instead of silently dropping them.',
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, PaymentSchedule>  $schedules
     * @return array<string, mixed>
     */
    private function row(Collection $schedules, PermitApplication $permitApplication): array
    {
        $business = $permitApplication->business;
        $outstandingByQuarter = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        $unscheduledAmountCents = 0;

        foreach ($schedules as $paymentSchedule) {
            $outstandingAmountCents = max(0, $paymentSchedule->total_amount_cents - $paymentSchedule->paid_amount_cents);

            if ($paymentSchedule->due_on === null) {
                $unscheduledAmountCents += $outstandingAmountCents;

                continue;
            }

            $outstandingByQuarter[$paymentSchedule->due_on->quarter] += $outstandingAmountCents;
        }

        $quarterTotalAmountCents = array_sum($outstandingByQuarter);

        return [
            'application_id' => $permitApplication->id,
            'application_number' => $permitApplication->application_number,
            'application_type' => $permitApplication->type->value,
            'application_status' => $permitApplication->status->value,
            'application_year' => $permitApplication->application_year,
            'application_date' => $this->applicationDate($permitApplication),
            'business_id' => $business->id,
            'business_name' => $business->name,
            'trade_name' => $business->trade_name,
            'business_address' => $business->address,
            'barangay' => $business->barangay,
            'owner_name' => $business->owner->name,
            'capital_investment_cents' => $permitApplication->lines->sum('capital_investment_cents'),
            'gross_sales_cents' => $permitApplication->lines->sum('declared_gross_sales_cents'),
            'payment_modes' => $schedules->pluck('payment_mode')->unique()->values()->all(),
            'schedule_count' => $schedules->count(),
            'q1_amount_cents' => $outstandingByQuarter[1],
            'q2_amount_cents' => $outstandingByQuarter[2],
            'q3_amount_cents' => $outstandingByQuarter[3],
            'q4_amount_cents' => $outstandingByQuarter[4],
            'quarter_total_amount_cents' => $quarterTotalAmountCents,
            'unscheduled_amount_cents' => $unscheduledAmountCents,
            'total_amount_cents' => $quarterTotalAmountCents + $unscheduledAmountCents,
        ];
    }

    private function applicationDate(PermitApplication $permitApplication): ?string
    {
        return ($permitApplication->submitted_at ?? $permitApplication->created_at)?->toDateString();
    }
}
