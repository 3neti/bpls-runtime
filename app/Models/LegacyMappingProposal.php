<?php

namespace App\Models;

use App\Enums\LegacyMappingProposalAction;
use App\Enums\LegacyMappingProposalStatus;
use Database\Factories\LegacyMappingProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $legacy_mapping_plan_id
 * @property int $legacy_record_id
 * @property int|null $parent_legacy_record_id
 * @property string $dataset_key
 * @property string $entity_type
 * @property string $target_type
 * @property int|null $target_id
 * @property LegacyMappingProposalAction $proposed_action
 * @property LegacyMappingProposalStatus $status
 * @property string $identity_fingerprint
 * @property string $projection_hash
 * @property array<string, string>|null $collision_fingerprints
 * @property list<string>|null $reasons
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_mapping_plan_id', 'legacy_record_id', 'parent_legacy_record_id', 'dataset_key', 'entity_type', 'target_type', 'target_id', 'proposed_action', 'status', 'identity_fingerprint', 'projection_hash', 'collision_fingerprints', 'reasons', 'metadata'])]
class LegacyMappingProposal extends Model
{
    /** @use HasFactory<LegacyMappingProposalFactory> */
    use HasFactory;

    /** @return BelongsTo<LegacyMappingPlan, $this> */
    public function mappingPlan(): BelongsTo
    {
        return $this->belongsTo(LegacyMappingPlan::class, 'legacy_mapping_plan_id');
    }

    /** @return BelongsTo<LegacyRecord, $this> */
    public function legacyRecord(): BelongsTo
    {
        return $this->belongsTo(LegacyRecord::class);
    }

    /** @return BelongsTo<LegacyRecord, $this> */
    public function parentLegacyRecord(): BelongsTo
    {
        return $this->belongsTo(LegacyRecord::class, 'parent_legacy_record_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'proposed_action' => LegacyMappingProposalAction::class,
            'status' => LegacyMappingProposalStatus::class,
            'collision_fingerprints' => 'array',
            'reasons' => 'array',
            'metadata' => 'array',
        ];
    }
}
