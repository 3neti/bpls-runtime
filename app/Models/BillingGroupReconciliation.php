<?php

namespace App\Models;

use App\Enums\BillingGroupEvidenceType;
use App\Enums\BillingGroupReconciliationStatus;
use Database\Factories\BillingGroupReconciliationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $billing_group_id
 * @property int $recorded_by_id
 * @property int $version
 * @property BillingGroupEvidenceType $evidence_type
 * @property string $evidence_reference
 * @property string|null $source_excerpt
 * @property string|null $operational_interpretation
 * @property list<string> $unresolved_questions
 * @property BillingGroupReconciliationStatus $reconciliation_status
 * @property string $execution_status
 * @property string $execution_reason
 * @property array<string, mixed> $definition_snapshot
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BillingGroup $billingGroup
 * @property-read User $recordedBy
 */
#[Fillable(['billing_group_id', 'recorded_by_id', 'version', 'evidence_type', 'evidence_reference', 'source_excerpt', 'operational_interpretation', 'unresolved_questions', 'reconciliation_status', 'execution_status', 'execution_reason', 'definition_snapshot', 'metadata'])]
class BillingGroupReconciliation extends Model
{
    /** @use HasFactory<BillingGroupReconciliationFactory> */
    use HasFactory;

    protected $attributes = [
        'reconciliation_status' => 'pending_municipal_decision',
        'execution_status' => 'blocked',
    ];

    /** @return BelongsTo<BillingGroup, $this> */
    public function billingGroup(): BelongsTo
    {
        return $this->belongsTo(BillingGroup::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'evidence_type' => BillingGroupEvidenceType::class,
            'unresolved_questions' => 'array',
            'reconciliation_status' => BillingGroupReconciliationStatus::class,
            'definition_snapshot' => 'array',
            'metadata' => 'array',
        ];
    }
}
