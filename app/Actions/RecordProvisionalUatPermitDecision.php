<?php

namespace App\Actions;

use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitClearanceStatus;
use App\Enums\StakeholderPreviewPersona;
use App\Models\PermitApplication;
use App\Models\ProvisionalUatPermitCompletion;
use App\Models\User;
use App\StakeholderPreview\StakeholderPreviewSafety;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordProvisionalUatPermitDecision
{
    public function __construct(private readonly StakeholderPreviewSafety $safety) {}

    public function handle(PermitApplication $permitApplication, User $actor, string $decision, ?string $reason = null): ProvisionalUatPermitCompletion
    {
        return DB::transaction(function () use ($permitApplication, $actor, $decision, $reason): ProvisionalUatPermitCompletion {
            $this->safety->ensureEnabled();
            $persona = $this->safety->personaFor($actor);

            if (! in_array($persona, [StakeholderPreviewPersona::MayorOffice, StakeholderPreviewPersona::Releasing], true)) {
                throw new DomainException('This preview perspective cannot record a final permit action.');
            }

            $permitApplication = PermitApplication::query()->whereKey($permitApplication->id)->lockForUpdate()->firstOrFail();
            $permitApplication->load(['paymentSchedules', 'clearances', 'provisionalUatPermitCompletion']);
            $this->assertReady($permitApplication);
            $completion = $permitApplication->provisionalUatPermitCompletion
                ?? new ProvisionalUatPermitCompletion([
                    'permit_application_id' => $permitApplication->id,
                    'semantic_classification' => 'provisional_uat',
                    'source_snapshot' => $this->sourceSnapshot($permitApplication),
                ]);

            if ($persona === StakeholderPreviewPersona::MayorOffice) {
                if (! in_array($decision, ['go', 'no_go'], true)) {
                    throw new DomainException('The Mayor Office preview decision must be go or no-go.');
                }

                $approved = $decision === 'go';
                $completion->fill([
                    'decided_by_id' => $actor->id,
                    'status' => $approved ? 'approved_for_preview_release' : 'not_approved',
                    'decision' => $decision,
                    'reason' => filled($reason) ? Str::squish($reason) : null,
                    'permit_number' => $approved ? $this->permitNumber($permitApplication) : null,
                    'synthetic_signature_reference' => $approved ? config('stakeholder_preview.weekend_hypothesis.synthetic_signature_reference') : null,
                    'decided_at' => now(),
                    'released_by_id' => null,
                    'released_at' => null,
                ])->save();

                $completion->refresh();

                return $completion;
            }

            if ($decision !== 'release' || $completion->status !== 'approved_for_preview_release') {
                throw new DomainException('The sample permit must have a provisional Mayor Office go decision before preview release.');
            }

            $completion->fill([
                'released_by_id' => $actor->id,
                'status' => 'released_in_preview',
                'released_at' => now(),
            ])->save();

            $completion->refresh();

            return $completion;
        });
    }

    private function assertReady(PermitApplication $permitApplication): void
    {
        $paid = $permitApplication->paymentSchedules->contains(fn ($schedule): bool => $schedule->status === PaymentScheduleStatus::Paid);
        $clearancesComplete = $permitApplication->clearances->isNotEmpty()
            && $permitApplication->clearances->every(fn ($clearance): bool => $clearance->status === PermitClearanceStatus::Completed);

        if (! $paid || ! $clearancesComplete) {
            throw new DomainException('Payment confirmation and all scenario clearances are required before final preview processing.');
        }
    }

    private function permitNumber(PermitApplication $permitApplication): string
    {
        return sprintf(
            '%s-%d-%06d',
            config('stakeholder_preview.weekend_hypothesis.permit_number_prefix', 'UAT-IPIL'),
            $permitApplication->application_year,
            $permitApplication->id,
        );
    }

    /** @return array<string, mixed> */
    private function sourceSnapshot(PermitApplication $permitApplication): array
    {
        return [
            'semantic_classification' => 'provisional_uat',
            'profile' => StakeholderPreviewSafety::Profile,
            'permit_application_id' => $permitApplication->id,
            'application_type' => $permitApplication->type->value,
            'real_mayor_credentials_or_key_used' => false,
            'official_numbering_authority' => false,
            'permit_issuance_authority' => false,
            'legal_effect' => false,
        ];
    }
}
