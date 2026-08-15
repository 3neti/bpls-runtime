<?php

namespace App\Models;

use App\Enums\BillingGroupFieldType;
use Database\Factories\BillingGroupFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $billing_group_id
 * @property string $key
 * @property string $name
 * @property BillingGroupFieldType $field_type
 * @property bool $is_required
 * @property bool $is_unique
 * @property int $sort_order
 * @property list<string>|null $options
 * @property string|null $placeholder
 * @property string|null $default_value
 */
#[Fillable(['billing_group_id', 'key', 'name', 'field_type', 'is_required', 'is_unique', 'sort_order', 'options', 'placeholder', 'default_value'])]
class BillingGroupField extends Model
{
    /** @use HasFactory<BillingGroupFieldFactory> */
    use HasFactory;

    /** @return BelongsTo<BillingGroup, $this> */
    public function billingGroup(): BelongsTo
    {
        return $this->belongsTo(BillingGroup::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'field_type' => BillingGroupFieldType::class,
            'is_required' => 'boolean',
            'is_unique' => 'boolean',
            'options' => 'array',
        ];
    }
}
