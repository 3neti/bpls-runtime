<?php

namespace App\Actions;

use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationRevisionAction;
use App\Enums\BusinessPermitEvaluationSource;
use App\Evaluation\BusinessPermitEvaluationResolver;
use App\Evaluation\BusinessPermitEvaluationVersioner;
use App\Models\BusinessPermitEvaluation;
use App\Models\BusinessPermitEvaluationItem;
use App\Models\BusinessPermitEvaluationItemRevision;
use App\Models\BusinessPermitEvaluationVersion;
use App\Models\User;

class RefreshBusinessPermitEvaluation
{
    public function __construct(
        private readonly BusinessPermitEvaluationVersioner $versioner,
        private readonly BusinessPermitEvaluationResolver $resolver,
    ) {}

    public function handle(
        BusinessPermitEvaluation $evaluation,
        ?User $actor = null,
        ?int $expectedVersionSequence = null,
        ?string $expectedFingerprint = null,
    ): BusinessPermitEvaluationVersion {
        $projection = $this->resolver->resolve($evaluation);
        $resolvedLineOfBusinessIds = $projection['resolved_line_of_business_ids'];

        return $this->versioner->create(
            $evaluation,
            $actor,
            'dynamic_dependencies_refreshed',
            function (BusinessPermitEvaluationVersion $version) use ($evaluation, $resolvedLineOfBusinessIds, $actor): void {
                $evaluation->items()->with('revisions.version')->get()->each(function (BusinessPermitEvaluationItem $item) use ($version, $resolvedLineOfBusinessIds, $actor): void {
                    if (data_get($item->metadata, 'fixture_dependency.semantic_classification') !== BusinessPermitEvaluationSource::ProvisionalUat->value) {
                        return;
                    }

                    $lineOfBusinessId = data_get($item->metadata, 'fixture_dependency.line_of_business_id');
                    if (! is_int($lineOfBusinessId)) {
                        return;
                    }

                    $latest = $item->revisions
                        ->sortBy(fn (BusinessPermitEvaluationItemRevision $revision): int => $revision->version->sequence)
                        ->last();
                    $proposal = $item->revisions->first(
                        fn (BusinessPermitEvaluationItemRevision $revision): bool => $revision->action === BusinessPermitEvaluationRevisionAction::Proposal,
                    );
                    if (! $latest instanceof BusinessPermitEvaluationItemRevision || ! $proposal instanceof BusinessPermitEvaluationItemRevision) {
                        return;
                    }

                    $applicability = in_array($lineOfBusinessId, $resolvedLineOfBusinessIds, true)
                        ? BusinessPermitEvaluationApplicability::Applicable
                        : BusinessPermitEvaluationApplicability::NotApplicable;
                    if ($latest->applicability === $applicability) {
                        return;
                    }

                    $item->revisions()->create([
                        'business_permit_evaluation_version_id' => $version->id,
                        'action' => BusinessPermitEvaluationRevisionAction::Proposal,
                        'applicability' => $applicability,
                        'value' => $proposal->value,
                        'source_classification' => BusinessPermitEvaluationSource::ProvisionalUat,
                        'actor_id' => $actor?->id,
                        'reason' => 'Deterministic provisional UAT applicability was reevaluated from the resolved Line(s) of Business.',
                        'occurred_at' => now(),
                    ]);
                });
            },
            $expectedVersionSequence,
            $expectedFingerprint,
        );
    }
}
