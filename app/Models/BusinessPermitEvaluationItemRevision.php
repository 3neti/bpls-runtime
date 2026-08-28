<?php

namespace App\Models;

use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationRevisionAction;
use App\Enums\BusinessPermitEvaluationSource;
use Database\Factories\BusinessPermitEvaluationItemRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_permit_evaluation_item_id
 * @property int $business_permit_evaluation_version_id
 * @property BusinessPermitEvaluationRevisionAction $action
 * @property BusinessPermitEvaluationApplicability $applicability
 * @property array<string, mixed>|null $value
 * @property BusinessPermitEvaluationSource $source_classification
 * @property string|null $idempotency_key
 * @property string|null $dependency_fingerprint
 * @property int|null $actor_id
 * @property string|null $reason
 * @property Carbon $occurred_at
 */
#[Fillable(['business_permit_evaluation_item_id', 'business_permit_evaluation_version_id', 'action', 'applicability', 'value', 'source_classification', 'idempotency_key', 'dependency_fingerprint', 'actor_id', 'reason', 'occurred_at'])]
class BusinessPermitEvaluationItemRevision extends Model
{
    /** @use HasFactory<BusinessPermitEvaluationItemRevisionFactory> */
    use HasFactory;

    /** @return BelongsTo<BusinessPermitEvaluationItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(BusinessPermitEvaluationItem::class, 'business_permit_evaluation_item_id');
    }

    /** @return BelongsTo<BusinessPermitEvaluationVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(BusinessPermitEvaluationVersion::class, 'business_permit_evaluation_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return [
            'action' => BusinessPermitEvaluationRevisionAction::class,
            'applicability' => BusinessPermitEvaluationApplicability::class,
            'value' => 'array',
            'source_classification' => BusinessPermitEvaluationSource::class,
            'occurred_at' => 'datetime',
        ];
    }
}
