<?php

namespace App\Actions;

use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Models\PermitApplication;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class SubmitCitizenPermitApplication
{
    public function handle(PermitApplication $permitApplication, User $submittedBy): PermitApplication
    {
        return DB::transaction(function () use ($permitApplication, $submittedBy): PermitApplication {
            $application = PermitApplication::query()
                ->with('business')
                ->lockForUpdate()
                ->findOrFail($permitApplication->id);

            if ($application->submitted_by_id !== $submittedBy->id) {
                throw new DomainException('This permit application draft does not belong to the authenticated citizen.');
            }

            if (
                $submittedBy->business_owner_id === null
                || $application->business->business_owner_id !== $submittedBy->business_owner_id
            ) {
                throw new DomainException('The application business is not linked to the citizen registry identity.');
            }

            if ($this->wasAlreadySubmitted($application)) {
                return $application->load(['business.owner', 'lines.lineOfBusiness']);
            }

            if ($application->status !== PermitApplicationStatus::Draft) {
                throw new DomainException('Only a citizen draft may be formally submitted.');
            }

            if ($application->type !== PermitApplicationType::New) {
                throw new DomainException('Only a new permit application draft may be submitted through citizen intake.');
            }

            if ($application->application_number !== null || $application->assessments()->exists()) {
                throw new DomainException('This application has already entered a later municipal processing step.');
            }

            $occurredAt = now();
            $metadata = $application->metadata ?? [];
            $metadata['citizen_submission'] = [
                'actor_id' => $submittedBy->id,
                'submitted_at' => $occurredAt->toIso8601String(),
                'meaning' => 'Citizen formally submitted the draft to the municipal processing queue.',
            ];
            $metadata['municipal_receipt'] = [
                'received_at' => $occurredAt->toIso8601String(),
                'processing_status' => PermitApplicationStatus::Assessment->value,
                'meaning' => 'Municipality received the application into its assessment queue.',
            ];
            $metadata['submission_policy_boundary'] = [
                'official_application_number_assigned' => false,
                'documentary_sufficiency_determined' => false,
                'payment_mode_committed' => false,
            ];
            $metadata['status_history'] = [
                ...($metadata['status_history'] ?? []),
                [
                    'from' => PermitApplicationStatus::Draft->value,
                    'to' => PermitApplicationStatus::Assessment->value,
                    'actor_id' => $submittedBy->id,
                    'reason' => 'Citizen submitted; municipality received the application into the processing queue.',
                    'occurred_at' => $occurredAt->toIso8601String(),
                ],
            ];

            $application->forceFill([
                'status' => PermitApplicationStatus::Assessment,
                'submitted_at' => $occurredAt,
                'application_number' => null,
                'metadata' => $metadata,
            ])->save();

            return $application->refresh()->load(['business.owner', 'lines.lineOfBusiness']);
        });
    }

    private function wasAlreadySubmitted(PermitApplication $application): bool
    {
        return $application->status === PermitApplicationStatus::Assessment
            && $application->submitted_at !== null
            && data_get($application->metadata, 'citizen_submission.submitted_at') !== null
            && data_get($application->metadata, 'municipal_receipt.received_at') !== null;
    }
}
