<?php

namespace Database\Seeders;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RevenueCodeFeeCatalogSeeder::class);

        $permissions = collect(UserPermission::cases())
            ->mapWithKeys(fn (UserPermission $permission): array => [
                $permission->value => Permission::firstOrCreate(
                    ['code' => $permission->value],
                    [
                        'name' => str($permission->value)->replace(['.', '_'], ' ')->title()->toString(),
                        'description' => null,
                    ],
                ),
            ]);

        $adminRole = Role::firstOrCreate(
            ['code' => UserRole::Admin->value],
            [
                'name' => 'Admin',
                'description' => 'Local administrative scenario role.',
            ],
        );
        $adminRole->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());

        $user = User::query()->firstOrNew(['email' => 'test@example.com']);

        if (! $user->exists) {
            $user->name = 'Test User';
            $user->password = Hash::make(Str::password(40));
            $user->email_verified_at = now();
        }

        $user->role_id = $adminRole->id;
        $user->save();
    }
}
