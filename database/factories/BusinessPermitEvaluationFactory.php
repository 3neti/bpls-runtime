<?php

namespace Database\Factories;

use App\Models\BusinessPermitEvaluation;
use App\Models\PermitApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BusinessPermitEvaluation> */
class BusinessPermitEvaluationFactory extends Factory
{
    public function definition(): array
    {
        return ['permit_application_id' => PermitApplication::factory(), 'created_by_id' => null];
    }
}
