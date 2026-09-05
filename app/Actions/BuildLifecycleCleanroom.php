<?php

namespace App\Actions;

use App\Data\Application\ApplicationDataResolver;
use App\Models\LifecycleCleanroomRun;
use App\Models\PermitApplication;

class BuildLifecycleCleanroom
{
    public function __construct(
        private readonly ResolveLifecycleCleanroomState $resolveState,
        private readonly ApplicationDataResolver $applicationDataResolver,
        private readonly BuildExecutablePermitApplicationDocument $buildDocument,
    ) {}

    /** @return array<string, mixed> */
    public function handle(): array
    {
        $active = LifecycleCleanroomRun::query()->where('status', 'active')->latest('id')->first();

        $activeState = $active instanceof LifecycleCleanroomRun ? $this->resolveState->handle($active) : null;
        $applicationId = $active instanceof LifecycleCleanroomRun
            ? ($active->renewal_application_id ?? $active->new_application_id)
            : null;
        $application = is_int($applicationId) ? PermitApplication::query()->find($applicationId) : null;
        if (is_array($activeState)) {
            $activeState['application_data'] = $application instanceof PermitApplication
                ? $this->applicationDataResolver->resolve($application)->toArray()
                : null;
            $activeState['application_document'] = $application instanceof PermitApplication
                ? $this->buildDocument->handle($application)
                : null;
            $schedule = $application?->paymentSchedules()->with(['xChangePayment', 'treasuryCollections.receipt'])->latest('sequence')->first();
            $activeState['payment_simulation'] = [
                'available' => $schedule?->xChangePayment?->pay_code !== null && $schedule->treasuryCollections->isEmpty(),
                'pay_code' => $schedule?->xChangePayment?->pay_code,
                'collection_id' => $schedule?->treasuryCollections->first()?->id,
                'receipt_id' => $schedule?->treasuryCollections->first()?->receipt?->id,
                'status' => $schedule?->treasuryCollections->isNotEmpty() ? 'collected' : ($schedule?->xChangePayment?->pay_code !== null ? 'awaiting_simulation' : 'not_ready'),
            ];
        }

        return [
            'active' => $activeState,
            'history' => LifecycleCleanroomRun::query()->where('status', 'closed')->latest('id')->limit(5)->get()->map(fn (LifecycleCleanroomRun $run): array => [
                'public_id' => $run->public_id,
                'closed_at' => $run->closed_at?->toIso8601String(),
                'new_application_id' => $run->new_application_id,
                'renewal_application_id' => $run->renewal_application_id,
            ])->all(),
        ];
    }
}
