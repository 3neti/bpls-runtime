<?php

namespace App\Models;

use Database\Factories\PricingChargeItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use LogicException;

/** Structural identity only; no rate, fiscal activation or inferred account mapping. */
class PricingChargeItem extends Model
{
    /** @use HasFactory<PricingChargeItemFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'pricing_charge_group_id', 'fee_category_id', 'pricing_unit_id'];

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            foreach (['code', 'name'] as $field) {
                if (! is_string($item->getAttribute($field)) || trim($item->getAttribute($field)) === '') {
                    throw new InvalidArgumentException('Charge identity and name must be explicit.');
                }
            }
        });
        static::updating(function (): never {
            throw new LogicException('Charge definitions are immutable; use a new identity.');
        });
        static::deleting(function (): never {
            throw new LogicException('Charge definitions must be retained.');
        });
    }

    /** @return BelongsTo<PricingChargeGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(PricingChargeGroup::class, 'pricing_charge_group_id');
    }

    /** @return BelongsTo<PricingUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(PricingUnit::class, 'pricing_unit_id');
    }

    /** @return BelongsTo<FeeCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class, 'fee_category_id');
    }
}
