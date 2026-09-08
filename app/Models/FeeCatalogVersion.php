<?php

namespace App\Models;

use App\Enums\FeeCatalogVersionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $title
 * @property FeeCatalogVersionStatus $status
 * @property Carbon $effective_from
 * @property Carbon|null $effective_until
 * @property string|null $authority_reference
 * @property string $source_sha256
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['code', 'title', 'status', 'effective_from', 'effective_until', 'authority_reference', 'source_sha256', 'metadata'])]
class FeeCatalogVersion extends Model
{
    /** @return HasMany<FeeRule, $this> */
    public function feeRules(): HasMany
    {
        return $this->hasMany(FeeRule::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => FeeCatalogVersionStatus::class,
            'effective_from' => 'date',
            'effective_until' => 'date',
            'metadata' => 'array',
        ];
    }
}
