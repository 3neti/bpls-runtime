<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->string('guard_name')->default('web')->after('description');
        });
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->string('guard_name')->default('web')->after('description');
        });

        DB::table('roles')->orderBy('id')->each(function (object $role): void {
            DB::table('roles')->where('id', $role->id)->update([
                'display_name' => $role->name,
                'name' => $role->code,
                'guard_name' => 'web',
            ]);
        });
        DB::table('permissions')->orderBy('id')->each(function (object $permission): void {
            DB::table('permissions')->where('id', $permission->id)->update([
                'display_name' => $permission->name,
                'name' => $permission->code,
                'guard_name' => 'web',
            ]);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->unique(['name', 'guard_name']);
        });
        Schema::table('permissions', function (Blueprint $table) {
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });
        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->primary(['role_id', 'model_id', 'model_type']);
        });
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        DB::table('permission_role')->orderBy('id')->each(function (object $assignment): void {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $assignment->permission_id,
                'role_id' => $assignment->role_id,
            ]);
        });
        DB::table('users')->whereNotNull('role_id')->orderBy('id')->each(function (object $user): void {
            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $user->role_id,
                'model_type' => User::class,
                'model_id' => $user->id,
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('access_status')->default('active')->after('password');
            $table->timestamp('access_expires_at')->nullable()->after('access_status');
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('permission_role');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['permission_id', 'role_id']);
        });
        DB::table('role_has_permissions')->get()->each(function (object $assignment): void {
            DB::table('permission_role')->insert([
                'permission_id' => $assignment->permission_id,
                'role_id' => $assignment->role_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
        DB::table('model_has_roles')->orderBy('model_id')->get()->groupBy('model_id')->each(function ($assignments, int|string $userId): void {
            DB::table('users')->where('id', $userId)->update(['role_id' => $assignments->first()->role_id]);
        });

        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');

        DB::table('roles')->orderBy('id')->each(function (object $role): void {
            DB::table('roles')->where('id', $role->id)->update(['name' => $role->display_name]);
        });
        DB::table('permissions')->orderBy('id')->each(function (object $permission): void {
            DB::table('permissions')->where('id', $permission->id)->update(['name' => $permission->display_name]);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['name', 'guard_name']);
            $table->dropColumn(['display_name', 'guard_name']);
        });
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropUnique(['name', 'guard_name']);
            $table->dropColumn(['display_name', 'guard_name']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['access_status', 'access_expires_at']);
        });
    }
};
