<?php

namespace Database\Factories;

use App\Enums\LegacyDocumentObjectReconciliationStatus;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyDocumentObjectReconciliation;
use App\Models\LegacyDocumentObjectStagingRun;
use App\Models\LegacyRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyDocumentObjectReconciliation>
 */
class LegacyDocumentObjectReconciliationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $legacyId = fake()->unique()->uuid();
        $storageReference = 'storage:'.fake()->unique()->uuid();
        $documentType = 'Supporting Document';
        $originalName = fake()->unique()->word().'.pdf';
        $payload = [
            '_id' => $legacyId,
            'documents' => [[
                'storageId' => $storageReference,
                'documentType' => $documentType,
                'fileName' => $originalName,
                'uploadedAt' => now()->toIso8601String(),
            ]],
        ];

        return [
            'legacy_document_object_staging_run_id' => LegacyDocumentObjectStagingRun::factory(),
            'legacy_record_id' => function (array $attributes) use ($legacyId, $payload): int {
                $run = LegacyDocumentObjectStagingRun::query()->findOrFail($attributes['legacy_document_object_staging_run_id']);

                return LegacyRecord::factory()->create([
                    'legacy_import_batch_id' => $run->legacy_import_batch_id,
                    'legacy_source_id' => $run->importBatch->legacy_source_id,
                    'dataset_key' => 'businesses',
                    'entity_type' => 'business',
                    'legacy_id' => $legacyId,
                    'payload' => $payload,
                    'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
                ])->id;
            },
            'legacy_application_id_mapping_id' => function (array $attributes): int {
                $record = LegacyRecord::query()->findOrFail($attributes['legacy_record_id']);

                return LegacyApplicationIdMapping::factory()->create([
                    'legacy_import_batch_id' => $record->legacy_import_batch_id,
                    'legacy_source_id' => $record->legacy_source_id,
                ])->id;
            },
            'item_key' => 'document:0',
            'storage_reference_hash' => hash('sha256', $storageReference),
            'document_type_hash' => hash('sha256', $documentType),
            'original_name_hash' => hash('sha256', $originalName),
            'object_checksum' => hash('sha256', fake()->uuid()),
            'size_bytes' => 100,
            'mime_type' => 'application/pdf',
            'staged_disk' => 'local',
            'staged_path' => 'legacy-document-staging/'.fake()->uuid().'.pdf',
            'status' => LegacyDocumentObjectReconciliationStatus::Accepted,
            'decision_authority' => 'Factory authority',
            'evidence_reference' => 'FACTORY-'.fake()->unique()->uuid(),
            'decided_at' => now(),
            'metadata' => ['fixture' => true],
        ];
    }
}
