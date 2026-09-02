<?php

namespace App\Models;

use Database\Factories\PermitApplicationDeclarationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $permit_application_id
 * @property int|null $declared_by_id
 * @property int $schema_version
 * @property string $snapshot_hash
 * @property array<string, mixed> $snapshot
 * @property Carbon $declared_at
 * @property Carbon $created_at
 */
#[Fillable(['permit_application_id', 'declared_by_id', 'schema_version', 'snapshot_hash', 'snapshot', 'declared_at'])]
class PermitApplicationDeclaration extends Model
{
    /** @use HasFactory<PermitApplicationDeclarationFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return BelongsTo<PermitApplication, $this> */
    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    /** @return BelongsTo<User, $this> */
    public function declaredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declared_by_id');
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('A lodged applicant declaration is immutable.'));
        static::deleting(fn (): never => throw new LogicException('A lodged applicant declaration may not be deleted through the application model.'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'declared_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
