<?php

namespace App\Actions;

use App\Enums\AssessmentStatus;
use App\Enums\FeeRuleCategory;
use App\Models\Assessment;
use Illuminate\Support\Collection;

final class BuildAssessmentSummaryReport
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

        $assessments = Assessment::query()
            ->with([
                'assessedBy',
                'lines.lineOfBusiness',
                'permitApplication.business.owner',
            ])
            ->where('status', AssessmentStatus::Computed)
            ->whereNull('superseded_at')
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
                                        ->orWhereHas('owner', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
                                });
                        });
                    });
            })
            ->orderByDesc('assessed_at')
            ->orderByDesc('id')
            ->get();

        $rows = $assessments
            ->map(fn (Assessment $assessment): array => $this->row($assessment))
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
                'total_amount_cents' => $rows->sum('total_amount_cents'),
                'tax_amount_cents' => $rows->sum('tax_amount_cents'),
                'fee_amount_cents' => $rows->sum('fee_amount_cents'),
                'clearance_amount_cents' => $rows->sum('clearance_amount_cents'),
                'other_amount_cents' => $rows->sum('other_amount_cents'),
                'date_basis' => 'permit_application_year',
                'scope' => 'Current computed, non-superseded assessment snapshots for the selected application year.',
                'policy_note' => 'This report summarizes persisted assessment snapshots only. It does not recalculate legal liability, resolve superseded assessments, or authorize unresolved surcharge, interest, PIL, deficiency-tax, or rounding policy.',
            ],
            'rows' => $rows,
        ];
    }

    /** @return array<string, mixed> */
    private function row(Assessment $assessment): array
    {
        $permitApplication = $assessment->permitApplication;
        $business = $permitApplication->business;

        return [
            'assessment_id' => $assessment->id,
            'assessment_sequence' => $assessment->sequence,
            'assessment_status' => $assessment->status->value,
            'assessed_at' => $assessment->assessed_at?->toIso8601String(),
            'assessed_by' => $assessment->assessedBy?->name,
            'application_id' => $permitApplication->id,
            'application_number' => $permitApplication->application_number,
            'application_type' => $permitApplication->type->value,
            'application_status' => $permitApplication->status->value,
            'application_year' => $permitApplication->application_year,
            'business_id' => $business->id,
            'business_name' => $business->name,
            'trade_name' => $business->trade_name,
            'registration_number' => $business->registration_number,
            'owner_name' => $business->owner->name,
            'line_count' => $assessment->lines->count(),
            'line_of_businesses' => $assessment->lines
                ->map(fn ($line): ?string => $line->lineOfBusiness?->name)
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'tax_amount_cents' => $assessment->lines->where('category', FeeRuleCategory::Tax)->sum('amount_cents'),
            'fee_amount_cents' => $assessment->lines->where('category', FeeRuleCategory::Fee)->sum('amount_cents'),
            'clearance_amount_cents' => $assessment->lines->where('category', FeeRuleCategory::Clearance)->sum('amount_cents'),
            'other_amount_cents' => $assessment->lines->where('category', FeeRuleCategory::Other)->sum('amount_cents'),
            'total_amount_cents' => $assessment->total_amount_cents,
        ];
    }
}
