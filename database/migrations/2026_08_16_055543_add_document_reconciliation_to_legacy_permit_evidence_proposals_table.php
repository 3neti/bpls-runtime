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
        Schema::table('legacy_permit_evidence_proposals', function (Blueprint $table) {
            $table->foreignId('legacy_document_object_reconciliation_id')->nullable()->after('legacy_clearance_type_reconciliation_id');
            $table->foreign('legacy_document_object_reconciliation_id', 'legacy_permit_ev_prop_doc_recon_fk')
                ->references('id')->on('legacy_document_object_reconciliations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legacy_permit_evidence_proposals', function (Blueprint $table) {
            $table->dropForeign('legacy_permit_ev_prop_doc_recon_fk');
            $table->dropColumn('legacy_document_object_reconciliation_id');
        });
    }
};
