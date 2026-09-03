<?php

namespace App\Models;

use Database\Factories\BploRoutingSuggestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $permit_application_id
 * @property int|null $routing_determination_id
 * @property string $profile_version
 * @property list<string> $profile_keys
 * @property string $status
 * @property string $situational_context
 * @property list<array<string, mixed>> $suggested_work
 * @property array<string, mixed> $application_facts_snapshot
 * @property Carbon $lodged_at
 * @property Carbon $review_due_at
 * @property Carbon|null $resolved_at
 * @property-read PermitApplication $permitApplication
 * @property-read BploRoutingDetermination|null $routingDetermination
 */
#[Fillable(['permit_application_id', 'routing_determination_id', 'profile_version', 'profile_keys', 'status', 'situational_context', 'suggested_work', 'application_facts_snapshot', 'lodged_at', 'review_due_at', 'resolved_at'])]
class BploRoutingSuggestion extends Model
{
    public const string AwaitingConfirmation = 'awaiting_confirmation';

    public const string BploConfirmed = 'bplo_confirmed';

    public const string SystemDefaulted = 'system_defaulted';

    public const string Invalidated = 'invalidated';

    /** @use HasFactory<BploRoutingSuggestionFactory> */
    use HasFactory;

    /** @return BelongsTo<PermitApplication, $this> */
    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    /** @return BelongsTo<BploRoutingDetermination, $this> */
    public function routingDetermination(): BelongsTo
    {
        return $this->belongsTo(BploRoutingDetermination::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'profile_keys' => 'array',
            'suggested_work' => 'array',
            'application_facts_snapshot' => 'array',
            'lodged_at' => 'datetime',
            'review_due_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
