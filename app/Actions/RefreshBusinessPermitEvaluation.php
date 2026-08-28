<?php

namespace App\Actions;

use App\Evaluation\BusinessPermitEvaluationVersioner;
use App\Models\BusinessPermitEvaluation;
use App\Models\BusinessPermitEvaluationVersion;
use App\Models\User;

class RefreshBusinessPermitEvaluation
{
    public function __construct(private readonly BusinessPermitEvaluationVersioner $versioner) {}

    public function handle(
        BusinessPermitEvaluation $evaluation,
        ?User $actor = null,
        ?int $expectedVersionSequence = null,
        ?string $expectedFingerprint = null,
    ): BusinessPermitEvaluationVersion {
        return $this->versioner->create(
            $evaluation,
            $actor,
            'dynamic_dependencies_refreshed',
            static function (BusinessPermitEvaluationVersion $version): void {},
            $expectedVersionSequence,
            $expectedFingerprint,
        );
    }
}
