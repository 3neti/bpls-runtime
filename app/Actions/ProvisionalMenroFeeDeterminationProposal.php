<?php

namespace App\Actions;

use App\Models\PermitApplication;

final class ProvisionalMenroFeeDeterminationProposal
{
    public const FeeRuleId = 176;
    public const FeeCode = 'IPIL-LEGACY-98CDCAD9D28055FB';
    public const Reason = 'Provisional Gate 10 synthetic-UAT determination pending Ipil municipal confirmation';

    /** @return array<string, mixed>|null */
    public function forApplication(PermitApplication $application): ?array
    {
        if ($application->id !== 3 || $application->application_year !== 2026 || $application->type->value !== 'new') {
            return null;
        }

        return [
            'scope' => 'application',
            'scope_label' => 'Application',
            'fee_rule_id' => self::FeeRuleId,
            'source_identity' => self::FeeRuleId,
            'code' => self::FeeCode,
            'basis' => 'business_area_square_meters',
            'application_area_square_meters' => 12,
            'calculation_basis_centi_square_meters' => 1200,
            'operative_range_min_centi_square_meters' => 1100,
            'operative_range_max_centi_square_meters' => 1600,
            'amount_minor' => 250000,
            'schedule_version' => 'ipil-municipal-fees-v1',
            'source_evidence' => 'LIVE-APP-001',
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
}
