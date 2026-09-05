<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('provisional_uat_permit_completions', function (Blueprint $table) {
            $table->foreignId('issued_by_id')->nullable()->after('decided_by_id')->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable()->after('decided_at')->index();
            $table->date('valid_until')->nullable()->after('issued_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provisional_uat_permit_completions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issued_by_id');
            $table->dropColumn(['issued_at', 'valid_until']);
        });
    }
};
