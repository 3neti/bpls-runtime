<?php

namespace App\Models;

use App\Enums\LegacyMappingProposalStatus;
use Database\Factories\LegacyHistoricalFinancialPreservationProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $legacy_historical_financial_preservation_plan_id
 * @property int $legacy_record_id
 * @property int|null $legacy_application_id_mapping_id
 * @property LegacyMappingProposalStatus $status
 * @property string $projection_hash
 * @property list<string>|null $reasons
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_historical_financial_preservation_plan_id', 'legacy_record_id', 'legacy_application_id_mapping_id', 'status', 'projection_hash', 'reasons', 'metadata'])]
class LegacyHistoricalFinancialPreservationProposal extends Model
{
    /** @use HasFactory<LegacyHistoricalFinancialPreservationProposalFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'blocked'];

    /** @return BelongsTo<LegacyHistoricalFinancialPreservationPlan, $this> */
    public function preservationPlan(): BelongsTo
    {
        return $this->belongsTo(LegacyHistoricalFinancialPreservationPlan::class, 'legacy_historical_financial_preservation_plan_id');
    }

    /** @return BelongsTo<LegacyRecord, $this> */
    public function legacyRecord(): BelongsTo
    {
        return $this->belongsTo(LegacyRecord::class);
    }

    /** @return BelongsTo<LegacyApplicationIdMapping, $this> */
    public function applicationMapping(): BelongsTo
    {
        return $this->belongsTo(LegacyApplicationIdMapping::class, 'legacy_application_id_mapping_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => LegacyMappingProposalStatus::class, 'reasons' => 'array', 'metadata' => 'array'];
    }
}
