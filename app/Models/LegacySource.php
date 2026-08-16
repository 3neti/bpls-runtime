<?php

namespace App\Models;

use Database\Factories\LegacySourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $key
 * @property string $title
 * @property string $source_type
 * @property string|null $baseline
 * @property string|null $archive_checksum
 * @property array<string, mixed> $provenance
 * @property string $status
 * @property-read Collection<int, LegacyImportBatch> $importBatches
 */
#[Fillable(['key', 'title', 'source_type', 'baseline', 'archive_checksum', 'provenance', 'status'])]
class LegacySource extends Model
{
    /** @use HasFactory<LegacySourceFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'registered'];

    /** @return HasMany<LegacyImportBatch, $this> */
    public function importBatches(): HasMany
    {
        return $this->hasMany(LegacyImportBatch::class);
    }

    /** @return HasMany<LegacyRecord, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(LegacyRecord::class);
    }

    /** @return HasMany<LegacyIdMapping, $this> */
    public function idMappings(): HasMany
    {
        return $this->hasMany(LegacyIdMapping::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['provenance' => 'array'];
    }
}
