<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menro_fee_determinations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('permit_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('office_code');
            $table->string('scope');
            $table->foreignId('fee_rule_id')->constrained('fee_rules');
            $table->string('code');
            $table->string('basis');
            $table->unsignedInteger('application_area_square_meters');
            $table->unsignedInteger('calculation_basis_centi_square_meters');
            $table->unsignedInteger('operative_range_min_centi_square_meters');
            $table->unsignedInteger('operative_range_max_centi_square_meters');
            $table->unsignedBigInteger('amount_minor');
            $table->string('schedule_version');
            $table->string('source_evidence');
            $table->string('classification');
            $table->boolean('production_authority')->default(false);
            $table->text('reason');
            $table->timestamp('determined_at');
            $table->string('fingerprint', 64);
            $table->timestamps();
            $table->unique(['permit_application_id', 'office_code']);
            $table->unique('fingerprint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menro_fee_determinations');
    }
};
