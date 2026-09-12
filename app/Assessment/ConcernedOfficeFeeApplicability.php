<?php

namespace App\Assessment;

use App\Enums\FeeCatalogVersionStatus;
use App\Enums\FeeDeterminationChannel;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Models\FeeRule;
use App\Models\PermitApplication;

final class ConcernedOfficeFeeApplicability
{
    public function matches(FeeRule $fee, PermitApplication $application, string $officeCode): bool
    {
        $fee->loadMissing(['catalogVersion', 'officeAssignments', 'lineOfBusinesses']);
        if (! $fee->is_active || $fee->category === FeeRuleCategory::Tax
            || $fee->determination_channel !== FeeDeterminationChannel::ConcernedOfficePaymentOrder
            || $fee->effective_from->year > $application->application_year
            || ($fee->effective_until !== null && $fee->effective_until->year < $application->application_year)) {
            return false;
        }
        $version = $fee->catalogVersion;
        if ($fee->fee_catalog_version_id !== null && ($version === null
            || $version->status !== FeeCatalogVersionStatus::Active
            || $version->effective_from->year > $application->application_year
            || ($version->effective_until !== null && $version->effective_until->year < $application->application_year))) {
            return false;
        }
        $types = data_get($fee->metadata, 'application_types');
        if ($types !== null && (! is_array($types) || ($types !== [] && ! in_array($application->type->value, $types, true)))) {
            return false;
        }
        $offices = $fee->officeAssignments->pluck('office_code');
        $configuredOffice = data_get($fee->metadata, 'responsible_office_code');
        if (($offices->isNotEmpty() && ! $offices->contains($officeCode))
            || (is_string($configuredOffice) && $configuredOffice !== $officeCode)) {
            return false;
        }
        // This commission resolves Health applicability only; other offices retain their established scope policy.
        if ($officeCode !== 'health' || $fee->scope === FeeRuleScope::Application) {
            return true;
        }
        $application->loadMissing(['lines', 'treasuryLineOfBusinessAssignments']);
        $assigned = $application->lines->pluck('line_of_business_id')
            ->merge($application->treasuryLineOfBusinessAssignments->whereNull('removed_at')->pluck('line_of_business_id'))
            ->filter()->unique();

        return $assigned->contains($fee->line_of_business_id)
            || $fee->lineOfBusinesses->pluck('id')->intersect($assigned)->isNotEmpty();
    }
}
