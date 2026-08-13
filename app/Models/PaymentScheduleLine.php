<?php

namespace App\Models;

use App\Enums\FeeRuleCategory;
use App\Enums\PaymentScheduleLineStatus;
use Database\Factories\PaymentScheduleLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payment_schedule_id
 * @property int|null $assessment_line_id
 * @property int|null $permit_application_line_id
 * @property int|null $line_of_business_id
 * @property string $code
 * @property string $name
 * @property FeeRuleCategory $category
 * @property Carbon|null $due_on
 * @property PaymentScheduleLineStatus $status
 * @property int $amount_cents
 * @property int $paid_amount_cents
 * @property array<string, mixed> $source_snapshot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['payment_schedule_id', 'assessment_line_id', 'permit_application_line_id', 'line_of_business_id', 'code', 'name', 'category', 'due_on', 'status', 'amount_cents', 'paid_amount_cents', 'source_snapshot'])]
class PaymentScheduleLine extends Model
{
    /** @use HasFactory<PaymentScheduleLineFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
        'paid_amount_cents' => 0,
    ];

    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class);
    }

    public function assessmentLine(): BelongsTo
    {
        return $this->belongsTo(AssessmentLine::class);
    }

    public function permitApplicationLine(): BelongsTo
    {
        return $this->belongsTo(PermitApplicationLine::class);
    }

    public function lineOfBusiness(): BelongsTo
    {
        return $this->belongsTo(LineOfBusiness::class);
    }

    public function collectionAllocations(): HasMany
    {
        return $this->hasMany(CollectionAllocation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => FeeRuleCategory::class,
            'due_on' => 'date',
            'status' => PaymentScheduleLineStatus::class,
            'source_snapshot' => 'array',
        ];
    }
}
