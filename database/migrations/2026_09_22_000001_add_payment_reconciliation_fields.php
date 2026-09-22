<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x_change_payments', function (Blueprint $table): void {
            $table->boolean('synthetic_only')->default(false);
            $table->string('reconciliation_state')->default('pending')->index();
            $table->string('reconciliation_source')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('next_check_at')->nullable()->index();
            $table->unsignedInteger('reconciliation_attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('x_change_payments', function (Blueprint $table): void {
            $table->dropColumn(['synthetic_only', 'reconciliation_state', 'reconciliation_source', 'last_checked_at', 'next_check_at', 'reconciliation_attempts']);
        });
    }
};
