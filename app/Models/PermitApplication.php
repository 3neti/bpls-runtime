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
use Illuminate\Support\Collection;

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
 * @property-read Collection<int, Assessment> $assessments
 */
#[Fillable(['business_id', 'submitted_by_id', 'application_number', 'type', 'status', 'application_year', 'submitted_at', 'assessed_at', 'legacy_source_id', 'metadata'])]
class PermitApplication extends Model
{
    /** @use HasFactory<PermitApplicationFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'draft',
    ];

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    /** @return HasMany<PermitApplicationLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PermitApplicationLine::class)->orderBy('id');
    }

    /** @return HasMany<Assessment, $this> */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /** @return HasMany<PaymentSchedule, $this> */
    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class);
    }

    /** @return HasMany<TreasuryCollection, $this> */
    public function treasuryCollections(): HasMany
    {
        return $this->hasMany(TreasuryCollection::class);
    }

    /** @return HasMany<PermitClearance, $this> */
    public function clearances(): HasMany
    {
        return $this->hasMany(PermitClearance::class);
    }

    /** @return HasMany<PermitApplicationDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(PermitApplicationDocument::class);
    }

    public function canContinue(): bool
    {
        return ($this->metadata['terminal_state']['can_continue'] ?? true) !== false;
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
