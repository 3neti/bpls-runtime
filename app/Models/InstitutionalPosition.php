<?php

namespace App\Models;

use Database\Factories\InstitutionalPositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['code', 'name', 'capability_role_id', 'authority_classification', 'assignment_status', 'metadata'])]
class InstitutionalPosition extends Model
{
    /** @use HasFactory<InstitutionalPositionFactory> */
    use HasFactory;

    /** @return BelongsTo<Role, $this> */
    public function capabilityRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'capability_role_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
