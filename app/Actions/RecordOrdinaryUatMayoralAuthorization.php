<?php

namespace App\Actions;

use App\Models\PermitApplication;
use App\Models\ProvisionalUatPermitCompletion;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class RecordOrdinaryUatMayoralAuthorization
{
    public function __construct(private readonly OrdinaryUatPermitAuthority $authority, private readonly ProjectPermitReadiness $readiness) {}

    public function handle(PermitApplication $application, User $actor): ProvisionalUatPermitCompletion
    {
        return DB::transaction(function () use ($application, $actor): ProvisionalUatPermitCompletion {
            $application = PermitApplication::query()->whereKey($application)->lockForUpdate()->firstOrFail();
            if (! $this->authority->allows($application, $actor)) {
                throw new DomainException('Only the exact configured workflow-UAT Mayor actor may authorize.');
            }
            if ($this->authority->authorized($application)) {
                return $application->provisionalUatPermitCompletion;
            }
            $readiness = $this->readiness->handle($application);
            if (! $this->authority->prerequisites($application)
                || array_diff($readiness['blocked_by'], ['mayoral_authorization_recorded']) !== []
                || $application->provisionalUatPermitCompletion !== null) {
                throw new DomainException('Complete canonical prerequisites and no prior conflicting authority record are required.');
            }
            $configuration = $this->authority->configuration();
            $evidence = ['version' => 'bpls.ordinary-uat-mayoral-authorization.v1', 'application_id' => $application->id,
                'actor_id' => $actor->id, 'mode' => 'synthetic_only', 'production_authority' => false,
                'statutory_signature' => false, 'personally_performed_by_officeholder' => false,
                'authorized_at' => now()->toIso8601String(), 'configuration' => $configuration,
                'configuration_fingerprint' => hash('sha256', json_encode($configuration, JSON_THROW_ON_ERROR)),
                'readiness' => $readiness, 'readiness_fingerprint' => $this->authority->readinessFingerprint($readiness),
                'evidence_fingerprint' => $this->authority->evidenceFingerprint($application),
                'assessment_id' => $application->paymentSchedules->sortByDesc('sequence')->first()?->assessment_id,
                'collection_ids' => $application->paymentSchedules->flatMap(fn ($s) => $s->treasuryCollections)->pluck('id')->all(),
                'certifications' => $application->postPaymentOfficeCertifications->sortBy('id')->map(fn ($c): array => [
                    'id' => $c->id, 'office' => $c->office_code, 'receipt_id' => $c->receipt_id,
                    'actor_id' => $c->certified_by_id, 'certified_at' => $c->certified_at?->toIso8601String(),
                ])->values()->all()];
            $evidence['fingerprint'] = hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR));

            return $application->provisionalUatPermitCompletion()->create([
                'decided_by_id' => $actor->id, 'decided_at' => $evidence['authorized_at'],
                'decision' => 'authorize_synthetic_issuance', 'status' => 'authorized_synthetic',
                'semantic_classification' => 'synthetic_only',
                'source_snapshot' => ['ordinary_mayoral_authorization' => $evidence, 'production_authority' => false],
            ]);
        }, 3);
    }
}
