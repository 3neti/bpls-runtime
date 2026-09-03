<?php

namespace App\Models;

use App\Actions\PermitApplicationStatusMutation;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Models\Builders\PermitApplicationBuilder;
use Database\Factories\PermitApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $business_id
 * @property int|null $submitted_by_id
 * @property string|null $application_number
 * @property string|null $tracking_reference
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
 * @property-read Collection<int, PermitApplicationLine> $lines
 * @property-read Collection<int, PaymentSchedule> $paymentSchedules
 * @property-read Collection<int, TreasuryCollection> $treasuryCollections
 * @property-read Collection<int, OfficeChargeContribution> $officeChargeContributions
 * @property-read BusinessPermitEvaluation|null $businessPermitEvaluation
 * @property-read BploRoutingDetermination|null $bploRoutingDetermination
 * @property-read BploRoutingSuggestion|null $bploRoutingSuggestion
 * @property-read Collection<int, PaperlessPaymentOrder> $paperlessPaymentOrders
 * @property-read PermitApplicationDeclaration|null $declaration
 * @property-read ProvisionalUatPermitCompletion|null $provisionalUatPermitCompletion
 */
#[Fillable(['business_id', 'submitted_by_id', 'application_number', 'tracking_reference', 'type', 'status', 'application_year', 'submitted_at', 'assessed_at', 'legacy_source_id', 'metadata'])]
class PermitApplication extends Model
{
    /** @use HasFactory<PermitApplicationFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'draft',
    ];

    public function setAttribute($key, $value)
    {
        if ($key === 'status' && $this->requiresStatusMutationPrivilege($value)) {
            PermitApplicationStatusMutation::assertPrivileged();
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    public function newEloquentBuilder($query): PermitApplicationBuilder
    {
        return new PermitApplicationBuilder($query);
    }

    protected function performUpdate(Builder $query)
    {
        if ($this->isDirty('status')) {
            PermitApplicationStatusMutation::assertPrivileged();
        }

        return parent::performUpdate($query);
    }

    protected function performInsert(Builder $query)
    {
        if ($this->getAttribute('status') !== PermitApplicationStatus::Draft) {
            PermitApplicationStatusMutation::assertPrivileged();
        }

        return parent::performInsert($query);
    }

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

    /** @return HasMany<OfficeChargeContribution, $this> */
    public function officeChargeContributions(): HasMany
    {
        return $this->hasMany(OfficeChargeContribution::class);
    }

    /** @return HasOne<BusinessPermitEvaluation, $this> */
    public function businessPermitEvaluation(): HasOne
    {
        return $this->hasOne(BusinessPermitEvaluation::class);
    }

    /** @return HasOne<BploRoutingDetermination, $this> */
    public function bploRoutingDetermination(): HasOne
    {
        return $this->hasOne(BploRoutingDetermination::class);
    }

    /** @return HasOne<BploRoutingSuggestion, $this> */
    public function bploRoutingSuggestion(): HasOne
    {
        return $this->hasOne(BploRoutingSuggestion::class);
    }

    /** @return HasMany<PaperlessPaymentOrder, $this> */
    public function paperlessPaymentOrders(): HasMany
    {
        return $this->hasMany(PaperlessPaymentOrder::class)->orderBy('id');
    }

    /** @return HasOne<PermitApplicationDeclaration, $this> */
    public function declaration(): HasOne
    {
        return $this->hasOne(PermitApplicationDeclaration::class);
    }

    /** @return HasOne<ProvisionalUatPermitCompletion, $this> */
    public function provisionalUatPermitCompletion(): HasOne
    {
        return $this->hasOne(ProvisionalUatPermitCompletion::class);
    }

    /**
     * @param  Builder<PermitApplication>  $query
     * @return Builder<PermitApplication>
     */
    public function scopeVisibleToPortalOwner(Builder $query, User $citizen): Builder
    {
        return $query->whereHas(
            'business',
            fn (Builder $businessQuery): Builder => $businessQuery
                ->where('business_owner_id', $citizen->business_owner_id ?? 0),
        );
    }

    public function canContinue(): bool
    {
        return ($this->metadata['terminal_state']['can_continue'] ?? true) !== false;
    }

    public function isHistoricalEvidenceOnly(): bool
    {
        return $this->status === PermitApplicationStatus::HistoricalEvidence
            && data_get($this->metadata, 'historical_semantics.operationally_eligible') === false;
    }

    private function requiresStatusMutationPrivilege(mixed $value): bool
    {
        $status = $value instanceof PermitApplicationStatus ? $value : PermitApplicationStatus::tryFrom((string) $value);

        if ($status === null) {
            return true;
        }

        if (! $this->exists) {
            return $status !== PermitApplicationStatus::Draft;
        }

        return $this->getRawOriginal('status') !== $status->value;
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
