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
        Schema::create('permit_clearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('completed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code');
            $table->string('label');
            $table->string('status')->default('pending')->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->text('remarks')->nullable();
            $table->json('source_snapshot');
            $table->string('legacy_source_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['permit_application_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permit_clearances');
    }
};
