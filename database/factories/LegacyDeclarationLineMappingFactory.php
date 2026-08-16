<?php

namespace Database\Factories;

use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyDeclarationLineMapping;
use App\Models\LegacyLineOfBusinessReconciliation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyDeclarationLineMapping>
 */
class LegacyDeclarationLineMappingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_declaration_mapping_execution_id' => null,
            'legacy_application_id_mapping_id' => LegacyApplicationIdMapping::factory(),
            'legacy_line_of_business_reconciliation_id' => fn (array $attributes): int => LegacyLineOfBusinessReconciliation::factory()->create([
                'legacy_source_id' => LegacyApplicationIdMapping::query()->whereKey($attributes['legacy_application_id_mapping_id'])->sole()->legacy_source_id,
            ])->id,
            'legacy_source_id' => fn (array $attributes): int => LegacyApplicationIdMapping::query()->whereKey($attributes['legacy_application_id_mapping_id'])->sole()->legacy_source_id,
            'legacy_import_batch_id' => fn (array $attributes): int => LegacyApplicationIdMapping::query()->whereKey($attributes['legacy_application_id_mapping_id'])->sole()->legacy_import_batch_id,
            'permit_application_line_id' => function (array $attributes): int {
                $applicationMapping = LegacyApplicationIdMapping::query()->whereKey($attributes['legacy_application_id_mapping_id'])->sole();
                $reconciliation = LegacyLineOfBusinessReconciliation::query()->whereKey($attributes['legacy_line_of_business_reconciliation_id'])->sole();

                return $applicationMapping->permitApplication()->firstOrFail()->lines()->create([
                    'line_of_business_id' => $reconciliation->line_of_business_id,
                    'declared_gross_sales_cents' => 0,
                    'capital_investment_cents' => 0,
                    'quantity' => 1,
                    'metadata' => [],
                ])->id;
            },
            'dataset_key' => 'applications',
            'legacy_id' => fake()->unique()->uuid(),
            'line_index' => 0,
            'status' => 'mapped',
            'mapping_basis' => 'factory',
            'metadata' => [],
        ];
    }
}
