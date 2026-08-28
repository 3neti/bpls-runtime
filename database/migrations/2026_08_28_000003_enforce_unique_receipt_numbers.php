<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropUnique(['numbering_authority', 'receipt_number']);
            $table->unique('receipt_number');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropUnique(['receipt_number']);
            $table->unique(['numbering_authority', 'receipt_number']);
        });
    }
};
