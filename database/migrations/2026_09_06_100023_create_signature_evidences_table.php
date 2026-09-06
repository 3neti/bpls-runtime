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
        Schema::create('signature_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signer_id')->constrained('users')->restrictOnDelete();
            $table->morphs('signable');
            $table->string('purpose', 120);
            $table->string('method')->default('captured_facsimile');
            $table->string('evidence_digest', 64)->unique();
            $table->json('source_snapshot');
            $table->timestamp('captured_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signature_evidences');
    }
};
