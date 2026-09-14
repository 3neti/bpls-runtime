<?php

namespace App\Actions;

use App\Enums\UserPermission;
use App\Models\InstitutionalPositionAssignment;
use App\Models\LifecycleCleanroomRun;
use App\Models\PaymentSchedule;
use App\Models\User;
use LogicException;

final class AuthorizeUatQrPhSimulation
{
    public function __construct(
        private readonly EnsureQrPhPaymentEligible $ensureEligible,
        private readonly ResolveActivePaymentAttempt $resolveAttempt,
    ) {}

    public function available(PaymentSchedule $schedule, ?User $viewer): bool
    {
        if (! $this->handle($schedule, $viewer)) {
            return false;
        }
        try {
            $this->ensureEligible->handle($schedule);
        } catch (LogicException) {
            return false;
        }
        $payment = $schedule->xChangePayment;

        return $payment !== null
            && in_array($payment->status, ['issued', 'awaiting_payment'], true)
            && $payment->assessment_id === $schedule->assessment_id
            && $payment->amount_cents === $schedule->total_amount_cents
            && $payment->currency === 'PHP'
            && filled($payment->pay_code)
            && $schedule->treasuryCollections()->doesntExist()
            && $this->resolveAttempt->handle($payment)['state'] === 'active';
    }

    public function environmentAllowsSimulation(): bool
    {
        $url = rtrim((string) config('app.url'), '/');
        $targetAllowed = $url === 'https://bpls-stakeholder-preview-uat-uat-5wn03n.laravel.cloud'
            || (app()->environment(['local', 'testing'])
                && in_array(parse_url($url, PHP_URL_HOST), ['localhost', '127.0.0.1', 'bpls-runtime.test'], true));

        return app()->environment(['staging', 'local', 'testing'])
            && config('stakeholder_preview.mode') === true
            && config('stakeholder_preview.production_migration_enabled') === false
            && config('stakeholder_preview.production_integrations') === 'disabled'
            && $targetAllowed;
    }

    public function handle(PaymentSchedule $schedule, ?User $viewer): bool
    {
        if (! $this->environmentAllowsSimulation()
            || ! $viewer instanceof User
            || ! $viewer->can(UserPermission::RecordCollections->value)) {
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
        return InstitutionalPositionAssignment::query()
            ->where('user_id', $viewer->id)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->whereHas('position.capabilityRole', fn ($query) => $query->where('code', 'cashier'))
            ->exists()
            && $schedule->permit_application_id === 291
            && $schedule->id === 63
            && $schedule->assessment_id === 215
            && $schedule->xChangePayment?->id === 5
            && $schedule->total_amount_cents === 417500
            && data_get($schedule->permitApplication->metadata, 'nelson_reconciliation_v1.commissioned_path') === true;
    }
}
