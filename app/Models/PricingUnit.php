<?php

namespace App\Models;

use Database\Factories\PricingUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

/** A quantity dimension, never a price or automatic conversion. */
class PricingUnit extends Model
{
    /** @use HasFactory<PricingUnitFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'dimension', 'decimal_places'];

    protected static function booted(): void
    {
        static::creating(function (self $unit): void {
            foreach (['code', 'name', 'dimension'] as $field) {
                if (! is_string($unit->getAttribute($field)) || trim($unit->getAttribute($field)) === '') {
                    throw new InvalidArgumentException('Unit identity and dimension must be explicit.');
                }
            }
            $precision = $unit->getAttributes()['decimal_places'] ?? null;
            if (! is_int($precision) || $precision < 0 || $precision > 12) {
                throw new InvalidArgumentException('Unit precision must be an integer from zero through twelve.');
            }
        });
        static::updating(function (): never {
            throw new LogicException('Unit definitions are immutable; use a new identity.');
        });
        static::deleting(function (): never {
            throw new LogicException('Unit definitions must be retained.');
        });
    }

    protected function casts(): array
    {
        return ['decimal_places' => 'integer'];
    }
}
