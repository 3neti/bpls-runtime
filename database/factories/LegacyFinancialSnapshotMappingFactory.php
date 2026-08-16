<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyFinancialSnapshotMapping;
use App\Models\LegacyRecord;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyFinancialSnapshotMapping>
 */
class LegacyFinancialSnapshotMappingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_financial_mapping_execution_id' => null,
            'legacy_record_id' => LegacyRecord::factory(),
            'legacy_source_id' => fn (array $attributes): int => LegacyRecord::query()->whereKey($attributes['legacy_record_id'])->sole()->legacy_source_id,
            'legacy_import_batch_id' => fn (array $attributes): int => LegacyRecord::query()->whereKey($attributes['legacy_record_id'])->sole()->legacy_import_batch_id,
            'legacy_application_id_mapping_id' => function (array $attributes): int {
                $record = LegacyRecord::query()->whereKey($attributes['legacy_record_id'])->sole();
                $application = PermitApplication::factory()->create();

                return LegacyApplicationIdMapping::factory()->create([
                    'legacy_source_id' => $record->legacy_source_id,
                    'legacy_import_batch_id' => $record->legacy_import_batch_id,
                    'permit_application_id' => $application->id,
                    'dataset_key' => 'business_permit_applications',
                ])->id;
            },
            'assessment_id' => function (array $attributes): int {
                $mapping = LegacyApplicationIdMapping::query()->whereKey($attributes['legacy_application_id_mapping_id'])->sole();

                return Assessment::factory()->create(['permit_application_id' => $mapping->permit_application_id])->id;
            },
            'payment_schedule_id' => function (array $attributes): int {
                $assessment = Assessment::query()->whereKey($attributes['assessment_id'])->sole();

                return PaymentSchedule::factory()->create([
                    'permit_application_id' => $assessment->permit_application_id,
                    'assessment_id' => $assessment->id,
                ])->id;
            },
            'dataset_key' => fn (array $attributes): string => LegacyRecord::query()->whereKey($attributes['legacy_record_id'])->sole()->dataset_key,
            'legacy_id' => fn (array $attributes): string => LegacyRecord::query()->whereKey($attributes['legacy_record_id'])->sole()->legacy_id,
            'status' => 'mapped',
            'mapping_basis' => 'approved_annual_single_section_snapshot',
            'metadata' => [],
        ];
    }
}
