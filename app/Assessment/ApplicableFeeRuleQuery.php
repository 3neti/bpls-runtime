<?php

namespace App\Assessment;

use App\Enums\FeeRuleScope;
use App\Enums\PermitApplicationType;
use App\Models\FeeRule;
use App\Models\PermitApplication;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class ApplicableFeeRuleQuery
{
    /** @return Collection<int, FeeRule> */
    public function forPermitApplication(PermitApplication $permitApplication): Collection
    {
        $permitApplication->loadMissing('lines');

        return $this->forApplicationFacts(
            applicationType: $permitApplication->type,
            applicationYear: $permitApplication->application_year,
            lineOfBusinessIds: $permitApplication->lines
                ->pluck('line_of_business_id')
                ->filter()
                ->unique()
                ->values(),
        );
    }

    /**
     * @param  SupportCollection<int, int>|array<int, int>  $lineOfBusinessIds
     * @return Collection<int, FeeRule>
     */
    public function forApplicationFacts(
        PermitApplicationType $applicationType,
        int $applicationYear,
        SupportCollection|array $lineOfBusinessIds = [],
    ): Collection {
        $asOfDate = "{$applicationYear}-01-01";
        $applicableLineOfBusinessIds = collect($lineOfBusinessIds)->filter()->unique()->values();

        return FeeRule::query()
            ->with(['ranges', 'currentReconciliation'])
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $asOfDate)
            ->where(function ($query) use ($asOfDate): void {
                $query
                    ->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $asOfDate);
            })
            ->where(function ($query) use ($applicableLineOfBusinessIds): void {
                $query
                    ->where('scope', FeeRuleScope::Application->value)
                    ->orWhereIn('line_of_business_id', $applicableLineOfBusinessIds);
            })
            ->orderBy('code')
            ->get()
            ->filter(fn (FeeRule $feeRule): bool => $this->appliesToApplicationType($feeRule, $applicationType))
            ->values();
    }

    private function appliesToApplicationType(FeeRule $feeRule, PermitApplicationType $applicationType): bool
    {
        $applicationTypes = $feeRule->metadata['application_types'] ?? null;

        if ($applicationTypes === null) {
            return true;
        }

        if (! is_array($applicationTypes)) {
            return false;
        }

        return in_array($applicationType->value, $applicationTypes, true);
    }
}
