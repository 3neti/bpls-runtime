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
        Schema::create('permit_application_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_application_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('declared_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->char('snapshot_hash', 64)->index();
            $table->json('snapshot');
            $table->timestamp('declared_at')->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permit_application_declarations');
    }
};
