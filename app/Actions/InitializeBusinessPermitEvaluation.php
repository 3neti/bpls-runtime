<?php

namespace App\Actions;

use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationItemType;
use App\Enums\BusinessPermitEvaluationRevisionAction;
use App\Enums\BusinessPermitEvaluationSource;
use App\Evaluation\BusinessPermitEvaluationResolver;
use App\Evaluation\BusinessPermitEvaluationVersioner;
use App\Models\BusinessPermitEvaluation;
use App\Models\BusinessPermitEvaluationVersion;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class InitializeBusinessPermitEvaluation
{
    public const EVIDENCE_PROVENANCE = 'Board operational recollection of in-person Nelson discussion — accepted for Evaluator V1 implementation, subject to later municipal correction during UAT.';

    public function __construct(private readonly BusinessPermitEvaluationVersioner $versioner) {}

    public function handle(PermitApplication $permitApplication, ?User $actor = null): BusinessPermitEvaluation
    {
        return DB::transaction(function () use ($permitApplication, $actor): BusinessPermitEvaluation {
            $permitApplication = PermitApplication::query()->whereKey($permitApplication->id)->lockForUpdate()->firstOrFail();
            $permitApplication->load('lines');

            if ($permitApplication->isHistoricalEvidenceOnly()) {
                throw new LogicException('Historical evidence applications cannot be given fabricated operational Evaluation history.');
            }

            if ($permitApplication->submitted_at === null) {
                throw new LogicException('A Business Permit Evaluation begins only after the permit application is lodged.');
            }

            $existing = $permitApplication->businessPermitEvaluation()->first();
            if ($existing instanceof BusinessPermitEvaluation) {
                return $existing->load('currentVersion');
            }

            $evaluation = $permitApplication->businessPermitEvaluation()->create(['created_by_id' => $actor?->id]);
            $lineItem = $evaluation->items()->create([
                'key' => BusinessPermitEvaluationResolver::APPLICANT_LINES_ITEM_KEY,
                'item_type' => BusinessPermitEvaluationItemType::Fact,
                'responsible_party' => 'applicant',
                'is_required' => true,
                'requires_confirmation' => false,
                'metadata' => [
                    'label' => 'Line(s) of Business',
                    'registry_mutation' => false,
                    'evidence_provenance' => self::EVIDENCE_PROVENANCE,
                ],
            ]);

            $this->versioner->create(
                $evaluation,
                $actor,
                'applicant_declaration_recorded',
                function (BusinessPermitEvaluationVersion $version) use ($lineItem, $permitApplication, $actor): void {
                    $lineItem->revisions()->create([
                        'business_permit_evaluation_version_id' => $version->id,
                        'action' => BusinessPermitEvaluationRevisionAction::Declaration,
                        'applicability' => BusinessPermitEvaluationApplicability::Applicable,
                        'value' => [
                            'line_of_business_ids' => $permitApplication->lines
                                ->pluck('line_of_business_id')->filter()->unique()->sort()->values()->all(),
                            'permit_application_line_ids' => $permitApplication->lines->pluck('id')->sort()->values()->all(),
                        ],
                        'source_classification' => BusinessPermitEvaluationSource::ApplicantDeclaration,
                        'actor_id' => $permitApplication->submitted_by_id ?? $actor?->id,
                        'reason' => 'Original applicant declaration copied into the new Evaluation without changing the application or Business registry.',
                        'occurred_at' => $permitApplication->submitted_at,
                    ]);
                },
            );

            return $evaluation->fresh(['currentVersion', 'items.revisions']);
        });
    }
}
