<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $business_owner_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property string $access_status
 * @property Carbon|null $access_expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'access_status', 'access_expires_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** @return BelongsTo<BusinessOwner, $this> */
    public function businessOwner(): BelongsTo
    {
        return $this->belongsTo(BusinessOwner::class);
    }

    /** @return HasMany<PermitApplication, $this> */
    public function permitApplications(): HasMany
    {
        return $this->hasMany(PermitApplication::class, 'submitted_by_id');
    }

    /** @return HasMany<UserAccessAudit, $this> */
    public function accessAudits(): HasMany
    {
        return $this->hasMany(UserAccessAudit::class, 'subject_user_id');
    }

    public function hasPermission(UserPermission $permission): bool
    {
        if (! $this->hasActiveAccess()) {
            return false;
        }

        if ($this->hasRole(UserRole::Admin)) {
            return true;
        }

        try {
            return $this->hasPermissionTo($permission->value);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    public function primaryRole(): ?Role
    {
        /** @var Role|null */
        return $this->roles->sortBy('id')->first();
    }

    /** @return list<string> */
    public function roleCodes(): array
    {
        return array_values($this->roles
            ->map(fn ($role): string => (string) $role->getAttribute('code'))
            ->values()
            ->all());
    }

    public function hasActiveAccess(): bool
    {
        return ($this->access_status ?? 'active') === 'active'
            && ($this->access_expires_at === null || $this->access_expires_at->isFuture());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'access_expires_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
