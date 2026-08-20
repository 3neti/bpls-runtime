<?php

namespace App\Models;

use Database\Factories\ProvisionalUatPermitCompletionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $permit_application_id
 * @property int|null $decided_by_id
 * @property int|null $released_by_id
 * @property string $status
 * @property string|null $decision
 * @property string|null $reason
 * @property string|null $permit_number
 * @property string|null $synthetic_signature_reference
 * @property Carbon|null $decided_at
 * @property Carbon|null $released_at
 * @property string $semantic_classification
 * @property array<string, mixed> $source_snapshot
 * @property-read PermitApplication $permitApplication
 * @property-read User|null $decidedBy
 * @property-read User|null $releasedBy
 */
class ProvisionalUatPermitCompletion extends Model
{
    /** @use HasFactory<ProvisionalUatPermitCompletionFactory> */
    use HasFactory;

    protected $fillable = [
        'permit_application_id',
        'decided_by_id',
        'released_by_id',
        'status',
        'decision',
        'reason',
        'permit_number',
        'synthetic_signature_reference',
        'decided_at',
        'released_at',
        'semantic_classification',
        'source_snapshot',
    ];

    /** @return BelongsTo<PermitApplication, $this> */
    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_id');
    }

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'released_at' => 'datetime',
            'source_snapshot' => 'array',
        ];
    }
}
