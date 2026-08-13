<?php

namespace App\Models;

use App\Enums\ReceiptStatus;
use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $treasury_collection_id
 * @property int $payment_schedule_id
 * @property int $permit_application_id
 * @property int $assessment_id
 * @property int|null $issued_by_id
 * @property ReceiptStatus $status
 * @property string $numbering_authority
 * @property string $receipt_number
 * @property int $amount_cents
 * @property Carbon $issued_at
 * @property string|null $remarks
 * @property array<string, mixed> $source_snapshot
 * @property string|null $legacy_source_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['treasury_collection_id', 'payment_schedule_id', 'permit_application_id', 'assessment_id', 'issued_by_id', 'status', 'numbering_authority', 'receipt_number', 'amount_cents', 'issued_at', 'remarks', 'source_snapshot', 'legacy_source_id'])]
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'issued',
        'numbering_authority' => 'manual',
    ];

    public function treasuryCollection(): BelongsTo
    {
        return $this->belongsTo(TreasuryCollection::class);
    }

    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class);
    }

    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReceiptStatus::class,
            'issued_at' => 'datetime',
            'source_snapshot' => 'array',
        ];
    }
}
