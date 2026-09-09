<?php

namespace App\Models;

use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission as SpatiePermission;

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
class Permission extends SpatiePermission
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    public function label(): string
    {
        return $this->display_name;
    }

    protected static function booted(): void
    {
        static::saving(function (Permission $permission): void {
            $permission->display_name ??= $permission->name !== $permission->code
                ? $permission->name
                : str($permission->code)->replace(['.', '_'], ' ')->title()->toString();
            $permission->name = $permission->code;
            $permission->guard_name = 'web';
        });
    }
}
