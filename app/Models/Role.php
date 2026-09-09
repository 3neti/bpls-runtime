<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int $id
 * @property string $name Canonical machine name.
 * @property string $code
 * @property string $display_name
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'code', 'display_name', 'description', 'guard_name'])]
class Role extends SpatieRole
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    public function label(): string
    {
        return $this->display_name;
    }

    protected static function booted(): void
    {
        static::saving(function (Role $role): void {
            $role->display_name ??= $role->name !== $role->code
                ? $role->name
                : str($role->code)->replace('_', ' ')->title()->toString();
            $role->name = $role->code;
            $role->guard_name = 'web';
        });
    }
}
