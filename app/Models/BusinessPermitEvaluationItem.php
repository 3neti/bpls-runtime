<?php

namespace App\Models;

use App\Enums\BusinessPermitEvaluationItemType;
use Database\Factories\BusinessPermitEvaluationItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $business_permit_evaluation_id
 * @property string $key
 * @property BusinessPermitEvaluationItemType $item_type
 * @property string $responsible_party
 * @property bool $is_required
 * @property bool $requires_confirmation
 * @property array<string, mixed>|null $metadata
 * @property-read BusinessPermitEvaluation $evaluation
 * @property-read Collection<int, BusinessPermitEvaluationItemRevision> $revisions
 */
#[Fillable(['business_permit_evaluation_id', 'key', 'item_type', 'responsible_party', 'is_required', 'requires_confirmation', 'metadata'])]
class BusinessPermitEvaluationItem extends Model
{
    /** @use HasFactory<BusinessPermitEvaluationItemFactory> */
    use HasFactory;

    /** @return BelongsTo<BusinessPermitEvaluation, $this> */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(BusinessPermitEvaluation::class, 'business_permit_evaluation_id');
    }

    /** @return HasMany<BusinessPermitEvaluationItemRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(BusinessPermitEvaluationItemRevision::class)->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'item_type' => BusinessPermitEvaluationItemType::class,
            'is_required' => 'boolean',
            'requires_confirmation' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
