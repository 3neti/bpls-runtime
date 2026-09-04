<?php

namespace App\Actions;

use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationRevisionAction;
use App\Evaluation\BusinessPermitEvaluationResolver;
use App\Models\BusinessPermitEvaluation;
use App\Models\BusinessPermitEvaluationItem;
use App\Models\BusinessPermitEvaluationItemRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class ConfirmBusinessPermitEvaluationOfficeDefaults
{
    public function __construct(
        private readonly BusinessPermitEvaluationResolver $resolver,
        private readonly CompleteBusinessPermitEvaluationResponsibility $completeResponsibility,
    ) {}

    /**
     * @param  list<int>  $itemIds
     */
    public function handle(
        BusinessPermitEvaluation $evaluation,
        User $actor,
        array $itemIds,
        int $expectedVersionSequence,
        string $expectedFingerprint,
        string $idempotencyKey,
    ): int {
        $projection = $this->resolver->resolve($evaluation);

        if ($projection['version_sequence'] !== $expectedVersionSequence
            || ! hash_equals($projection['current_fingerprint'], $expectedFingerprint)) {
            throw new LogicException('The Evaluation changed. Reload it before confirming the defaults.');
        }

        $requestedIds = collect($itemIds)->unique()->values();
        $projectedItems = collect($projection['items'])->keyBy('id');

        $items = $requestedIds->map(function (int $itemId) use ($evaluation, $projectedItems): array {
            $projected = $projectedItems->get($itemId);

            if (! is_array($projected)
                || $projected['item_type'] !== 'charge'
                || $projected['resolution'] === 'resolved'
                || ! is_int(data_get($projected, 'default_value.amount_cents'))
                || data_get($projected, 'metadata.inspection_required', false)) {
                throw new LogicException('Only open office charges with a default and no outstanding inspection may be confirmed together.');
            }

            $item = $evaluation->items()->find($itemId);

            if (! $item instanceof BusinessPermitEvaluationItem) {
                throw new LogicException('The selected Evaluation responsibility no longer exists.');
            }

            $proposal = $item->revisions()
                ->where('action', BusinessPermitEvaluationRevisionAction::Proposal)
                ->first();

            if (! $proposal instanceof BusinessPermitEvaluationItemRevision) {
                throw new LogicException('The selected charge has no default proposal to confirm.');
            }

            return [$item, $proposal, data_get($projected, 'default_value.amount_cents')];
        });

        if ($items->isEmpty()) {
            throw new LogicException('There are no eligible defaults to confirm.');
        }

        return DB::transaction(function () use ($evaluation, $actor, $items, $expectedVersionSequence, $expectedFingerprint, $idempotencyKey): int {
            $sequence = $expectedVersionSequence;
            $fingerprint = $expectedFingerprint;

            foreach ($items as $index => [$item, $proposal, $amountMinor]) {
                $this->completeResponsibility->handle(
                    $item,
                    $actor,
                    BusinessPermitEvaluationApplicability::Applicable,
                    [
                        'amount_cents' => $amountMinor,
                        'inspection' => [
                            'required' => false,
                            'mode' => null,
                            'completed' => false,
                            'findings' => null,
                        ],
                    ],
                    $proposal->source_classification,
                    null,
                    $sequence,
                    $fingerprint,
                    "{$idempotencyKey}:{$item->id}",
                );

                if ($index < $items->count() - 1) {
                    $evaluation = $evaluation->fresh();
                    $nextProjection = $this->resolver->resolve($evaluation);
                    $sequence = $nextProjection['version_sequence'];
                    $fingerprint = $nextProjection['current_fingerprint'];
                }
            }

            return $items->count();
        });
    }
}
