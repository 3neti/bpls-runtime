<?php

namespace App\Actions;

use App\Enums\PermitApplicationStatus;
use App\Enums\StakeholderPreviewPersona;
use App\Models\BploRoutingDetermination;
use App\Models\BploRoutingSuggestion;
use App\Models\PermitApplication;
use App\Notifications\BploRoutingDefaulted;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Support\Facades\DB;

class ApplyDueBploRoutingSuggestions
{
    public function __construct(
        private readonly ArmBploRoutingSentinel $armSentinel,
        private readonly BuildBploRoutingSuggestion $buildSuggestion,
        private readonly RecordBploRoutingDetermination $recordRouting,
        private readonly StakeholderPreviewSafety $previewSafety,
    ) {}

    /** @return array{armed: int, defaulted: int, invalidated: int} */
    public function handle(): array
    {
        if (! $this->armSentinel->isEnabled()) {
            return ['armed' => 0, 'defaulted' => 0, 'invalidated' => 0];
        }

        // New submissions are armed by the canonical lodging action. Existing
        // laboratory records are armed only when BPLO deliberately opens them.
        // A scheduled sweep must never retroactively enroll historical fixtures.
        $armed = 0;
        $defaulted = 0;
        $invalidated = 0;
        $dueSuggestionIds = BploRoutingSuggestion::query()
            ->where('status', BploRoutingSuggestion::AwaitingConfirmation)
            ->where('review_due_at', '<=', now())
            ->orderBy('id')
            ->pluck('id');

        if ($dueSuggestionIds->isEmpty()) {
            return compact('armed', 'defaulted', 'invalidated');
        }

        $bploServiceActor = $this->previewSafety->account(StakeholderPreviewPersona::Bplo);
        $dueSuggestionIds->each(function (int $suggestionId) use ($bploServiceActor, &$defaulted, &$invalidated): void {
            $result = DB::transaction(function () use ($suggestionId, $bploServiceActor): string {
                $candidate = BploRoutingSuggestion::query()->find($suggestionId);
                if (! $candidate instanceof BploRoutingSuggestion) {
                    return 'unchanged';
                }

                $application = PermitApplication::query()->whereKey($candidate->permit_application_id)->lockForUpdate()->first();
                $suggestion = BploRoutingSuggestion::query()->whereKey($suggestionId)->lockForUpdate()->first();
                if (! $application instanceof PermitApplication
                    || ! $suggestion instanceof BploRoutingSuggestion
                    || $suggestion->status !== BploRoutingSuggestion::AwaitingConfirmation
                    || $suggestion->review_due_at->isFuture()) {
                    return 'unchanged';
                }

                $existing = $application->bploRoutingDetermination()->first();
                if ($existing instanceof BploRoutingDetermination) {
                    $suggestion->update([
                        'routing_determination_id' => $existing->id,
                        'status' => BploRoutingSuggestion::BploConfirmed,
                        'resolved_at' => $existing->determined_at,
                    ]);

                    return 'unchanged';
                }

                $currentSuggestion = $this->buildSuggestion->handle($application);
                if ($application->status === PermitApplicationStatus::Cancelled
                    || ! $application->canContinue()
                    || $currentSuggestion === null
                    || data_get($currentSuggestion, 'application_facts_snapshot.facts_hash') !== data_get($suggestion->application_facts_snapshot, 'facts_hash')) {
                    $suggestion->update([
                        'status' => BploRoutingSuggestion::Invalidated,
                        'resolved_at' => now(),
                    ]);

                    return 'invalidated';
                }

                $suggestion->setRelation('permitApplication', $application);
                $determination = $this->recordRouting->handleSystemDefault($suggestion, $bploServiceActor);

                return $determination->wasRecentlyCreated ? 'defaulted' : 'unchanged';
            }, 3);

            if ($result === 'defaulted') {
                $defaulted++;
                $bploServiceActor->notify(new BploRoutingDefaulted($suggestionId));
            } elseif ($result === 'invalidated') {
                $invalidated++;
            }
        });

        return compact('armed', 'defaulted', 'invalidated');
    }
}
