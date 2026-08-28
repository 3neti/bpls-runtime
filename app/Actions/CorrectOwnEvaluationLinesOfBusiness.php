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
use LogicException;

class CorrectOwnEvaluationLinesOfBusiness
{
    public function __construct(private readonly ReviseBusinessPermitEvaluationItem $reviseItem) {}

    /** @param list<int> $lineOfBusinessIds */
    public function handle(
        BusinessPermitEvaluation $evaluation,
        array $lineOfBusinessIds,
        User $applicant,
        string $reason,
        int $expectedVersionSequence,
        string $expectedFingerprint,
        string $idempotencyKey,
    ): BusinessPermitEvaluationItemRevision {
        $evaluation->loadMissing('permitApplication.business.owner.users');
        if ($evaluation->permitApplication->submitted_by_id !== $applicant->id
            && ! $evaluation->permitApplication->business->owner->users->contains('id', $applicant->id)) {
            throw new LogicException('Only the owning applicant may correct this applicant-owned declaration.');
        }

        $ids = collect($lineOfBusinessIds)->map(fn (mixed $id): int => (int) $id)->unique()->sort()->values();
        if ($ids->isEmpty() || LineOfBusiness::query()->whereIn('id', $ids)->where('is_active', true)->count() !== $ids->count()) {
            throw new LogicException('Applicant correction requires at least one active canonical Line of Business.');
        }

        $item = $evaluation->items()->where('key', BusinessPermitEvaluationResolver::APPLICANT_LINES_ITEM_KEY)->first();
        if (! $item instanceof BusinessPermitEvaluationItem) {
            throw new LogicException('The original applicant declaration is unavailable.');
        }

        return $this->reviseItem->handle(
            $item,
            BusinessPermitEvaluationRevisionAction::Correction,
            BusinessPermitEvaluationApplicability::Applicable,
            ['line_of_business_ids' => $ids->all()],
            BusinessPermitEvaluationSource::ApplicantDeclaration,
            $applicant,
            $reason,
            $expectedVersionSequence,
            $expectedFingerprint,
            $idempotencyKey,
        );
    }
}
