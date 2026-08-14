<?php

namespace App\Actions;

use App\Enums\PermitApplicationType;

class DescribeRetirementPolicyBoundary
{
    /**
     * @return array<string, mixed>|null
     */
    public function handle(PermitApplicationType|string $type): ?array
    {
        $type = $type instanceof PermitApplicationType ? $type : PermitApplicationType::from($type);

        if ($type !== PermitApplicationType::Retirement) {
            return null;
        }

        return [
            'status' => 'policy_boundary',
            'application_type' => PermitApplicationType::Retirement->value,
            'software_knows' => [
                'application_is_retirement' => true,
                'business_closure_may_be_relevant' => true,
                'final_liability_may_be_required' => true,
                'legal_retirement_effect_is_not_yet_automated' => true,
            ],
            'unresolved_policy' => [
                'retirement effective date and legal closure effect',
                'final tax, fee, surcharge, interest, and deficiency liability',
                'inspection or clearance requirements before retirement acceptance',
                'documentary requirements for business closure',
                'treatment of unpaid schedules, receipts, and prior permit artifacts',
                'public status meaning after retirement is recorded',
            ],
            'artifact_statement' => 'Retirement intake and assessment evidence can be recorded, but closure date, final liability, inspections, documentary requirements, unpaid obligations, and legal retirement effect remain unresolved.',
        ];
    }
}
