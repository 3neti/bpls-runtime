<?php

namespace App\Models;

use App\Enums\LegacyMappingProposalStatus;
use Database\Factories\LegacyDeclarationMappingProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $legacy_declaration_mapping_plan_id
 * @property int $legacy_record_id
 * @property int $line_index
 * @property int|null $legacy_line_of_business_reconciliation_id
 * @property int|null $line_of_business_id
 * @property LegacyMappingProposalStatus $status
 * @property string $projection_hash
 * @property list<string>|null $reasons
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_declaration_mapping_plan_id', 'legacy_record_id', 'line_index', 'legacy_line_of_business_reconciliation_id', 'line_of_business_id', 'status', 'projection_hash', 'reasons', 'metadata'])]
class LegacyDeclarationMappingProposal extends Model
{
    /** @use HasFactory<LegacyDeclarationMappingProposalFactory> */
    use HasFactory;

    /** @return BelongsTo<LegacyDeclarationMappingPlan, $this> */
    public function mappingPlan(): BelongsTo
    {
        return $this->belongsTo(LegacyDeclarationMappingPlan::class, 'legacy_declaration_mapping_plan_id');
    }

    /** @return BelongsTo<LegacyRecord, $this> */
    public function legacyRecord(): BelongsTo
    {
        return $this->belongsTo(LegacyRecord::class);
    }

    /** @return BelongsTo<LegacyLineOfBusinessReconciliation, $this> */
    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(LegacyLineOfBusinessReconciliation::class, 'legacy_line_of_business_reconciliation_id');
    }

    /** @return BelongsTo<LineOfBusiness, $this> */
    public function lineOfBusiness(): BelongsTo
    {
        return $this->belongsTo(LineOfBusiness::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => LegacyMappingProposalStatus::class, 'reasons' => 'array', 'metadata' => 'array'];
    }
}
