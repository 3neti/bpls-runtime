<?php

namespace App\Models;

use App\Enums\LegacyMappingProposalAction;
use App\Enums\LegacyMappingProposalStatus;
use Database\Factories\LegacyApplicationMappingProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $legacy_application_mapping_plan_id
 * @property int $legacy_record_id
 * @property int|null $owner_mapping_id
 * @property int|null $business_mapping_id
 * @property int|null $target_id
 * @property LegacyMappingProposalAction $proposed_action
 * @property LegacyMappingProposalStatus $status
 * @property string $identity_fingerprint
 * @property string $projection_hash
 * @property array<string, string>|null $collision_fingerprints
 * @property list<string>|null $reasons
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_application_mapping_plan_id', 'legacy_record_id', 'owner_mapping_id', 'business_mapping_id', 'target_id', 'proposed_action', 'status', 'identity_fingerprint', 'projection_hash', 'collision_fingerprints', 'reasons', 'metadata'])]
class LegacyApplicationMappingProposal extends Model
{
    /** @use HasFactory<LegacyApplicationMappingProposalFactory> */
    use HasFactory;

    /** @return BelongsTo<LegacyApplicationMappingPlan, $this> */
    public function mappingPlan(): BelongsTo
    {
        return $this->belongsTo(LegacyApplicationMappingPlan::class, 'legacy_application_mapping_plan_id');
    }

    /** @return BelongsTo<LegacyRecord, $this> */
    public function legacyRecord(): BelongsTo
    {
        return $this->belongsTo(LegacyRecord::class);
    }

    /** @return BelongsTo<LegacyIdMapping, $this> */
    public function ownerMapping(): BelongsTo
    {
        return $this->belongsTo(LegacyIdMapping::class, 'owner_mapping_id');
    }

    /** @return BelongsTo<LegacyIdMapping, $this> */
    public function businessMapping(): BelongsTo
    {
        return $this->belongsTo(LegacyIdMapping::class, 'business_mapping_id');
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
