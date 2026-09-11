<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable([
    'ipil_rescue_source_identity_id', 'source_dataset', 'association_state', 'evidence_role',
    'document_type', 'original_filename', 'source_sha256', 'source_size_bytes',
    'object_relative_path', 'import_accepted', 'spatie_imported', 'managed_copy_sha256',
    'source_manifest_entry',
])]
final class IpilHistoricalMediaEvidence extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'ipil_historical_media_evidence';

    public const ApplicationDocumentsCollection = 'application_documents';

    public const GeneratedArtifactsCollection = 'legacy_generated_artifacts';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::ApplicationDocumentsCollection)->useDisk('ipil_gate6');
        $this->addMediaCollection(self::GeneratedArtifactsCollection)->useDisk('ipil_gate6');
    }

    protected function casts(): array
    {
        return [
            'import_accepted' => 'boolean',
            'spatie_imported' => 'boolean',
            'source_manifest_entry' => 'array',
        ];
    }
}
