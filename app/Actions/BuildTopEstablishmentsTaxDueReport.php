<?php

namespace App\Actions;

use App\Enums\FeeRuleCategory;
use App\Models\PaymentSchedule;
use Illuminate\Support\Collection;

final class BuildTopEstablishmentsTaxDueReport
{
    /**
     * @param  array{year?: int|string|null, type?: string|null, q?: string|null, limit?: int|string|null}  $filters
     * @return array{
     *     filters: array{year: int, type: string|null, q: string|null, limit: int},
     *     summary: array<string, mixed>,
     *     rows: Collection<int, array<string, mixed>>
     * }
     */
    public function handle(array $filters = []): array
    {
        $year = (int) ($filters['year'] ?? now()->year);
        $type = filled($filters['type'] ?? null) ? (string) $filters['type'] : null;
        $search = filled($filters['q'] ?? null) ? trim((string) $filters['q']) : null;
        $limit = min(100, max(1, (int) ($filters['limit'] ?? 100)));

        $schedules = PaymentSchedule::query()
            ->with([
                'assessment.lines.lineOfBusiness',
                'permitApplication.business.owner',
                'permitApplication.lines.lineOfBusiness',
            ])
            ->whereHas('assessment.lines', fn ($query) => $query->where('category', FeeRuleCategory::Tax))
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
            ->filter(fn (array $row): bool => $row['tax_due_cents'] > 0)
            ->sortBy([
                ['tax_due_cents', 'desc'],
                ['business_name', 'asc'],
                ['application_number', 'asc'],
            ])
            ->take($limit)
            ->values();

        return [
            'filters' => [
                'year' => $year,
                'type' => $type,
                'q' => $search,
                'limit' => $limit,
            ],
            'summary' => [
                'row_count' => $rows->count(),
                'business_count' => $rows->pluck('business_id')->unique()->count(),
                'tax_due_cents' => $rows->sum('tax_due_cents'),
                'largest_tax_due_cents' => $rows->max('tax_due_cents') ?? 0,
                'date_basis' => 'permit_application_year',
                'scope' => 'Top establishments by persisted tax assessment lines for the selected application year.',
                'policy_note' => 'This report ranks persisted assessment tax lines only. It does not calculate legal delinquency, outstanding balance, penalties, surcharge, interest, PIL, enforceability, or final official top-taxpayer acceptance.',
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
        $taxLines = $schedule->assessment->lines
            ->where('category', FeeRuleCategory::Tax);
        $taxDueCents = $taxLines->sum('amount_cents');

        return [
            'payment_schedule_id' => $schedule->id,
            'assessment_id' => $schedule->assessment_id,
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
            'tax_line_count' => $taxLines->count(),
            'tax_codes' => $taxLines
                ->pluck('code')
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'tax_due_cents' => $taxDueCents,
            'schedule_status' => $schedule->status->value,
            'total_schedule_amount_cents' => $schedule->total_amount_cents,
            'paid_amount_cents' => $schedule->paid_amount_cents,
            'outstanding_amount_cents' => max(0, $schedule->total_amount_cents - $schedule->paid_amount_cents),
        ];
    }
}
