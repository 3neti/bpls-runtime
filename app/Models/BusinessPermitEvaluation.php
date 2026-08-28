<?php

namespace App\Models;

use Database\Factories\BusinessPermitEvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $permit_application_id
 * @property int|null $created_by_id
 * @property-read PermitApplication $permitApplication
 * @property-read Collection<int, BusinessPermitEvaluationVersion> $versions
 * @property-read Collection<int, BusinessPermitEvaluationItem> $items
 * @property-read BusinessPermitEvaluationVersion|null $currentVersion
 */
#[Fillable(['permit_application_id', 'created_by_id'])]
class BusinessPermitEvaluation extends Model
{
    /** @use HasFactory<BusinessPermitEvaluationFactory> */
    use HasFactory;

    /** @return BelongsTo<PermitApplication, $this> */
    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return HasMany<BusinessPermitEvaluationVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(BusinessPermitEvaluationVersion::class);
    }

    /** @return HasOne<BusinessPermitEvaluationVersion, $this> */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(BusinessPermitEvaluationVersion::class)->ofMany('sequence', 'max');
    }

    /** @return HasMany<BusinessPermitEvaluationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BusinessPermitEvaluationItem::class)->orderBy('key');
    }
}
