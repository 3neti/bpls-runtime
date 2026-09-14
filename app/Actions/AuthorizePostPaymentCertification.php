<?php

namespace App\Actions;

use App\Enums\UserPermission;
use App\Models\InstitutionalPositionAssignment;
use App\Models\PostPaymentOfficeCertification;
use App\Models\User;
use LogicException;

final class AuthorizePostPaymentCertification
{
    public function __construct(private readonly PostPaymentCertificationEligibility $eligibility) {}

    public function allows(PostPaymentOfficeCertification $certification, ?User $actor): bool
    {
        if ($actor === null || ! $actor->can(UserPermission::AccessStaff->value)
            || ! $this->eligibility->ordinaryUat($certification->permitApplication)
            || ! $actor->hasRole($certification->office_code)
            || ! InstitutionalPositionAssignment::query()->where('user_id', $actor->id)
                ->where('status', 'active')->whereNull('ended_at')
                ->whereHas('position.capabilityRole', fn ($q) => $q->where('code', $certification->office_code))->exists()) {
            return false;
        }
        try {
            $bindings = $this->eligibility->receipts($certification->permitApplication);
        } catch (LogicException) {
            return false;
        }

        $routing = $certification->permitApplication->bploRoutingDetermination;
        $workIds = $routing?->works->where('office_code', $certification->office_code)->pluck('id')->sort()->values()->all() ?? [];

        return ($bindings[$certification->office_code] ?? null)?->id === $certification->receipt_id
            && $routing?->id === $certification->bplo_routing_determination_id
            && $workIds === collect($certification->routing_work_ids)->sort()->values()->all();
    }
}
