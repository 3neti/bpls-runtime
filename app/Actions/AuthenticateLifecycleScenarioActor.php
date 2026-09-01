<?php

namespace App\Actions;

use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\Models\LifecycleScenarioSpecimen;
use App\Models\User;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AuthenticateLifecycleScenarioActor
{
    /** @var array<string, array{label: string, role_suffix: string, destination: string}> */
    private const array Actors = [
        'citizen' => ['label' => 'Citizen', 'role_suffix' => 'citizen', 'destination' => 'citizen.businesses.show'],
        'intake' => ['label' => 'BPLO Intake', 'role_suffix' => 'intake', 'destination' => 'staff.permit-applications.show'],
        'assessment_officer' => ['label' => 'Assessment Officer', 'role_suffix' => 'assessment-officer', 'destination' => 'staff.permit-applications.assessments.show'],
        'assessor' => ['label' => 'Municipal Assessor', 'role_suffix' => 'assessor', 'destination' => 'staff.permit-applications.evaluation.show'],
        'engineering' => ['label' => 'Engineering', 'role_suffix' => 'engineering', 'destination' => 'staff.permit-applications.evaluation.show'],
        'health' => ['label' => 'Health', 'role_suffix' => 'health', 'destination' => 'staff.permit-applications.evaluation.show'],
        'menro' => ['label' => 'MENRO', 'role_suffix' => 'menro', 'destination' => 'staff.permit-applications.evaluation.show'],
        'treasury' => ['label' => 'Treasury', 'role_suffix' => 'treasury-counter-check', 'destination' => 'staff.permit-applications.evaluation.show'],
        'municipal_treasurer' => ['label' => 'Municipal Treasurer', 'role_suffix' => 'municipal-treasurer', 'destination' => 'staff.permit-applications.assessments.show'],
    ];

    public function __construct(private readonly StakeholderPreviewSafety $safety) {}

    /** @return list<array{key: string, label: string}> */
    public function entries(LifecycleScenarioSpecimen $specimen): array
    {
        return array_values(collect(self::Actors)
            ->filter(fn (array $actor, string $key): bool => $this->resolve($specimen, $key) instanceof User)
            ->map(fn (array $actor, string $key): array => ['key' => $key, 'label' => $actor['label']])
            ->all());
    }

    public function handle(Request $request, LifecycleScenarioSpecimen $specimen, string $actorKey): string
    {
        $this->safety->ensureReady();
        $user = $this->resolve($specimen, $actorKey);
        abort_unless($user instanceof User, 404);

        $destination = $this->destination($specimen, $actorKey);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $authenticatedUser = Auth::guard('web')->loginUsingId($user->getAuthIdentifier());
        $request->session()->regenerate();
        abort_unless($authenticatedUser instanceof User, 404);

        return $destination;
    }

    private function resolve(LifecycleScenarioSpecimen $specimen, string $actorKey): ?User
    {
        $actor = self::Actors[$actorKey] ?? null;
        $manifest = $specimen->owned_resource_manifest;
        if (! is_array($actor)
            || data_get($manifest, 'semantic_classification') !== 'synthetic_only'
            || data_get($manifest, 'production_liability') !== false) {
            return null;
        }

        $actorIds = $this->integerIds(data_get($manifest, 'actor_user_ids'));
        $roleIds = $this->integerIds(data_get($manifest, 'actor_role_ids'));
        $prefix = $actorKey === 'citizen' || $specimen->scenario_id === NewApplicationHappyPathDefinition::Id
            ? 'scenario-01'
            : 'scenario-02';

        return User::query()
            ->with('role.permissions')
            ->whereKey($actorIds)
            ->whereHas('role', fn ($query) => $query
                ->whereKey($roleIds)
                ->where('code', $prefix.'-'.$actor['role_suffix']))
            ->first();
    }

    private function destination(LifecycleScenarioSpecimen $specimen, string $actorKey): string
    {
        $actor = self::Actors[$actorKey];
        $application = $specimen->permitApplication()->with(['business', 'assessments'])->firstOrFail();
        $parameter = match ($actor['destination']) {
            'citizen.businesses.show' => $application->business_id,
            'staff.permit-applications.assessments.show' => $application->assessments()->whereNull('superseded_at')->sole()->id,
            default => $application->id,
        };

        return route($actor['destination'], $parameter, absolute: false);
    }

    /** @return list<int> */
    private function integerIds(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        return array_values(collect($ids)
            ->filter(fn (mixed $id): bool => is_int($id) && $id > 0)
            ->unique()
            ->all());
    }
}
