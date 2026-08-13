<?php

namespace App\Models;

use Database\Factories\PermitApplicationLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $permit_application_id
 * @property int|null $line_of_business_id
 * @property int $declared_gross_sales_cents
 * @property int $capital_investment_cents
 * @property int $quantity
 * @property Carbon|null $started_on
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['permit_application_id', 'line_of_business_id', 'declared_gross_sales_cents', 'capital_investment_cents', 'quantity', 'started_on', 'metadata'])]
class PermitApplicationLine extends Model
{
    /** @use HasFactory<PermitApplicationLineFactory> */
    use HasFactory;

    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    public function lineOfBusiness(): BelongsTo
    {
        return $this->belongsTo(LineOfBusiness::class);
    }

    public function assessmentLines(): HasMany
    {
        return $this->hasMany(AssessmentLine::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'metadata' => 'array',
        ];
    }
}
