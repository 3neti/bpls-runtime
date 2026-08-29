<?php

namespace Database\Factories;

use App\Actions\InitializeBusinessPermitEvaluation;
use App\Models\BusinessPermitEvaluationCounterCheck;
use App\Models\BusinessPermitEvaluationVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BusinessPermitEvaluationCounterCheck> */
class BusinessPermitEvaluationCounterCheckFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_permit_evaluation_version_id' => BusinessPermitEvaluationVersion::factory(),
            'checked_by_id' => User::factory(),
            'evidence_provenance' => InitializeBusinessPermitEvaluation::EVIDENCE_PROVENANCE,
            'checked_at' => now(),
        ];
    }
}
