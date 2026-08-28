<?php

namespace App\Models;

use App\Enums\PaymentScheduleStatus;
use Database\Factories\PaymentScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $permit_application_id
 * @property int $assessment_id
 * @property int|null $prepared_by_id
 * @property int $sequence
 * @property PaymentScheduleStatus $status
 * @property string $payment_mode
 * @property Carbon|null $due_on
 * @property int $total_amount_cents
 * @property int $paid_amount_cents
 * @property array<string, mixed> $source_snapshot
 * @property string|null $legacy_source_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PermitApplication $permitApplication
 * @property-read Assessment $assessment
 * @property-read Collection<int, PaymentScheduleLine> $lines
 * @property-read Collection<int, TreasuryCollection> $treasuryCollections
 * @property-read XChangePayment|null $xChangePayment
 */
#[Fillable(['permit_application_id', 'assessment_id', 'prepared_by_id', 'sequence', 'status', 'payment_mode', 'due_on', 'total_amount_cents', 'paid_amount_cents', 'source_snapshot', 'legacy_source_id'])]
class PaymentSchedule extends Model
{
    /** @use HasFactory<PaymentScheduleFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
        'payment_mode' => 'single',
        'total_amount_cents' => 0,
        'paid_amount_cents' => 0,
    ];

    /** @return BelongsTo<PermitApplication, $this> */
    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_id');
    }

    /** @return HasMany<PaymentScheduleLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PaymentScheduleLine::class);
    }

    /** @return HasMany<TreasuryCollection, $this> */
    public function treasuryCollections(): HasMany
    {
        return $this->hasMany(TreasuryCollection::class);
    }

    /** @return HasOne<XChangePayment, $this> */
    public function xChangePayment(): HasOne
    {
        return $this->hasOne(XChangePayment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentScheduleStatus::class,
            'due_on' => 'date',
            'source_snapshot' => 'array',
        ];
    }
}
