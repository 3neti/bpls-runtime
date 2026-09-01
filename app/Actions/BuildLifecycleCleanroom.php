<?php

namespace App\Actions;

use App\Models\LifecycleCleanroomRun;

class BuildLifecycleCleanroom
{
    public function __construct(private readonly ResolveLifecycleCleanroomState $resolveState) {}

    /** @return array<string, mixed> */
    public function handle(): array
    {
        $active = LifecycleCleanroomRun::query()->where('status', 'active')->latest('id')->first();

        return [
            'active' => $active instanceof LifecycleCleanroomRun ? $this->resolveState->handle($active) : null,
            'history' => LifecycleCleanroomRun::query()->where('status', 'closed')->latest('id')->limit(5)->get()->map(fn (LifecycleCleanroomRun $run): array => [
                'public_id' => $run->public_id,
                'closed_at' => $run->closed_at?->toIso8601String(),
                'new_application_id' => $run->new_application_id,
                'renewal_application_id' => $run->renewal_application_id,
            ])->all(),
        ];
    }
}
