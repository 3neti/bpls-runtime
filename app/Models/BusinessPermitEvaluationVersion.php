<?php

namespace App\Models;

use Database\Factories\BusinessPermitEvaluationVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $business_permit_evaluation_id
 * @property int $sequence
 * @property string $fingerprint
 * @property string $reason
 * @property int|null $created_by_id
 * @property array<string, mixed>|null $metadata
 * @property-read BusinessPermitEvaluation $evaluation
 * @property-read Collection<int, BusinessPermitEvaluationItemRevision> $revisions
 * @property-read BusinessPermitEvaluationCounterCheck|null $counterCheck
 */
#[Fillable(['business_permit_evaluation_id', 'sequence', 'fingerprint', 'reason', 'created_by_id', 'metadata'])]
class BusinessPermitEvaluationVersion extends Model
{
    /** @use HasFactory<BusinessPermitEvaluationVersionFactory> */
    use HasFactory;

    /** @return BelongsTo<BusinessPermitEvaluation, $this> */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(BusinessPermitEvaluation::class, 'business_permit_evaluation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return HasMany<BusinessPermitEvaluationItemRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(BusinessPermitEvaluationItemRevision::class)->orderBy('id');
    }

    /** @return HasOne<BusinessPermitEvaluationCounterCheck, $this> */
    public function counterCheck(): HasOne
    {
        return $this->hasOne(BusinessPermitEvaluationCounterCheck::class);
    }

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
