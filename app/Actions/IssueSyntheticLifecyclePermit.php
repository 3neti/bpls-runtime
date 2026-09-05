<?php

namespace App\Actions;

use App\Models\LifecycleCleanroomRun;
use App\Models\PermitApplication;
use App\Models\ProvisionalUatPermitCompletion;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class IssueSyntheticLifecyclePermit
{
    public function __construct(private readonly ProjectPermitReadiness $projectReadiness) {}

    public function handle(PermitApplication $permitApplication, User $actor): ProvisionalUatPermitCompletion
    {
        return DB::transaction(function () use ($permitApplication, $actor): ProvisionalUatPermitCompletion {
            $application = PermitApplication::query()->whereKey($permitApplication)->lockForUpdate()->firstOrFail();
            $readiness = $this->projectReadiness->handle($application);
            if (! $readiness['ready'] || $readiness['semantic_classification'] !== 'synthetic_only') {
                throw new DomainException('The synthetic permit cannot be issued until deterministic PermitReadiness passes.');
            }
            $this->assertActor($application, $actor, 'permit_issuer');

            $completion = $application->provisionalUatPermitCompletion()->firstOrNew();
            if ($completion->issued_at !== null) {
                return $completion;
            }
            $issuedAt = now();
            $permitNumber = sprintf('BP-%d-%04d', $application->application_year, $application->id % 10000);
            $completion->fill([
                'permit_application_id' => $application->id,
                'issued_by_id' => $actor->id,
                'status' => 'issued_synthetic',
                'decision' => 'issue_synthetic_specimen',
                'permit_number' => $permitNumber,
                'synthetic_signature_reference' => 'SYNTHETIC-MAYOR-'.substr(hash('sha256', $permitNumber.'|'.$application->id), 0, 16),
                'issued_at' => $issuedAt,
                'valid_until' => $issuedAt->copy()->year($application->application_year)->endOfYear()->toDateString(),
                'semantic_classification' => 'synthetic_only',
                'source_snapshot' => [
                    'permit_readiness' => $readiness,
                    'number_allocator' => 'synthetic_specimen_bp_year_sequence_v1',
                    'official_numbering_authority' => false,
                    'mayor_authority_evidence' => 'bounded_synthetic_cleanroom_representation',
                    'real_mayor_login_or_signature_used' => false,
                    'permit_issuance_authority' => false,
                    'production_authority' => false,
                    'legal_effect' => false,
                ],
            ])->save();

            return $completion->refresh();
        }, 3);
    }

    private function assertActor(PermitApplication $application, User $actor, string $key): void
    {
        $runId = data_get($application->metadata, 'lifecycle_cleanroom.run_id');
        $run = is_string($runId) ? LifecycleCleanroomRun::query()->where('public_id', $runId)->first() : null;
        if (! $run instanceof LifecycleCleanroomRun || data_get($run->actor_manifest, 'actors.'.$key.'.user_id') !== $actor->id) {
            throw new DomainException('Only the commissioned synthetic permit issuer may perform this cleanroom act.');
        }
    }
}
