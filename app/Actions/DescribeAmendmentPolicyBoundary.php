<?php

namespace App\Actions;

use App\Enums\PermitApplicationType;

class DescribeAmendmentPolicyBoundary
{
    /**
     * @return array<string, mixed>|null
     */
    public function handle(PermitApplicationType|string $type): ?array
    {
        $type = $type instanceof PermitApplicationType ? $type : PermitApplicationType::from($type);

        if ($type !== PermitApplicationType::Amendment) {
            return null;
        }

        return [
            'status' => 'policy_boundary',
            'application_type' => PermitApplicationType::Amendment->value,
            'software_knows' => [
                'application_is_amendment' => true,
                'business_identity_change_may_be_relevant' => true,
                'amended_fields_are_not_yet_structured' => true,
            ],
            'unresolved_policy' => [
                'which business changes qualify as amendment instead of new application',
                'ownership, business name, and management-change legal effect',
                'location-transfer overlap with transfer lifecycle',
                'amendment fee and assessment basis',
                'documentary requirements for changed business facts',
                'historical permit relationship and supersession behavior',
            ],
            'artifact_statement' => 'Amendment intake and assessment evidence can be recorded, but amended-field semantics, legal effect, transfer overlap, fee basis, and supersession policy remain unresolved.',
        ];
    }
}
