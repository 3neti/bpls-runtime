<?php

namespace App\Models;

use Database\Factories\UserAccessAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAccessAudit extends Model
{
    /** @use HasFactory<UserAccessAuditFactory> */
    use HasFactory;

    protected $fillable = ['actor_user_id', 'subject_user_id', 'action', 'reason', 'before_state', 'after_state'];

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    protected function casts(): array
    {
        return ['before_state' => 'array', 'after_state' => 'array'];
    }
}
