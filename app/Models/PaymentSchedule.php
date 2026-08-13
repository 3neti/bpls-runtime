<?php

namespace App\Models;

use App\Enums\PaymentScheduleStatus;
use Database\Factories\PaymentScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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

    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PaymentScheduleLine::class);
    }

    public function treasuryCollections(): HasMany
    {
        return $this->hasMany(TreasuryCollection::class);
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
