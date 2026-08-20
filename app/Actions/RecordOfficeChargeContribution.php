<?php

namespace App\Actions;

use App\Enums\StakeholderPreviewPersona;
use App\Models\OfficeChargeContribution;
use App\Models\PermitApplication;
use App\Models\User;
use App\StakeholderPreview\StakeholderPreviewSafety;
use DomainException;
use Illuminate\Support\Facades\DB;

class RecordOfficeChargeContribution
{
    public function __construct(private readonly StakeholderPreviewSafety $safety) {}

    public function handle(PermitApplication $permitApplication, User $actor, bool $isApplicable, ?int $amountCents): OfficeChargeContribution
    {
        return DB::transaction(function () use ($permitApplication, $actor, $isApplicable, $amountCents): OfficeChargeContribution {
            $this->safety->ensureEnabled();
            $persona = $this->safety->personaFor($actor);

            if (! $persona instanceof StakeholderPreviewPersona || ! $persona->isConcernedOffice()) {
                throw new DomainException('This preview perspective cannot submit a concerned-office charge.');
            }

            if ($permitApplication->assessments()->whereNull('superseded_at')->whereHas('decision')->exists()
                || $permitApplication->paymentSchedules()->exists()) {
                throw new DomainException('This office charge is locked because Treasury approval or payment processing has begun. Recompute through a fresh preview application.');
            }

            if ($isApplicable && ($amountCents === null || $amountCents < 0)) {
                throw new DomainException('Enter the office-assessed amount before submitting this applicable office review.');
            }

            $officeCode = $persona->officeCode();
            $office = config('stakeholder_preview.weekend_hypothesis.office_charges.'.$officeCode);

            if (! is_array($office) || ! is_string($office['label'] ?? null)) {
                throw new DomainException('The preview office is not part of the configured scenario.');
            }

            $submittedAt = now();
            $contribution = OfficeChargeContribution::query()->updateOrCreate(
                ['permit_application_id' => $permitApplication->id, 'office_code' => $officeCode],
                [
                    'submitted_by_id' => $actor->id,
                    'office_label' => $office['label'],
                    'is_applicable' => $isApplicable,
                    'status' => 'approved',
                    'amount_cents' => $isApplicable ? $amountCents : null,
                    'submitted_at' => $submittedAt,
                    'semantic_classification' => 'provisional_uat',
                    'source_snapshot' => [
                        'semantic_classification' => 'provisional_uat',
                        'evidence_strength' => 'board_strong_operational_recollection',
                        'scenario_scope' => StakeholderPreviewSafety::Profile,
                        'office_code' => $officeCode,
                        'staff_action' => true,
                        'automatic_calculation' => false,
                        'actor_id' => $actor->id,
                        'submitted_at' => $submittedAt->toIso8601String(),
                    ],
                ],
            );

            $metadata = $permitApplication->metadata ?? [];
            $metadata['provisional_uat_workflow'] = [
                'semantic_classification' => 'provisional_uat',
                'profile' => StakeholderPreviewSafety::Profile,
                'applicable_office_codes' => array_keys(config('stakeholder_preview.weekend_hypothesis.office_charges', [])),
                'generalizes_municipal_policy' => false,
            ];
            $permitApplication->update(['metadata' => $metadata]);

            return $contribution->refresh();
        });
    }
}
