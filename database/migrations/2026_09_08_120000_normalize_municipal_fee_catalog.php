<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_catalog_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('status')->index();
            $table->date('effective_from')->index();
            $table->date('effective_until')->nullable()->index();
            $table->string('authority_reference')->nullable();
            $table->string('source_sha256', 64);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('business_divisions', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('business_division_line_of_business', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_division_id')->constrained()->cascadeOnDelete();
            $table->foreignId('line_of_business_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['business_division_id', 'line_of_business_id'], 'business_division_lob_unique');
        });

        Schema::create('fee_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('fee_rule_category')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('revenue_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('fee_rules', function (Blueprint $table): void {
            $table->foreignId('fee_catalog_version_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('business_division_id')->nullable()->after('line_of_business_id')->constrained()->nullOnDelete();
            $table->foreignId('fee_category_id')->nullable()->after('category')->constrained()->nullOnDelete();
            $table->foreignId('revenue_account_id')->nullable()->after('fee_category_id')->constrained()->nullOnDelete();
            $table->string('determination_channel')->default('automatic_assessment')->after('scope')->index();
        });
    }

    public function down(): void
    {
        Schema::table('fee_rules', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('fee_catalog_version_id');
            $table->dropConstrainedForeignId('business_division_id');
            $table->dropConstrainedForeignId('fee_category_id');
            $table->dropConstrainedForeignId('revenue_account_id');
            $table->dropColumn('determination_channel');
        });

        Schema::dropIfExists('revenue_accounts');
        Schema::dropIfExists('fee_categories');
        Schema::dropIfExists('business_division_line_of_business');
        Schema::dropIfExists('business_divisions');
        Schema::dropIfExists('fee_catalog_versions');
    }
};
