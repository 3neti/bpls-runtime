<?php

namespace App\Actions;

use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationRevisionAction;
use App\Enums\BusinessPermitEvaluationSource;
use App\Evaluation\BusinessPermitEvaluationResolver;
use App\Models\BusinessPermitEvaluation;
use App\Models\BusinessPermitEvaluationItem;
use App\Models\BusinessPermitEvaluationItemRevision;
use App\Models\LineOfBusiness;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class CorrectEvaluationLinesOfBusiness
{
    public function __construct(
        private readonly ReviseBusinessPermitEvaluationItem $reviseItem,
        private readonly RefreshBusinessPermitEvaluation $refreshEvaluation,
    ) {}

    /** @param list<int> $lineOfBusinessIds */
    public function handle(
        BusinessPermitEvaluation $evaluation,
        array $lineOfBusinessIds,
        User $treasuryActor,
        string $reason,
        ?int $expectedVersionSequence = null,
        ?string $expectedFingerprint = null,
        ?string $idempotencyKey = null,
    ): BusinessPermitEvaluationItemRevision {
        return DB::transaction(function () use ($evaluation, $lineOfBusinessIds, $treasuryActor, $reason, $expectedVersionSequence, $expectedFingerprint, $idempotencyKey): BusinessPermitEvaluationItemRevision {
            if (blank($reason)) {
                throw new LogicException('Treasury Line of Business correction requires a reason.');
            }

            $normalizedIds = collect($lineOfBusinessIds)->map(fn (mixed $id): int => (int) $id)->unique()->sort()->values();
            if ($normalizedIds->isEmpty()) {
                throw new LogicException('Treasury must resolve at least one Line of Business within the same permit application.');
            }

            $supportedIds = LineOfBusiness::query()
                ->availableToMunicipalCatalog()
                ->whereIn('id', $normalizedIds)
                ->pluck('id')
                ->sort()
                ->values();
            if ($supportedIds->all() !== $normalizedIds->all()) {
                throw new LogicException('Treasury correction may select only active canonical Line of Business records. It cannot create a legal Business or establishment.');
            }

            $item = $evaluation->items()
                ->where('key', BusinessPermitEvaluationResolver::APPLICANT_LINES_ITEM_KEY)
                ->first();
            if (! $item instanceof BusinessPermitEvaluationItem) {
                throw new LogicException('The Evaluation has no preserved applicant Line of Business declaration.');
            }

            $revision = $this->reviseItem->handle(
                $item,
                BusinessPermitEvaluationRevisionAction::AuthorizedDetermination,
                BusinessPermitEvaluationApplicability::Applicable,
                ['line_of_business_ids' => $normalizedIds->all()],
                BusinessPermitEvaluationSource::BoardOperationalRecollection,
                $treasuryActor,
                $reason,
                $expectedVersionSequence,
                $expectedFingerprint,
                $idempotencyKey,
            );

            $revisionIsCurrent = $evaluation->versions()->latest('sequence')->value('id') === $revision->business_permit_evaluation_version_id;
            if ($revisionIsCurrent && $evaluation->items()->get()->contains(
                fn (BusinessPermitEvaluationItem $candidate): bool => data_get($candidate->metadata, 'fixture_dependency.semantic_classification') === 'provisional_uat',
            )) {
                $this->refreshEvaluation->handle($evaluation, $treasuryActor);
            }

            return $revision;
        });
    }
}
