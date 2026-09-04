<?php

namespace App\Actions;

use App\Evaluation\BusinessPermitEvaluationResolver;
use App\Models\BusinessPermitEvaluation;
use App\Models\LifecycleCleanroomRun;
use App\Models\PermitApplication;
use App\Models\User;
use LogicException;

class ConfirmLifecycleRoutineOfficeDefaults
{
    public function __construct(
        private readonly BusinessPermitEvaluationResolver $resolver,
        private readonly ConfirmBusinessPermitEvaluationOfficeDefaults $confirmOfficeDefaults,
    ) {}

    /** @return array{confirmed: int, manual: int} */
    public function handle(LifecycleCleanroomRun $run, int $applicationYear): array
    {
        if ($run->status !== 'active'
            || data_get($run->actor_manifest, 'semantic_classification') !== 'synthetic_only'
            || data_get($run->actor_manifest, 'production_liability') !== false) {
            throw new LogicException('Routine confirmation is available only inside an active synthetic cleanroom.');
        }

        $application = match ($applicationYear) {
            2025 => $run->newApplication()->first(),
            2026 => $run->renewalApplication()->first(),
            default => null,
        };

        if (! $application instanceof PermitApplication
            || ! in_array($application->id, $run->ownedPermitApplicationIds(), true)) {
            throw new LogicException('This cleanroom does not own the requested application.');
        }

        $evaluation = $application->businessPermitEvaluation()->first();
        if (! $evaluation instanceof BusinessPermitEvaluation) {
            throw new LogicException('Office-review work has not been created for this application.');
        }

        $projection = $this->resolver->resolve($evaluation);
        $openResponsibilities = collect($projection['items'])
            ->filter(fn (array $item): bool => data_get($item, 'metadata.lifecycle_cleanroom_responsibility') === true
                && $item['item_type'] === 'charge'
                && $item['resolution'] !== 'resolved');
        $eligible = $openResponsibilities
            ->filter(fn (array $item): bool => is_int(data_get($item, 'default_value.amount_cents'))
                && data_get($item, 'metadata.inspection_required', false) === false)
            ->groupBy('responsible_party');

        $confirmed = 0;
        foreach ($eligible as $office => $items) {
            $actorId = data_get($run->actor_manifest, "actors.{$office}.user_id");
            $actor = is_int($actorId) ? User::query()->find($actorId) : null;
            if (! $actor instanceof User) {
                throw new LogicException("The cleanroom actor for [{$office}] is unavailable.");
            }

            $evaluation = $evaluation->fresh();
            $current = $this->resolver->resolve($evaluation);
            $confirmed += $this->confirmOfficeDefaults->handle(
                $evaluation,
                $actor,
                array_values($items->pluck('id')->map(fn (mixed $id): int => (int) $id)->all()),
                $current['version_sequence'],
                $current['current_fingerprint'],
                "{$run->public_id}:routine:{$applicationYear}:{$office}",
            );
        }

        return [
            'confirmed' => $confirmed,
            'manual' => $openResponsibilities->count() - $confirmed,
        ];
    }
}
