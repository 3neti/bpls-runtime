<?php

namespace App\Models;

use App\Enums\LegacyLineOfBusinessReconciliationStatus;
use Database\Factories\LegacyLineOfBusinessReconciliationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $legacy_source_id
 * @property string $source_dataset
 * @property string $source_value_hash
 * @property int|null $line_of_business_id
 * @property LegacyLineOfBusinessReconciliationStatus $status
 * @property string|null $decision_authority
 * @property string|null $evidence_reference
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_source_id', 'source_dataset', 'source_value_hash', 'line_of_business_id', 'status', 'decision_authority', 'evidence_reference', 'decided_at', 'metadata'])]
class LegacyLineOfBusinessReconciliation extends Model
{
    /** @use HasFactory<LegacyLineOfBusinessReconciliationFactory> */
    use HasFactory;

    protected $attributes = ['source_dataset' => 'groups', 'status' => 'pending'];

    /** @return BelongsTo<LegacySource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(LegacySource::class, 'legacy_source_id');
    }

    /** @return BelongsTo<LineOfBusiness, $this> */
    public function lineOfBusiness(): BelongsTo
    {
        return $this->belongsTo(LineOfBusiness::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => LegacyLineOfBusinessReconciliationStatus::class, 'decided_at' => 'datetime', 'metadata' => 'array'];
    }
}
