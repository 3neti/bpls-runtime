<?php

namespace App\Assessment;

use App\Models\FeeRule;
use App\Models\LifecycleCleanroomRun;
use App\Models\PermitApplication;

final class TreasuryFeeResolution
{
    /** @var array<string, LifecycleCleanroomRun|null> */
    private array $runs = [];

    public function message(FeeRule $fee): string
    {
        return $fee->code === 'IPIL-LEGACY-5F028B76EEBEF485'
            ? 'TBD — enterprise classification required'
            : 'TBD — municipal determination required';
    }

    public function unresolved(FeeRule $fee, PermitApplication $application): bool
    {
        if ($fee->basis !== 'legacy_unresolved') {
            return false;
        }

        return ! $this->acceptedHistoricalReplay($fee, $application);
    }

    private function acceptedHistoricalReplay(FeeRule $fee, PermitApplication $application): bool
    {
        if ($application->application_year !== 2025 || $fee->code !== 'IPIL-LEGACY-5F028B76EEBEF485') {
            return false;
        }
        $context = data_get($application->metadata, 'lifecycle_cleanroom');
        $evaluation = data_get($application->metadata, 'business_permit_evaluation');
        if (! is_array($context) || ! is_array($evaluation)
            || ($context['semantic_classification'] ?? null) !== 'synthetic_only'
            || ($context['production_liability'] ?? null) !== false
            || ($evaluation['semantic_classification'] ?? null) !== 'provisional_uat'
            || ($evaluation['production_liability'] ?? null) !== false
            || ! is_string($context['run_id'] ?? null)
            || ! is_string($context['scenario_id'] ?? null)
            || ($evaluation['cleanroom_run_id'] ?? null) !== $context['run_id']
            || ($evaluation['scenario_id'] ?? null) !== $context['scenario_id']
            || data_get($context, 'source_specimen.id') !== LifecycleCleanroomRun::SourceSpecimenCal2026001New2025) {
            return false;
        }
        $key = $context['run_id'];
        if (! array_key_exists($key, $this->runs)) {
            $this->runs[$key] = LifecycleCleanroomRun::query()->where('public_id', $key)->first();
        }
        $run = $this->runs[$key];

        return $run !== null && $run->new_application_id === $application->id
            && data_get($run->actor_manifest, 'semantic_classification') === 'synthetic_only'
            && data_get($run->actor_manifest, 'production_liability') === false
            && data_get($run->actor_manifest, 'source_specimen.id') === LifecycleCleanroomRun::SourceSpecimenCal2026001New2025;
    }
}
