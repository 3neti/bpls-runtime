<?php

namespace App\Models;

use Database\Factories\LineOfBusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $code
 * @property string $name
 * @property string|null $major_category
 * @property bool $is_active
 * @property string|null $legacy_source_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'major_category', 'is_active', 'legacy_source_id', 'metadata'])]
class LineOfBusiness extends Model
{
    /** @use HasFactory<LineOfBusinessFactory> */
    use HasFactory;

    public function feeRules(): HasMany
    {
        return $this->hasMany(FeeRule::class);
    }

    public function permitApplicationLines(): HasMany
    {
        return $this->hasMany(PermitApplicationLine::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
