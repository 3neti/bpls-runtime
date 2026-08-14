<?php

namespace Database\Factories;

use App\Models\PermitApplication;
use App\Models\PermitApplicationDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PermitApplicationDocument>
 */
class PermitApplicationDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'permit_application_id' => PermitApplication::factory(),
            'label' => fake()->words(3, true),
            'original_name' => fake()->word().'.pdf',
            'storage_disk' => 'local',
            'path' => 'permit-applications/'.fake()->uuid().'/documents/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(100, 10_000),
            'source_snapshot' => [
                'classification' => 'supporting_evidence',
                'requirement_catalog_status' => 'unresolved',
            ],
            'uploaded_at' => now(),
        ];
    }
}
