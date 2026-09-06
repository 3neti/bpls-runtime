<?php

namespace App\Actions;

use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Models\PermitApplication;
use App\Models\User;
use App\Notifications\PermitApplicationReceived;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubmitCitizenPermitApplication
{
    public function __construct(
        private readonly FreezePermitApplicationDeclaration $freezeDeclaration,
        private readonly PermitApplicationStatusMutation $statusMutation,
        private readonly ArmBploRoutingSentinel $armRoutingSentinel,
        private readonly CaptureSignatureEvidence $captureSignatureEvidence,
    ) {}

    public function handle(
        PermitApplication $permitApplication,
        User $submittedBy,
        bool $undertakingAccepted,
        ?UploadedFile $signatureFacsimile = null,
    ): PermitApplication {
        return DB::transaction(function () use ($permitApplication, $submittedBy, $undertakingAccepted, $signatureFacsimile): PermitApplication {
            if (! $undertakingAccepted) {
                throw new DomainException('Confirm the Oath of Undertaking before submitting this application.');
            }

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

            $commissionedPath = data_get($application->metadata, 'nelson_reconciliation_v1.commissioned_path') === true;
            if ($commissionedPath && ! $signatureFacsimile instanceof UploadedFile) {
                throw new DomainException('Capture the applicant signature facsimile before lodging this Application.');
            }

            $occurredAt = now();
            $trackingReference = 'SUB-'.Str::upper((string) Str::ulid());
            $metadata = $application->metadata ?? [];
            $metadata['citizen_submission'] = [
                'actor_id' => $submittedBy->id,
                'submitted_at' => $occurredAt->toIso8601String(),
                'meaning' => 'Citizen formally submitted the draft to the municipal processing queue.',
            ];
            $metadata['undertaking_confirmation'] = [
                'schema_version' => 'bpls.undertaking-confirmation.v1',
                'accepted' => true,
                'actor_id' => $submittedBy->id,
                'accepted_at' => $occurredAt->toIso8601String(),
                'applicant_printed_name' => data_get($metadata, 'applicant_declaration_draft.undertaking.applicant_printed_name'),
                'position_title' => data_get($metadata, 'applicant_declaration_draft.undertaking.position_title'),
            ];
            $metadata['municipal_receipt'] = [
                'received_at' => $occurredAt->toIso8601String(),
                'processing_status' => PermitApplicationStatus::Assessment->value,
                'meaning' => 'Municipality received the application into its assessment queue.',
            ];
            $metadata['submission_policy_boundary'] = [
                'official_application_number_assigned' => false,
                'tracking_reference_is_official_number' => false,
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

            $declaration = $this->freezeDeclaration->handle($application, $submittedBy);
            $signatureEvidence = $signatureFacsimile instanceof UploadedFile
                ? $this->captureSignatureEvidence->handle(
                    $declaration,
                    $submittedBy,
                    'applicant_lodging',
                    $signatureFacsimile,
                )
                : null;
            $metadata['applicant_declaration'] = [
                'id' => $declaration->id,
                'snapshot_hash' => $declaration->snapshot_hash,
                'frozen_at' => $declaration->declared_at->toIso8601String(),
                'immutable' => true,
            ];
            $metadata['undertaking_confirmation']['declaration_snapshot_hash'] = $declaration->snapshot_hash;
            $metadata['undertaking_confirmation']['applicant_printed_name'] = data_get($declaration->snapshot, 'undertaking.applicant_printed_name');
            $metadata['undertaking_confirmation']['position_title'] = data_get($declaration->snapshot, 'undertaking.position_title');
            if ($signatureEvidence !== null) {
                $metadata['undertaking_confirmation']['signature_evidence'] = [
                    'id' => $signatureEvidence->id,
                    'digest' => $signatureEvidence->evidence_digest,
                    'method' => $signatureEvidence->method,
                    'purpose' => $signatureEvidence->purpose,
                ];
            }

            $this->statusMutation->persistStatusConsequence($application, PermitApplicationStatus::Assessment, [
                'submitted_at' => $occurredAt,
                'application_number' => null,
                'tracking_reference' => $trackingReference,
                'metadata' => $metadata,
            ]);

            if (! $commissionedPath) {
                $this->armRoutingSentinel->handle($application);
            }

            $submittedBy->notify(new PermitApplicationReceived(
                permitApplicationId: $application->id,
                trackingReference: $trackingReference,
                businessName: $application->business->name,
                receivedAt: $occurredAt,
            ));

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
