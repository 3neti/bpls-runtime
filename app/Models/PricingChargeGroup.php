<?php

namespace App\Models;

use Database\Factories\PricingChargeGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use LogicException;

/** Append-only browsing hierarchy; it does not determine fiscal eligibility. */
class PricingChargeGroup extends Model
{
    /** @use HasFactory<PricingChargeGroupFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'parent_id'];

    protected static function booted(): void
    {
        static::creating(function (self $group): void {
            foreach (['code', 'name'] as $field) {
                if (! is_string($group->getAttribute($field)) || trim($group->getAttribute($field)) === '') {
                    throw new InvalidArgumentException('Group code and name must be explicit.');
                }
            }
            $parent = $group->getAttribute('parent_id');
            if ($parent !== null && (! self::query()->whereKey($parent)->exists()
                || (string) $parent === (string) $group->getKey())) {
                throw new InvalidArgumentException('Parent must be an existing different group.');
            }
        });
        static::updating(function (): never {
            throw new LogicException('Group hierarchy is immutable; use a new identity.');
        });
        static::deleting(function (): never {
            throw new LogicException('Group definitions must be retained.');
        });
    }

    /** @return BelongsTo<PricingChargeGroup, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
