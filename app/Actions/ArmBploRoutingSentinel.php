<?php

namespace App\Actions;

use App\Models\BploRoutingSuggestion;
use App\Models\PermitApplication;
use App\StakeholderPreview\StakeholderPreviewSafety;
use LogicException;

class ArmBploRoutingSentinel
{
    public function __construct(
        private readonly BuildBploRoutingSuggestion $buildSuggestion,
        private readonly StakeholderPreviewSafety $previewSafety,
    ) {}

    public function handle(PermitApplication $permitApplication): ?BploRoutingSuggestion
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $application = $permitApplication->loadMissing(['lines.lineOfBusiness', 'bploRoutingSuggestion', 'bploRoutingDetermination']);
        if ($application->submitted_at === null || $application->bploRoutingDetermination !== null) {
            return null;
        }

        if ($application->bploRoutingSuggestion instanceof BploRoutingSuggestion) {
            return $application->bploRoutingSuggestion;
        }

        $suggestion = $this->buildSuggestion->handle($application);
        if ($suggestion === null) {
            return null;
        }

        $reviewMinutes = config('bplo.routing_sentinel.review_minutes');
        if (! is_int($reviewMinutes) || $reviewMinutes < 1 || $reviewMinutes > 1_440) {
            throw new LogicException('The BPLO routing review window must be between one minute and one day.');
        }

        if (config('bplo.routing_sentinel.clock') !== 'elapsed') {
            throw new LogicException('Only the elapsed BPLO routing clock is commissioned for the laboratory.');
        }

        return $application->bploRoutingSuggestion()->firstOrCreate(
            ['permit_application_id' => $application->id],
            [
                ...$suggestion,
                'status' => BploRoutingSuggestion::AwaitingConfirmation,
                'lodged_at' => $application->submitted_at,
                'review_due_at' => $application->submitted_at->copy()->addMinutes($reviewMinutes),
            ],
        );
    }

    public function isEnabled(): bool
    {
        return config('bplo.routing_sentinel.enabled') === true
            && config('bplo.routing_sentinel.clock') === 'elapsed'
            && $this->previewSafety->isEnabled();
    }
}
