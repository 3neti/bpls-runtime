<?php

namespace App\Models;

use App\Enums\RevenueCodeProvisionRowStatus;
use Database\Factories\RevenueCodeProvisionRowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $revenue_code_provision_id
 * @property int $sequence
 * @property string $code
 * @property string $source_basis_text
 * @property string $source_value_text
 * @property int|null $basis_from_cents
 * @property int|null $basis_below_cents
 * @property int|null $amount_cents
 * @property string|null $rate_basis_points
 * @property bool $is_ceiling
 * @property RevenueCodeProvisionRowStatus $normalization_status
 * @property string|null $normalization_notes
 * @property array<string, mixed>|null $metadata
 * @property-read RevenueCodeProvision $provision
 */
#[Fillable(['revenue_code_provision_id', 'sequence', 'code', 'source_basis_text', 'source_value_text', 'basis_from_cents', 'basis_below_cents', 'amount_cents', 'rate_basis_points', 'is_ceiling', 'normalization_status', 'normalization_notes', 'metadata'])]
class RevenueCodeProvisionRow extends Model
{
    /** @use HasFactory<RevenueCodeProvisionRowFactory> */
    use HasFactory;

    protected $attributes = [
        'is_ceiling' => false,
    ];

    /** @return BelongsTo<RevenueCodeProvision, $this> */
    public function provision(): BelongsTo
    {
        return $this->belongsTo(RevenueCodeProvision::class, 'revenue_code_provision_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rate_basis_points' => 'decimal:4',
            'is_ceiling' => 'boolean',
            'normalization_status' => RevenueCodeProvisionRowStatus::class,
            'metadata' => 'array',
        ];
    }
}
