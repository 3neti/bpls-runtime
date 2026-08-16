<?php

namespace App\Models;

use App\Enums\LegacyMappingProposalStatus;
use Database\Factories\LegacyPermitEvidenceProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $legacy_permit_evidence_plan_id
 * @property int $legacy_record_id
 * @property int|null $legacy_clearance_type_reconciliation_id
 * @property int|null $legacy_document_object_reconciliation_id
 * @property string $source_dataset
 * @property string $kind
 * @property string $item_key
 * @property LegacyMappingProposalStatus $status
 * @property string $projection_hash
 * @property list<string>|null $reasons
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_permit_evidence_plan_id', 'legacy_record_id', 'legacy_clearance_type_reconciliation_id', 'legacy_document_object_reconciliation_id', 'source_dataset', 'kind', 'item_key', 'status', 'projection_hash', 'reasons', 'metadata'])]
class LegacyPermitEvidenceProposal extends Model
{
    /** @use HasFactory<LegacyPermitEvidenceProposalFactory> */
    use HasFactory;

    /** @return BelongsTo<LegacyPermitEvidencePlan, $this> */
    public function mappingPlan(): BelongsTo
    {
        return $this->belongsTo(LegacyPermitEvidencePlan::class, 'legacy_permit_evidence_plan_id');
    }

    /** @return BelongsTo<LegacyRecord, $this> */
    public function legacyRecord(): BelongsTo
    {
        return $this->belongsTo(LegacyRecord::class);
    }

    /** @return BelongsTo<LegacyClearanceTypeReconciliation, $this> */
    public function clearanceReconciliation(): BelongsTo
    {
        return $this->belongsTo(LegacyClearanceTypeReconciliation::class, 'legacy_clearance_type_reconciliation_id');
    }

    /** @return BelongsTo<LegacyDocumentObjectReconciliation, $this> */
    public function documentReconciliation(): BelongsTo
    {
        return $this->belongsTo(LegacyDocumentObjectReconciliation::class, 'legacy_document_object_reconciliation_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => LegacyMappingProposalStatus::class, 'reasons' => 'array', 'metadata' => 'array'];
    }
}
