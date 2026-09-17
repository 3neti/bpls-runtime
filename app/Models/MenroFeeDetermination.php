<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $permit_application_id
 * @property int $actor_id
 * @property string $office_code
 * @property string $scope
 * @property int $fee_rule_id
 * @property string $code
 * @property string $basis
 * @property int $application_area_square_meters
 * @property int $calculation_basis_centi_square_meters
 * @property int $operative_range_min_centi_square_meters
 * @property int $operative_range_max_centi_square_meters
 * @property int $amount_minor
 * @property string $schedule_version
 * @property string $source_evidence
 * @property string $classification
 * @property bool $production_authority
 * @property string $reason
 * @property Carbon $determined_at
 * @property string $fingerprint
 */
#[Fillable([
    'permit_application_id', 'actor_id', 'office_code', 'scope', 'fee_rule_id', 'code', 'basis',
    'application_area_square_meters', 'calculation_basis_centi_square_meters',
    'operative_range_min_centi_square_meters', 'operative_range_max_centi_square_meters',
    'amount_minor', 'schedule_version', 'source_evidence', 'classification',
    'production_authority', 'reason', 'determined_at', 'fingerprint',
])]
class MenroFeeDetermination extends Model
{
    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function feeRule(): BelongsTo
    {
        return $this->belongsTo(FeeRule::class);
    }

    protected function casts(): array
    {
        return [
            'application_area_square_meters' => 'integer',
            'calculation_basis_centi_square_meters' => 'integer',
            'operative_range_min_centi_square_meters' => 'integer',
            'operative_range_max_centi_square_meters' => 'integer',
            'amount_minor' => 'integer',
            'production_authority' => 'boolean',
            'determined_at' => 'immutable_datetime',
        ];
    }
}
