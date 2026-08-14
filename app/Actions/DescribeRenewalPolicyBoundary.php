<?php

namespace App\Actions;

use App\Enums\PermitApplicationType;

class DescribeRenewalPolicyBoundary
{
    /**
     * @return array<string, mixed>|null
     */
    public function handle(PermitApplicationType|string $type): ?array
    {
        $type = $type instanceof PermitApplicationType ? $type : PermitApplicationType::from($type);

        if ($type !== PermitApplicationType::Renewal) {
            return null;
        }

        return [
            'status' => 'policy_boundary',
            'application_type' => PermitApplicationType::Renewal->value,
            'software_knows' => [
                'application_is_renewal' => true,
                'gross_receipts_basis_is_relevant' => true,
                'capital_investment_basis_is_not_sufficient_for_full_policy' => true,
            ],
            'unresolved_policy' => [
                'complete Revenue Code renewal rate catalog',
                'late-payment surcharge and interest timing',
                'PIL applicability and calculation',
                'deficiency tax behavior when reported receipts differ',
                'renewal documentary requirements and cutoff dates',
                'production fee configuration and historical permit basis',
            ],
            'artifact_statement' => 'Renewal intake and assessment evidence can be recorded, but full renewal tax, surcharge, PIL, deficiency, and production-history policy remain unresolved.',
        ];
    }
}
