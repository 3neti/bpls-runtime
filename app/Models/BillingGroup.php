<?php

namespace App\Models;

use App\Enums\BillingGroupAcceptanceStatus;
use Database\Factories\BillingGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property BillingGroupAcceptanceStatus $acceptance_status
 * @property bool $is_active
 * @property string|null $legacy_source_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, BillingGroupField> $fields
 * @property-read Collection<int, BillingGroupRecord> $records
 */
#[Fillable(['name', 'description', 'acceptance_status', 'is_active', 'legacy_source_id', 'metadata'])]
class BillingGroup extends Model
{
    /** @use HasFactory<BillingGroupFactory> */
    use HasFactory;

    protected $attributes = [
        'acceptance_status' => 'provisional',
        'is_active' => true,
    ];

    /** @return HasMany<BillingGroupField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(BillingGroupField::class)->orderBy('sort_order');
    }

    /** @return HasMany<BillingGroupRecord, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(BillingGroupRecord::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'acceptance_status' => BillingGroupAcceptanceStatus::class,
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
