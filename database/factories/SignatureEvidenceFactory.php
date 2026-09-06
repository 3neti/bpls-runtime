<?php

namespace Database\Factories;

use App\Models\PermitApplicationDeclaration;
use App\Models\SignatureEvidence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SignatureEvidence>
 */
class SignatureEvidenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'signer_id' => User::factory(),
            'signable_type' => PermitApplicationDeclaration::class,
            'signable_id' => PermitApplicationDeclaration::factory(),
            'purpose' => 'applicant_lodging',
            'method' => 'captured_facsimile',
            'evidence_digest' => hash('sha256', fake()->unique()->uuid()),
            'source_snapshot' => ['classification' => 'synthetic_test_fixture'],
            'captured_at' => now(),
        ];
    }
}
