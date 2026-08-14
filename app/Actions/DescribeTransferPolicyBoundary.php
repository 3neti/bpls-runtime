<?php

namespace App\Actions;

use App\Enums\PermitApplicationType;

class DescribeTransferPolicyBoundary
{
    /**
     * @return array<string, mixed>|null
     */
    public function handle(PermitApplicationType|string $type): ?array
    {
        $type = $type instanceof PermitApplicationType ? $type : PermitApplicationType::from($type);

        if ($type !== PermitApplicationType::Transfer) {
            return null;
        }

        return [
            'status' => 'policy_boundary',
            'application_type' => PermitApplicationType::Transfer->value,
            'software_knows' => [
                'application_is_transfer' => true,
                'location_transfer_may_be_relevant' => true,
                'ownership_transfer_may_be_relevant' => true,
                'legal_effect_is_not_yet_automated' => true,
            ],
            'unresolved_policy' => [
                'location-transfer documentary requirements',
                'ownership-transfer documentary requirements',
                'whether transfer terminates, supersedes, or preserves the prior permit',
                'assessment basis for transferred business or location',
                'clearance requirements before transfer acceptance',
                'effective date and public meaning of transferred permit records',
            ],
            'artifact_statement' => 'Transfer intake and assessment evidence can be recorded, but ownership transfer, location transfer, supersession, assessment basis, clearance prerequisites, and legal effect remain unresolved.',
        ];
    }
}
