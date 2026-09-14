<?php

namespace App\Actions;

use App\Enums\StakeholderPreviewPersona;
use App\Enums\UserPermission;
use App\Models\LifecycleCleanroomRun;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\StakeholderPreview\StakeholderPreviewSafety;

final class AuthorizeUatQrPhSimulation
{
    public function __construct(private readonly StakeholderPreviewSafety $safety) {}

    public function handle(PaymentSchedule $schedule, ?User $viewer): bool
    {
        if (! $viewer instanceof User || ! $viewer->can(UserPermission::RecordCollections->value)) {
            return false;
        }
        $runId = data_get($schedule->permitApplication->metadata, 'lifecycle_cleanroom.run_id');
        $run = is_string($runId) ? LifecycleCleanroomRun::query()->where('public_id', $runId)->first() : null;
        if ($run instanceof LifecycleCleanroomRun) {
            return $run->isClassicLifecycleV1() && $run->status === 'active'
                && data_get($run->actor_manifest, 'semantic_classification') === 'synthetic_only'
                && data_get($run->actor_manifest, 'production_liability') === false
                && data_get($run->actor_manifest, 'actors.cashier.user_id') === $viewer->id;
        }

        // The ordinary walkthrough commission is bound to this preserved UAT specimen.
        return $this->safety->isEnabled()
            && rtrim((string) config('app.url'), '/') === 'https://bpls-stakeholder-preview-uat-uat-5wn03n.laravel.cloud'
            && $this->safety->personaFor($viewer) === StakeholderPreviewPersona::Cashier
            && $schedule->permit_application_id === 291
            && $schedule->id === 63
            && $schedule->assessment_id === 215
            && $schedule->xChangePayment?->id === 5
            && $schedule->total_amount_cents === 417500
            && data_get($schedule->permitApplication->metadata, 'nelson_reconciliation_v1.commissioned_path') === true;
    }
}
