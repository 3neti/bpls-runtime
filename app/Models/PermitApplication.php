<?php

namespace App\Models;

use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use Database\Factories\PermitApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int|null $submitted_by_id
 * @property string|null $application_number
 * @property PermitApplicationType $type
 * @property PermitApplicationStatus $status
 * @property int $application_year
 * @property Carbon|null $submitted_at
 * @property Carbon|null $assessed_at
 * @property string|null $legacy_source_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['business_id', 'submitted_by_id', 'application_number', 'type', 'status', 'application_year', 'submitted_at', 'assessed_at', 'legacy_source_id', 'metadata'])]
class PermitApplication extends Model
{
    /** @use HasFactory<PermitApplicationFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'draft',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PermitApplicationLine::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class);
    }

    public function treasuryCollections(): HasMany
    {
        return $this->hasMany(TreasuryCollection::class);
    }

    public function clearances(): HasMany
    {
        return $this->hasMany(PermitClearance::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PermitApplicationType::class,
            'status' => PermitApplicationStatus::class,
            'submitted_at' => 'datetime',
            'assessed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
