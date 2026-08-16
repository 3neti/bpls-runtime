<?php

namespace Database\Factories;

use App\Enums\PermitClearanceStatus;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyClearanceTypeReconciliation;
use App\Models\LegacyPermitClearanceMapping;
use App\Models\LegacyRecord;
use App\Models\PermitClearance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyPermitClearanceMapping>
 */
class LegacyPermitClearanceMappingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $applicationLegacyId = fake()->unique()->uuid();
        $clearanceLegacyId = fake()->unique()->uuid();
        $clearanceTypeLegacyId = fake()->unique()->uuid();
        $payload = [
            '_id' => $clearanceLegacyId,
            'applicationId' => $applicationLegacyId,
            'clearanceTypeId' => $clearanceTypeLegacyId,
            'isCompleted' => false,
            'assignedAt' => now()->toIso8601String(),
        ];

        return [
            'legacy_permit_evidence_execution_id' => null,
            'legacy_record_id' => LegacyRecord::factory()->state([
                'dataset_key' => 'permit_clearances',
                'entity_type' => 'permit_clearance',
                'legacy_id' => $clearanceLegacyId,
                'payload' => $payload,
                'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            ]),
            'legacy_source_id' => fn (array $attributes): int => LegacyRecord::query()->whereKey($attributes['legacy_record_id'])->sole()->legacy_source_id,
            'legacy_import_batch_id' => fn (array $attributes): int => LegacyRecord::query()->whereKey($attributes['legacy_record_id'])->sole()->legacy_import_batch_id,
            'legacy_application_id_mapping_id' => function (array $attributes) use ($applicationLegacyId): int {
                $record = LegacyRecord::query()->whereKey($attributes['legacy_record_id'])->sole();

                return LegacyApplicationIdMapping::factory()->create([
                    'legacy_source_id' => $record->legacy_source_id,
                    'legacy_import_batch_id' => $record->legacy_import_batch_id,
                    'dataset_key' => 'applications',
                    'legacy_id' => $applicationLegacyId,
                ])->id;
            },
            'legacy_clearance_type_reconciliation_id' => function (array $attributes) use ($clearanceTypeLegacyId): int {
                $record = LegacyRecord::query()->whereKey($attributes['legacy_record_id'])->sole();

                return LegacyClearanceTypeReconciliation::factory()->create([
                    'legacy_source_id' => $record->legacy_source_id,
                    'source_legacy_id' => $clearanceTypeLegacyId,
                ])->id;
            },
            'permit_clearance_id' => function (array $attributes): int {
                $applicationMapping = LegacyApplicationIdMapping::query()->whereKey($attributes['legacy_application_id_mapping_id'])->sole();
                $reconciliation = LegacyClearanceTypeReconciliation::query()->whereKey($attributes['legacy_clearance_type_reconciliation_id'])->sole();
                $record = LegacyRecord::query()->whereKey($attributes['legacy_record_id'])->sole();

                return PermitClearance::factory()->create([
                    'permit_application_id' => $applicationMapping->permit_application_id,
                    'code' => $reconciliation->target_code,
                    'label' => $reconciliation->target_label,
                    'status' => PermitClearanceStatus::Pending,
                    'legacy_source_id' => $record->legacy_id,
                ])->id;
            },
            'dataset_key' => fn (array $attributes): string => LegacyRecord::query()->whereKey($attributes['legacy_record_id'])->sole()->dataset_key,
            'legacy_id' => fn (array $attributes): string => LegacyRecord::query()->whereKey($attributes['legacy_record_id'])->sole()->legacy_id,
            'status' => 'mapped',
            'mapping_basis' => 'fixture',
            'metadata' => ['fixture' => true],
        ];
    }
}
