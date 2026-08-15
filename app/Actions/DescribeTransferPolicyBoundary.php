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
                'location_and_ownership_transfer_are_distinct' => true,
                'tax_paid_period_is_relevant_to_location_transfer' => true,
                'ownership_change_is_described_as_termination' => true,
                'new_owner_may_require_a_new_mayors_permit' => true,
                'legal_effect_is_not_yet_automated' => true,
            ],
            'legal_evidence' => [
                'source_id' => 'LEGAL-MRC-001',
                'section_references' => [
                    'Section 2E.04(g)',
                    'Section 2E.04(f) retirement procedures',
                ],
                'ordinance_facts' => [
                    'A tax-paid business may continue at another location within the Municipality without additional business tax for the same paid period.',
                    'A change in ownership, management, or business name is described as termination rather than a simple location transfer.',
                    'A new owner by sale or conveyance is described as liable for the applicable tax or fee and required to secure a new Mayor’s Permit.',
                ],
                'execution_status' => 'recorded_non_executable',
            ],
            'unresolved_policy' => [
                'proof of paid business tax and identity of the covered paid period',
                'location-transfer documentary, clearance, and inspection requirements',
                'ownership-transfer conveyance and successor-identity requirements',
                'whether transfer terminates, supersedes, or preserves the prior permit',
                'relationship among transfer, retirement, amendment, renewal, and a new permit',
                'assessment basis and liability allocation for the predecessor and successor',
                'effective date and public meaning of transferred permit records',
            ],
            'artifact_statement' => 'Ordinance evidence distinguishes a paid-period location transfer from ownership transfer. Transfer intake and assessment evidence can be recorded, but successor liability, prior-permit effect, requirements, effective date, and legal effect remain non-executable pending municipal reconciliation.',
        ];
    }
}
