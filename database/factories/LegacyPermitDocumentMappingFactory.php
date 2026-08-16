<?php

namespace Database\Factories;

use App\Models\LegacyDocumentObjectReconciliation;
use App\Models\LegacyPermitDocumentMapping;
use App\Models\PermitApplicationDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyPermitDocumentMapping>
 */
class LegacyPermitDocumentMappingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_permit_evidence_execution_id' => null,
            'legacy_document_object_reconciliation_id' => LegacyDocumentObjectReconciliation::factory(),
            'legacy_record_id' => fn (array $attributes): int => LegacyDocumentObjectReconciliation::query()->findOrFail($attributes['legacy_document_object_reconciliation_id'])->legacy_record_id,
            'legacy_application_id_mapping_id' => fn (array $attributes): int => LegacyDocumentObjectReconciliation::query()->findOrFail($attributes['legacy_document_object_reconciliation_id'])->legacy_application_id_mapping_id,
            'legacy_source_id' => fn (array $attributes): int => LegacyDocumentObjectReconciliation::query()->findOrFail($attributes['legacy_document_object_reconciliation_id'])->legacyRecord->legacy_source_id,
            'legacy_import_batch_id' => fn (array $attributes): int => LegacyDocumentObjectReconciliation::query()->findOrFail($attributes['legacy_document_object_reconciliation_id'])->legacyRecord->legacy_import_batch_id,
            'permit_application_document_id' => function (array $attributes): int {
                $reconciliation = LegacyDocumentObjectReconciliation::query()->findOrFail($attributes['legacy_document_object_reconciliation_id']);

                return PermitApplicationDocument::factory()->create([
                    'permit_application_id' => $reconciliation->applicationMapping->permit_application_id,
                    'mime_type' => $reconciliation->mime_type,
                    'size_bytes' => $reconciliation->size_bytes,
                ])->id;
            },
            'item_key' => fn (array $attributes): string => LegacyDocumentObjectReconciliation::query()->findOrFail($attributes['legacy_document_object_reconciliation_id'])->item_key,
            'status' => 'mapped',
            'mapping_basis' => 'fixture',
            'metadata' => ['fixture' => true],
        ];
    }
}
