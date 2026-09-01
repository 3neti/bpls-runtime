<?php

namespace App\Actions;

use App\Enums\StakeholderPreviewPersona;
use App\Enums\UserPermission;
use App\Models\LifecycleScenarioSpecimen;
use App\Models\PermitApplication;
use App\Models\User;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateStakeholderPreviewSpecimenCitizen
{
    public function __construct(private readonly StakeholderPreviewSafety $safety) {}

    /** @return list<array{id: int, label: string, description: string}> */
    public function entries(): array
    {
        $this->safety->ensureReady();

        return array_values(LifecycleScenarioSpecimen::query()
            ->select([
                'id',
                'scenario_id',
                'permit_application_id',
                'owned_resource_manifest',
            ])
            ->latest('id')
            ->get()
            ->filter(fn (LifecycleScenarioSpecimen $specimen): bool => $this->resolveCitizen($specimen) instanceof User)
            ->map(fn (LifecycleScenarioSpecimen $specimen): array => [
                'id' => $specimen->id,
                'label' => str($specimen->scenario_id)->headline()->append(' Citizen')->toString(),
                'description' => 'Open the persisted lifecycle specimen through its owner-linked Citizen actor.',
            ])
            ->values()
            ->all());
    }

    public function handle(Request $request, LifecycleScenarioSpecimen $specimen): User
    {
        $this->safety->ensureReady();

        $user = $this->resolveCitizen($specimen);
        abort_unless($user instanceof User, 404);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $authenticatedUser = Auth::guard('web')->loginUsingId($user->getAuthIdentifier());
        $request->session()->regenerate();

        abort_unless($authenticatedUser instanceof User, 404);

        return $authenticatedUser;
    }

    private function resolveCitizen(LifecycleScenarioSpecimen $specimen): ?User
    {
        $manifest = $specimen->owned_resource_manifest;

        if (data_get($manifest, 'semantic_classification') !== 'synthetic_only'
            || data_get($manifest, 'production_liability') !== false) {
            return null;
        }

        $ownerIds = array_values(array_unique([
            ...$this->integerIds(data_get($manifest, 'business_owner_ids')),
            ...$this->integerIds(data_get($manifest, 'referenced_business_owner_ids')),
        ]));
        $applicationIds = $this->integerIds(data_get($manifest, 'permit_application_ids'));
        $actorIds = $this->integerIds(data_get($manifest, 'actor_user_ids'));

        if (count($ownerIds) !== 1
            || $actorIds === []
            || ! in_array($specimen->permit_application_id, $applicationIds, true)) {
            return null;
        }

        $application = PermitApplication::query()
            ->select(['id', 'business_id'])
            ->with('business:id,business_owner_id')
            ->find($specimen->permit_application_id);

        if (! $application instanceof PermitApplication
            || ! in_array($application->business->business_owner_id, $ownerIds, true)) {
            return null;
        }

        $citizens = User::query()
            ->select(['id', 'role_id', 'business_owner_id', 'name', 'email', 'email_verified_at'])
            ->with('role.permissions')
            ->whereKey($actorIds)
            ->where('business_owner_id', $application->business->business_owner_id)
            ->get()
            ->filter(fn (User $user): bool => $user->can(UserPermission::AccessCitizen->value)
                && ! $this->safety->personaFor($user) instanceof StakeholderPreviewPersona)
            ->values();

        return $citizens->count() === 1 ? $citizens->first() : null;
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
