<?php

namespace Database\Factories;

use App\Models\BusinessPermitEvaluation;
use App\Models\BusinessPermitEvaluationVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BusinessPermitEvaluationVersion> */
class BusinessPermitEvaluationVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_permit_evaluation_id' => BusinessPermitEvaluation::factory(),
            'sequence' => 1,
            'fingerprint' => hash('sha256', fake()->uuid()),
            'reason' => 'test_version',
            'metadata' => [],
        ];
    }
}
