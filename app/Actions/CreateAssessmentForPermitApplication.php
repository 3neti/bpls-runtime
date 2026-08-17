<?php

namespace App\Actions;

use App\Assessment\AssessmentCalculator;
use App\Enums\AssessmentStatus;
use App\Enums\FeeRuleScope;
use App\Enums\PermitApplicationStatus;
use App\Models\Assessment;
use App\Models\FeeRule;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class CreateAssessmentForPermitApplication
{
    public function __construct(private AssessmentCalculator $calculator) {}

    public function handle(PermitApplication $permitApplication, ?User $assessedBy = null): Assessment
    {
        return DB::transaction(function () use ($permitApplication, $assessedBy): Assessment {
            $permitApplication->loadMissing(['business', 'lines.lineOfBusiness']);

            if ($permitApplication->isHistoricalEvidenceOnly()) {
                throw new LogicException("Historical evidence application [{$permitApplication->id}] cannot enter operational assessment.");
            }

            $permitApplication->assessments()
                ->whereNull('superseded_at')
                ->update(['superseded_at' => now()]);

            $assessment = $permitApplication->assessments()->create([
                'assessed_by_id' => $assessedBy?->id,
                'sequence' => ($permitApplication->assessments()->max('sequence') ?? 0) + 1,
                'status' => AssessmentStatus::Computed,
                'assessed_at' => now(),
                'source_snapshot' => $this->sourceSnapshot($permitApplication),
            ]);

            $feeRules = $this->feeRulesFor($permitApplication);

            $this->createApplicationScopedLines($assessment, $feeRules);
            $this->createLineOfBusinessScopedLines($assessment, $permitApplication, $feeRules);

            $assessment->update([
                'total_amount_cents' => $assessment->lines()->sum('amount_cents'),
            ]);

            $permitApplication->update([
                'status' => PermitApplicationStatus::Assessment,
                'assessed_at' => $assessment->assessed_at,
            ]);

            return $assessment->load('lines');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceSnapshot(PermitApplication $permitApplication): array
    {
        return [
            'permit_application_id' => $permitApplication->id,
            'application_number' => $permitApplication->application_number,
            'type' => $permitApplication->type->value,
            'application_year' => $permitApplication->application_year,
            'business_id' => $permitApplication->business_id,
            'business_name' => $permitApplication->business->name,
            'line_ids' => $permitApplication->lines->pluck('id')->values()->all(),
        ];
    }

    /**
     * @return Collection<int, FeeRule>
     */
    private function feeRulesFor(PermitApplication $permitApplication): Collection
    {
        $asOfDate = "{$permitApplication->application_year}-01-01";
        $lineOfBusinessIds = $permitApplication->lines
            ->pluck('line_of_business_id')
            ->filter()
            ->unique()
            ->values();

        return FeeRule::query()
            ->with(['ranges', 'currentReconciliation'])
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $asOfDate)
            ->where(function ($query) use ($asOfDate): void {
                $query
                    ->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $asOfDate);
            })
            ->where(function ($query) use ($lineOfBusinessIds): void {
                $query
                    ->where('scope', FeeRuleScope::Application->value)
                    ->orWhereIn('line_of_business_id', $lineOfBusinessIds);
            })
            ->orderBy('code')
            ->get()
            ->filter(fn (FeeRule $feeRule): bool => $this->appliesToPermitApplicationType($feeRule, $permitApplication))
            ->values();
    }

    private function appliesToPermitApplicationType(FeeRule $feeRule, PermitApplication $permitApplication): bool
    {
        $applicationTypes = $feeRule->metadata['application_types'] ?? null;

        if ($applicationTypes === null) {
            return true;
        }

        if (! is_array($applicationTypes)) {
            return false;
        }

        return in_array($permitApplication->type->value, $applicationTypes, true);
    }

    /**
     * @param  Collection<int, FeeRule>  $feeRules
     */
    private function createApplicationScopedLines(Assessment $assessment, Collection $feeRules): void
    {
        $feeRules
            ->where('scope', FeeRuleScope::Application)
            ->each(fn (FeeRule $feeRule) => $this->createAssessmentLine($assessment, $feeRule));
    }

    /**
     * @param  Collection<int, FeeRule>  $feeRules
     */
    private function createLineOfBusinessScopedLines(Assessment $assessment, PermitApplication $permitApplication, Collection $feeRules): void
    {
        $lineRules = $feeRules->where('scope', FeeRuleScope::LineOfBusiness);

        $permitApplication->lines->each(function (PermitApplicationLine $applicationLine) use ($assessment, $lineRules): void {
            $lineRules
                ->where('line_of_business_id', $applicationLine->line_of_business_id)
                ->each(fn (FeeRule $feeRule) => $this->createAssessmentLine($assessment, $feeRule, $applicationLine));
        });
    }

    private function createAssessmentLine(Assessment $assessment, FeeRule $feeRule, ?PermitApplicationLine $applicationLine = null): void
    {
        $calculation = $this->calculator->calculate($feeRule, $applicationLine);

        $assessment->lines()->create([
            'permit_application_line_id' => $applicationLine?->id,
            'fee_rule_id' => $feeRule->id,
            'line_of_business_id' => $applicationLine instanceof PermitApplicationLine
                ? $applicationLine->line_of_business_id
                : $feeRule->line_of_business_id,
            'code' => $feeRule->code,
            'name' => $feeRule->name,
            'category' => $feeRule->category,
            'calculation_type' => $feeRule->calculation_type,
            'basis' => $feeRule->basis,
            'basis_amount_cents' => $calculation['basis_amount_cents'],
            'amount_cents' => $calculation['amount_cents'],
            'legal_basis' => $feeRule->legal_basis,
            'rule_snapshot' => $calculation['rule_snapshot'],
        ]);
    }
}
