<?php

namespace App\Models;

use Database\Factories\BusinessPermitEvaluationCounterCheckFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_permit_evaluation_version_id
 * @property int $checked_by_id
 * @property string|null $reason
 * @property string $evidence_provenance
 * @property Carbon $checked_at
 */
#[Fillable(['business_permit_evaluation_version_id', 'checked_by_id', 'reason', 'evidence_provenance', 'checked_at'])]
class BusinessPermitEvaluationCounterCheck extends Model
{
    /** @use HasFactory<BusinessPermitEvaluationCounterCheckFactory> */
    use HasFactory;

    /** @return BelongsTo<BusinessPermitEvaluationVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(BusinessPermitEvaluationVersion::class, 'business_permit_evaluation_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by_id');
    }

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }
}
