<?php

namespace App\Actions;

use App\Models\InstitutionalPositionAssignment;
use App\Models\PermitApplication;
use App\Models\User;
use LogicException;

final class OrdinaryUatPermitAuthority
{
    public function __construct(private readonly PostPaymentCertificationEligibility $eligibility) {}

    public function enabled(PermitApplication $application): bool
    {
        return $this->eligibility->ordinaryUat($application)
            && ! $application->isHistoricalEvidenceOnly()
            && config('workflow_uat_authority.mode') === 'synthetic_only';
    }

    public function assignment(string $ceremony = 'mayor'): ?InstitutionalPositionAssignment
    {
        return InstitutionalPositionAssignment::query()->with('position.capabilityRole')
            ->whereKey((int) config('workflow_uat_authority.'.$ceremony.'_assignment_id'))
            ->where('status', 'active')->whereNull('ended_at')
            ->whereHas('position', fn ($q) => $q->where('code', $ceremony === 'mayor' ? 'mayors_office_reviewer' : 'releasing_officer'))
            ->whereHas('position.capabilityRole', fn ($q) => $q->where('code', $ceremony === 'mayor' ? 'mayor_office' : 'releasing'))
            ->first();
    }

    public function allows(PermitApplication $application, ?User $actor, string $ceremony = 'mayor'): bool
    {
        $assignment = $this->assignment($ceremony);

        return $this->enabled($application) && $actor !== null && $assignment !== null
            && $assignment->user_id === $actor->id && $actor->can('staff.access');
    }

    public function prerequisites(PermitApplication $application): bool
    {
        try {
            $bindings = $this->eligibility->receipts($application);
        } catch (LogicException) {
            return false;
        }
        $certifications = $application->postPaymentOfficeCertifications()->get();

        return count($bindings) === $certifications->count()
            && $certifications->every(fn ($c): bool => $c->status === 'completed' && $c->result === 'certified'
                && $c->certified_by_id !== null && $c->certified_at !== null
                && ($bindings[$c->office_code] ?? null)?->id === $c->receipt_id
                && $c->bplo_routing_determination_id === $application->bploRoutingDetermination?->id
                && collect($c->routing_work_ids)->sort()->values()->all() === $application->bploRoutingDetermination->works->where('office_code', $c->office_code)->pluck('id')->sort()->values()->all());
    }

    /** @return array<string, mixed> */
    public function configuration(): array
    {
        $assignment = $this->assignment();

        return ['version' => config('workflow_uat_authority.version'), 'mode' => config('workflow_uat_authority.mode'),
            'target' => rtrim((string) config('app.url'), '/'), 'assignment_id' => $assignment?->id,
            'position_id' => $assignment?->institutional_position_id, 'actor_id' => $assignment?->user_id,
            'assigned_at' => $assignment?->getRawOriginal('assigned_at'), 'production_authority' => false];
    }

    /** @param array<string, mixed> $readiness */
    public function readinessFingerprint(array $readiness): string
    {
        unset($readiness['ready'], $readiness['state'], $readiness['blocked_by'], $readiness['prerequisites']['mayoral_authorization_recorded']);

        return hash('sha256', json_encode($readiness, JSON_THROW_ON_ERROR));
    }

    public function authorized(PermitApplication $application): bool
    {
        $record = $application->provisionalUatPermitCompletion;
        $evidence = data_get($record?->source_snapshot, 'ordinary_mayoral_authorization');
        if (! is_array($evidence)) {
            return false;
        }
        $fingerprint = $evidence['fingerprint'] ?? null;
        unset($evidence['fingerprint']);

        return $record?->decided_at !== null && $record->decided_by_id === data_get($evidence, 'actor_id')
            && data_get($evidence, 'application_id') === $application->id
            && data_get($evidence, 'production_authority') === false
            && data_get($evidence, 'mode') === 'synthetic_only'
            && is_string($fingerprint) && hash_equals($fingerprint, hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR)));
    }

    public function evidenceFingerprint(PermitApplication $application): string
    {
        $application->load(['assessments', 'paymentSchedules.treasuryCollections.receipts', 'paymentSchedules.treasuryCollections.allocations', 'postPaymentOfficeCertifications']);
        $schedules = $application->paymentSchedules->sortBy('id');
        $collections = $schedules->flatMap(fn ($s) => $s->treasuryCollections)->sortBy('id');
        $payload = [
            'application_id' => $application->id,
            'assessments' => $application->assessments->sortBy('id')->map->getRawOriginal()->values()->all(),
            'schedules' => $schedules->map->getRawOriginal()->values()->all(),
            'collections' => $collections->map->getRawOriginal()->values()->all(),
            'receipts' => $collections->flatMap(fn ($c) => $c->receipts)->sortBy('id')->map->getRawOriginal()->values()->all(),
            'allocations' => $collections->flatMap(fn ($c) => $c->allocations)->sortBy('id')->map->getRawOriginal()->values()->all(),
            'certifications' => $application->postPaymentOfficeCertifications->sortBy('id')->map->getRawOriginal()->values()->all(),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
