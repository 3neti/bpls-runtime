<?php

namespace App\Models;

use App\Enums\AssessmentDecisionAction;
use Database\Factories\AssessmentDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assessment_id
 * @property int|null $decided_by_id
 * @property AssessmentDecisionAction $action
 * @property Carbon $decided_at
 * @property string|null $reason
 * @property string $assessment_snapshot_hash
 * @property int $total_amount_cents
 * @property array<string, mixed> $source_snapshot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Assessment $assessment
 * @property-read User|null $decidedBy
 */
#[Fillable(['assessment_id', 'decided_by_id', 'action', 'decided_at', 'reason', 'assessment_snapshot_hash', 'total_amount_cents', 'source_snapshot'])]
class AssessmentDecision extends Model
{
    /** @use HasFactory<AssessmentDecisionFactory> */
    use HasFactory;

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'action' => AssessmentDecisionAction::class,
            'decided_at' => 'datetime',
            'source_snapshot' => 'array',
        ];
    }
}
