<?php

namespace App\Actions;

use App\Enums\AssessmentDecisionAction;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDeclaration;

class BuildExecutablePermitApplicationDocument
{
    /** @return array<string, mixed> */
    public function handle(PermitApplication $permitApplication): array
    {
        $application = PermitApplication::query()->with([
            'declaration',
            'business.owner',
            'lines.lineOfBusiness',
            'clearances',
            'assessments' => fn ($query) => $query->whereNull('superseded_at')->latest('sequence'),
            'assessments.lines.lineOfBusiness',
            'assessments.decision',
            'assessments.treasuryCounterCheck',
        ])->findOrFail($permitApplication->id);
        $declaration = $application->declaration;
        $assessment = $application->assessments->first();

        return [
            'identity' => [
                'application_id' => $application->id,
                'application_number' => $application->application_number,
                'tracking_reference' => $application->tracking_reference,
                'tax_year' => $application->application_year,
                'type' => $application->type->value,
                'status' => $application->status->value,
            ],
            'declaration' => [
                'state' => $declaration instanceof PermitApplicationDeclaration ? 'frozen' : 'draft',
                'declared_at' => $declaration?->declared_at->toIso8601String(),
                'snapshot_hash' => $declaration?->snapshot_hash,
                'snapshot' => $declaration instanceof PermitApplicationDeclaration
                    ? $declaration->snapshot
                    : data_get($application->metadata, 'applicant_declaration_draft'),
            ],
            'verification' => $this->verification($application),
            'page_2_assessment' => [
                'status' => 'unused_by_ipil',
                'statement' => 'Ipil does not use the Application Form Page 2 Assessment portion.',
                'populated_from_canonical_assessment' => false,
            ],
            'computation_assessment_slip' => $assessment === null ? null : [
                'assessment_id' => $assessment->id,
                'sequence' => $assessment->sequence,
                'status' => $assessment->status->value,
                'total_amount_cents' => $assessment->total_amount_cents,
                'line_count' => $assessment->lines()->count(),
                'statement' => 'Authoritative financial artifact: separate Computation/Assessment Slip',
            ],
            'treasury_counter_check' => $assessment?->treasuryCounterCheck === null ? null : [
                'result' => $assessment->treasuryCounterCheck->result?->value,
                'checked_at' => $assessment->treasuryCounterCheck->checked_at->toIso8601String(),
                'statement' => $assessment->treasuryCounterCheck->result?->value === 'no_correction'
                    ? 'Counter-check completed - no correction'
                    : 'Counter-check recorded against this Assessment',
            ],
            'municipal_treasurer' => $assessment?->decision === null ? null : [
                'action' => $assessment->decision->action->value,
                'decided_at' => $assessment->decision->decided_at->toIso8601String(),
                'exact_approval' => $assessment->decision->action === AssessmentDecisionAction::Approved,
                'assessment_snapshot_hash' => $assessment->decision->assessment_snapshot_hash,
            ],
            'permit' => [
                'status' => 'not_issued',
                'statement' => 'Permit not yet issued',
                'mayor_signature_authority' => 'unresolved',
            ],
            'post_payment_office_signatures' => [
                'status' => 'not_implemented',
                'statement' => 'Concerned-office verification and Page 2 signatures belong after payment; unavailable until that lifecycle is implemented.',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function verification(PermitApplication $application): array
    {
        $requirements = [
            ['description' => 'Barangay Clearance', 'issuing_office' => 'Barangay'],
            ['description' => 'Zoning Clearance (New/Renew)', 'issuing_office' => 'MPDO/Zoning'],
            ['description' => 'Building Permit/Occupancy Permit (New/Renew)', 'issuing_office' => 'Mun. Engineering'],
        ];

        return array_map(function (array $requirement) use ($application): array {
            $needle = mb_strtolower(strtok($requirement['description'], ' ('));
            $clearance = $application->clearances->first(fn ($candidate): bool => str_contains(mb_strtolower($candidate->label), $needle));

            return [
                ...$requirement,
                'canonical_clearance_id' => $clearance?->id,
                'status' => $clearance?->status->value ?? 'not_available',
                'date_issued' => $clearance?->completed_at?->toDateString(),
                'verified_by' => $clearance?->completed_by_id,
                'recommending_approval' => null,
            ];
        }, $requirements);
    }
}
