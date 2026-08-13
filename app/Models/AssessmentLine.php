<?php

namespace App\Models;

use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use Database\Factories\AssessmentLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assessment_id
 * @property int|null $permit_application_line_id
 * @property int|null $fee_rule_id
 * @property int|null $line_of_business_id
 * @property string $code
 * @property string $name
 * @property FeeRuleCategory $category
 * @property FeeRuleCalculationType $calculation_type
 * @property string $basis
 * @property int $basis_amount_cents
 * @property int $amount_cents
 * @property string|null $legal_basis
 * @property array<string, mixed> $rule_snapshot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['assessment_id', 'permit_application_line_id', 'fee_rule_id', 'line_of_business_id', 'code', 'name', 'category', 'calculation_type', 'basis', 'basis_amount_cents', 'amount_cents', 'legal_basis', 'rule_snapshot'])]
class AssessmentLine extends Model
{
    /** @use HasFactory<AssessmentLineFactory> */
    use HasFactory;

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function permitApplicationLine(): BelongsTo
    {
        return $this->belongsTo(PermitApplicationLine::class);
    }

    public function feeRule(): BelongsTo
    {
        return $this->belongsTo(FeeRule::class);
    }

    public function lineOfBusiness(): BelongsTo
    {
        return $this->belongsTo(LineOfBusiness::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => FeeRuleCategory::class,
            'calculation_type' => FeeRuleCalculationType::class,
            'rule_snapshot' => 'array',
        ];
    }
}
