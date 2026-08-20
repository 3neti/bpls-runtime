<?php

namespace App\Models;

use Database\Factories\OfficeChargeContributionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $permit_application_id
 * @property int|null $submitted_by_id
 * @property string $office_code
 * @property string $office_label
 * @property bool $is_applicable
 * @property string $status
 * @property int|null $amount_cents
 * @property Carbon|null $submitted_at
 * @property string $semantic_classification
 * @property array<string, mixed> $source_snapshot
 * @property-read PermitApplication $permitApplication
 * @property-read User|null $submittedBy
 */
class OfficeChargeContribution extends Model
{
    /** @use HasFactory<OfficeChargeContributionFactory> */
    use HasFactory;

    protected $fillable = [
        'permit_application_id',
        'submitted_by_id',
        'office_code',
        'office_label',
        'is_applicable',
        'status',
        'amount_cents',
        'submitted_at',
        'semantic_classification',
        'source_snapshot',
    ];

    /** @return BelongsTo<PermitApplication, $this> */
    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    protected function casts(): array
    {
        return [
            'is_applicable' => 'boolean',
            'submitted_at' => 'datetime',
            'source_snapshot' => 'array',
        ];
    }
}
