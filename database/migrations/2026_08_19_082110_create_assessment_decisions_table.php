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
        Schema::create('assessment_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('decided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->timestamp('decided_at')->index();
            $table->text('reason')->nullable();
            $table->char('assessment_snapshot_hash', 64)->index();
            $table->unsignedBigInteger('total_amount_cents');
            $table->json('source_snapshot');
            $table->timestamps();

            $table->index(['action', 'decided_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_decisions');
    }
};
