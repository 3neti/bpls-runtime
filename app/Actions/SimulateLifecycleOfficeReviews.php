<?php

namespace App\Actions;

use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationRevisionAction;
use App\Evaluation\BusinessPermitEvaluationResolver;
use App\Models\BusinessPermitEvaluation;
use App\Models\BusinessPermitEvaluationItem;
use App\Models\BusinessPermitEvaluationItemRevision;
use App\Models\LifecycleCleanroomRun;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class SimulateLifecycleOfficeReviews
{
    public function __construct(
        private readonly BusinessPermitEvaluationResolver $resolver,
        private readonly CompleteBusinessPermitEvaluationResponsibility $completeResponsibility,
    ) {}

    public function handle(LifecycleCleanroomRun $run, int $applicationYear): int
    {
        if ($run->status !== 'active'
            || data_get($run->actor_manifest, 'semantic_classification') !== 'synthetic_only'
            || data_get($run->actor_manifest, 'production_liability') !== false) {
            throw new LogicException('Office-review simulation is available only inside an active synthetic cleanroom.');
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
        $itemIds = collect($projection['items'])
            ->filter(fn (array $item): bool => data_get($item, 'metadata.lifecycle_cleanroom_responsibility') === true
                && $item['item_type'] === 'charge'
                && $item['resolution'] !== 'resolved'
                && is_int(data_get($item, 'default_value.amount_cents'))
                && data_get($item, 'metadata.inspection_required', false) === true)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        return DB::transaction(function () use ($run, $applicationYear, $evaluation, $itemIds): int {
            foreach ($itemIds as $itemId) {
                $item = $evaluation->items()->find($itemId);
                if (! $item instanceof BusinessPermitEvaluationItem) {
                    throw new LogicException('A cleanroom office responsibility no longer exists.');
                }

                $actorId = data_get($run->actor_manifest, "actors.{$item->responsible_party}.user_id");
                $actor = is_int($actorId) ? User::query()->find($actorId) : null;
                if (! $actor instanceof User) {
                    throw new LogicException("The cleanroom actor for [{$item->responsible_party}] is unavailable.");
                }

                $proposal = $item->revisions()
                    ->where('action', BusinessPermitEvaluationRevisionAction::Proposal)
                    ->first();
                if (! $proposal instanceof BusinessPermitEvaluationItemRevision) {
                    throw new LogicException('The cleanroom responsibility has no default proposal.');
                }

                $evaluation = $evaluation->fresh();
                $current = $this->resolver->resolve($evaluation);
                $finding = 'Synthetic Lifecycle Laboratory inspection simulation; no real inspection occurred.';
                $this->completeResponsibility->handle(
                    $item,
                    $actor,
                    BusinessPermitEvaluationApplicability::Applicable,
                    [
                        'amount_cents' => data_get($proposal->value, 'amount_cents'),
                        'inspection' => [
                            'required' => true,
                            'mode' => 'physical',
                            'completed' => true,
                            'findings' => $finding,
                        ],
                    ],
                    $proposal->source_classification,
                    $finding,
                    $current['version_sequence'],
                    $current['current_fingerprint'],
                    "{$run->public_id}:simulated-review:{$applicationYear}:{$item->id}",
                );
            }

            return $itemIds->count();
        });
    }
}
