<?php

namespace App\Actions;

use App\Enums\FeeDeterminationChannel;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleScope;
use App\Models\BploRoutingWork;
use App\Models\FeeRule;
use App\Models\PermitApplication;

final class ProvisionalMenroFeeDeterminationProposal
{
    public const FeeRuleId = 176;

    public const FeeCode = 'IPIL-LEGACY-98CDCAD9D28055FB';

    public const Reason = 'Provisional Gate 10 synthetic-UAT determination pending Ipil municipal confirmation';

    /** @return array<string, mixed>|null */
    public function forApplication(PermitApplication $application): ?array
    {
        if ($application->application_year !== 2026
            || $application->type->value !== 'new'
            || data_get($application->metadata, 'nelson_reconciliation_v1.commissioned_path') !== true
            || ! $this->hasMenroRoutingWork($application)) {
            return null;
        }

        $area = $application->business?->business_area_square_meters;
        if (! is_numeric($area) || (float) $area <= 0) {
            return null;
        }

        $basis = (int) round((float) $area * 100);
        $feeRule = FeeRule::query()
            ->with(['catalogVersion', 'ranges'])
            ->whereKey(self::FeeRuleId)
            ->where('code', self::FeeCode)
            ->where('is_active', true)
            ->whereNotNull('fee_catalog_version_id')
            ->where('scope', FeeRuleScope::Application->value)
            ->where('determination_channel', FeeDeterminationChannel::ConcernedOfficePaymentOrder->value)
            ->where('calculation_type', FeeRuleCalculationType::Range->value)
            ->where('basis', 'business_area_square_meters')
            ->whereDate('effective_from', '<=', $application->application_year.'-12-31')
            ->where(fn ($query) => $query
                ->whereNull('effective_until')
                ->orWhereDate('effective_until', '>=', $application->application_year.'-01-01'))
            ->first();
        if (! $feeRule instanceof FeeRule
            || data_get($feeRule->metadata, 'responsible_office_code') !== 'menro'
            || data_get($feeRule->metadata, 'basis_unit') !== 'centi_square_meter') {
            return null;
        }

        $matchingRanges = $feeRule->ranges
            ->filter(fn ($range): bool => $range->min_basis_cents <= $basis
                && ($range->max_basis_cents === null || $range->max_basis_cents >= $basis))
            ->values();
        if ($matchingRanges->count() !== 1) {
            return null;
        }

        $range = $matchingRanges->sole();
        $scheduleVersion = $feeRule->catalogVersion->code;
        $sourceEvidence = $this->sourceEvidence($feeRule->metadata);
        if (trim($scheduleVersion) === '' || $sourceEvidence === null) {
            return null;
        }

        return [
            'scope' => 'application',
            'scope_label' => 'Application',
            'fee_rule_id' => self::FeeRuleId,
            'source_identity' => self::FeeRuleId,
            'code' => self::FeeCode,
            'basis' => 'business_area_square_meters',
            'application_area_square_meters' => $basis % 100 === 0 ? intdiv($basis, 100) : $basis / 100,
            'calculation_basis_centi_square_meters' => $basis,
            'operative_range_min_centi_square_meters' => $range->min_basis_cents,
            'operative_range_max_centi_square_meters' => $range->max_basis_cents,
            'amount_minor' => $range->amount_cents,
            'schedule_version' => $scheduleVersion,
            'source_evidence' => $sourceEvidence,
            'classification' => 'PROVISIONAL_UAT_ONLY',
            'production_authority' => false,
            'reason' => self::Reason,
            'actor_statement' => 'Authenticated MENRO Officer will be recorded as the determining actor.',
            'timestamp_statement' => 'Server time will be recorded when this action is submitted.',
            'warning' => 'Synthetic-UAT evidence only; this is not municipal policy.',
        ];
    }

    /** @return array<string, mixed> */
    public function facts(PermitApplication $application): array
    {
        $proposal = $this->forApplication($application);
        if ($proposal === null) {
            return [];
        }

        return collect($proposal)->except([
            'scope_label', 'source_identity', 'actor_statement', 'timestamp_statement', 'warning',
        ])->all();
    }

    private function hasMenroRoutingWork(PermitApplication $application): bool
    {
        return BploRoutingWork::query()
            ->where('office_code', 'menro')
            ->whereHas('determination', fn ($query) => $query->where('permit_application_id', $application->id))
            ->exists();
    }

    /** @param array<string, mixed>|null $metadata */
    private function sourceEvidence(?array $metadata): ?string
    {
        $reference = data_get($metadata, 'evidence_reference');
        if (! is_string($reference) || trim($reference) === '') {
            return null;
        }

        return str($reference)->before(' ·')->trim()->toString();
    }
}
