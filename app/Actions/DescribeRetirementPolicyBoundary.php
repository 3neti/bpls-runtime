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
                'sworn_closure_statement_has_a_stated_thirty_day_period' => true,
                'non_operation_requires_municipal_verification' => true,
                'final_liability_is_required_before_official_retirement' => true,
                'permit_surrender_and_cancellation_are_described' => true,
                'legal_retirement_effect_is_not_yet_automated' => true,
            ],
            'legal_evidence' => [
                'source_id' => 'LEGAL-MRC-001',
                'section_references' => [
                    'Section 2E.04(f) retirement provisions',
                    'Section 2E.04 retirement procedures (a)-(c)',
                ],
                'ordinance_facts' => [
                    'A sworn current-year gross-sales or receipts statement is described as due within 30 days after closure.',
                    'The Treasurer assigns an inspector to verify that the business is no longer operating and may recommend disapproval when continuation under another name, manager, or owner is found.',
                    'Final tax liability must be settled and the permit surrendered, cancelled, and recorded before the business is considered officially retired or terminated.',
                ],
                'execution_status' => 'recorded_non_executable',
            ],
            'unresolved_policy' => [
                'authority and evidence for the actual cessation date and legal retirement effect',
                'sworn-statement form, submission channel, sufficiency, and late handling',
                'inspection assignment, findings, disapproval, notice, response, and appeal procedure',
                'final tax, fee, surcharge, interest, PIL, and deficiency computation',
                'treatment of unpaid schedules, receipts, and prior permit artifacts',
                'permit surrender, cancellation record, QR meaning, and public status after retirement',
            ],
            'artifact_statement' => 'The ordinance describes closure evidence, inspection, final settlement, and permit cancellation before official retirement. Intake and assessment evidence can be recorded, but those authority decisions and calculations remain non-executable pending municipal reconciliation.',
        ];
    }
}
