<?php

namespace App\Models;

use App\Enums\BillingGroupRecordStatus;
use Database\Factories\BillingGroupRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $billing_group_id
 * @property int $created_by_id
 * @property string $draft_reference
 * @property BillingGroupRecordStatus $status
 * @property string|null $description
 * @property Carbon|null $record_date
 * @property string|null $payor_name
 * @property array<string, string>|null $field_values
 * @property list<array<string, mixed>> $schema_snapshot
 * @property array<string, mixed> $source_snapshot
 */
#[Fillable(['billing_group_id', 'created_by_id', 'draft_reference', 'status', 'description', 'record_date', 'payor_name', 'field_values', 'schema_snapshot', 'source_snapshot'])]
class BillingGroupRecord extends Model
{
    /** @use HasFactory<BillingGroupRecordFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'draft',
    ];

    /** @return BelongsTo<BillingGroup, $this> */
    public function billingGroup(): BelongsTo
    {
        return $this->belongsTo(BillingGroup::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => BillingGroupRecordStatus::class,
            'record_date' => 'date',
            'field_values' => 'array',
            'schema_snapshot' => 'array',
            'source_snapshot' => 'array',
        ];
    }
}
