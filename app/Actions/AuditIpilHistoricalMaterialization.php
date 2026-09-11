<?php

namespace App\Actions;

use App\Support\IpilRescue\Gate6ExecutionAuthorization;
use App\Support\IpilRescue\IpilSeedMappingProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class AuditIpilHistoricalMaterialization
{
    /** @return array<string, mixed> */
    public function handle(): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('The Gate 6 audit requires the local PostgreSQL materialization target.');
        }

        $started = microtime(true);
        $checks = [];
        $this->equal($checks, 'source_identities', DB::table('ipil_rescue_source_identities')->count(), 324873);
        $this->equal($checks, 'evidence_records_without_auth_payloads', DB::table('ipil_historical_evidence_records')->count(), 246230);
        $this->equal($checks, 'owners', DB::table('ipil_historical_owners')->count(), 3194);
        $this->equal($checks, 'businesses', DB::table('ipil_historical_businesses')->count(), 3212);
        $this->equal($checks, 'applications', DB::table('ipil_historical_applications')->count(), 3137);
        foreach (['Renewal' => 2621, 'New' => 461, 'Additional' => 55] as $type => $expected) {
            $this->equal($checks, 'application_type_'.$type, DB::table('ipil_historical_applications')->where('source_type', $type)->count(), $expected);
        }
        foreach (['Released' => 2907, 'Assessment' => 199, 'Pending Payment' => 28, 'Draft' => 3] as $status => $expected) {
            $this->equal($checks, 'application_status_'.str_replace(' ', '_', $status), DB::table('ipil_historical_applications')->where('source_status', $status)->count(), $expected);
        }
        $this->equal($checks, 'classifications', DB::table('ipil_historical_classifications')->count(), 4236);
        $this->equal($checks, 'measurements', DB::table('ipil_historical_measurements')->count(), 4388);
        $this->equal($checks, 'automatic_psgc_assignments', DB::table('ipil_historical_businesses')->whereNotNull('barangay_psgc_proposal')->where('operationally_eligible', true)->count(), 0);
        $this->equal($checks, 'schedules', DB::table('ipil_historical_payment_schedules')->count(), 7648);
        foreach (['paid' => 5741, 'pending' => 1904, 'partial' => 3] as $status => $expected) {
            $this->equal($checks, 'schedule_status_'.$status, DB::table('ipil_historical_payment_schedules')->where('source_status', $status)->count(), $expected);
        }
        $this->equal($checks, 'non_cent_application_totals', DB::table('ipil_historical_applications')->where('total_fees_cent_exact', false)->count(), 348);
        $this->equal($checks, 'non_cent_schedule_totals', DB::table('ipil_historical_payment_schedules')->where('total_amount_cent_exact', false)->count(), 24);
        $this->equal($checks, 'schedule_missing_application', DB::table('ipil_historical_payment_schedules')->where('missing_application', true)->count(), 69);
        $this->equal($checks, 'payments', DB::table('ipil_historical_payments')->count(), 5874);
        foreach (['completed' => 5744, 'failed' => 129, 'pending' => 1] as $status => $expected) {
            $this->equal($checks, 'payment_status_'.$status, DB::table('ipil_historical_payments')->where('source_status', $status)->count(), $expected);
        }
        $this->equal($checks, 'payment_missing_application', DB::table('ipil_historical_payments')->where('missing_application', true)->count(), 3);
        $this->equal($checks, 'payment_missing_schedule', DB::table('ipil_historical_payments')->where('missing_schedule', true)->count(), 56);
        $paidAnchor = (string) DB::scalar("select coalesce(sum(cast(amount_decimal as numeric)), 0)::numeric(30,2)::text from ipil_historical_payments where source_status = 'completed'");
        $schedulePaidAnchor = (string) DB::scalar('select coalesce(sum(cast(paid_amount_decimal as numeric)), 0)::numeric(30,2)::text from ipil_historical_payment_schedules');
        $this->same($checks, 'completed_payment_anchor', $paidAnchor, '93295317.20');
        $this->same($checks, 'schedule_paid_anchor', $schedulePaidAnchor, '93295317.20');
        $this->equal($checks, 'receipt_claims', DB::table('ipil_historical_receipt_claims')->count(), 5873);
        $duplicateGroups = DB::query()->fromSub(DB::table('ipil_historical_receipt_claims')->select('normalized_sha256')->groupBy('normalized_sha256')->havingRaw('count(*) > 1'), 'duplicates')->count();
        $duplicateEvents = DB::table('ipil_historical_receipt_claims')->where('is_duplicate_claim', true)->count();
        $this->equal($checks, 'duplicate_receipt_groups', $duplicateGroups, 196);
        $this->equal($checks, 'events_in_duplicate_receipt_groups', $duplicateEvents, 744);
        $this->equal($checks, 'permit_claims', DB::table('ipil_historical_permit_claims')->count(), 2766);
        $this->equal($checks, 'permit_missing_application', DB::table('ipil_historical_permit_claims')->where('missing_application', true)->count(), 15);
        $this->equal($checks, 'permit_broken_business', DB::table('ipil_historical_permit_claims')->where('broken_business_edge', true)->count(), 10);
        $this->equal($checks, 'permit_broken_owner', DB::table('ipil_historical_permit_claims')->where('broken_owner_edge', true)->count(), 10);
        $this->equal($checks, 'clearance_claims', DB::table('ipil_historical_clearance_claims')->count(), 14615);
        $this->equal($checks, 'clearance_broken_type', DB::table('ipil_historical_clearance_claims')->where('broken_type_reference', true)->count(), 110);
        $this->equal($checks, 'media_source_objects', DB::table('ipil_historical_media_evidence')->count(), 35);
        $this->equal($checks, 'media_unresolved', DB::table('ipil_historical_media_evidence')->where('association_state', 'UNRESOLVED')->count(), 19);
        $this->equal($checks, 'media_accepted_imported', DB::table('ipil_historical_media_evidence')->where('spatie_imported', true)->count(), 16);
        $this->equal($checks, 'media_unresolved_imported', DB::table('ipil_historical_media_evidence')->where('association_state', 'UNRESOLVED')->where('spatie_imported', true)->count(), 0);
        $this->equal($checks, 'media_checksum_mismatches', DB::table('ipil_historical_media_evidence')->where('spatie_imported', true)->whereColumn('source_sha256', '!=', 'managed_copy_sha256')->count(), 0);
        $this->equal($checks, 'pricing_identities', DB::table('ipil_rescue_source_identities')->whereIn('dataset', ['pricing-corpus', 'pricing-knowledge'])->count(), 5);
        $this->equal($checks, 'historical_actionable', DB::table('ipil_historical_applications')->where(fn ($query) => $query->where('operationally_eligible', true)->orWhere('can_continue', true))->count(), 0);
        $runs = DB::table('ipil_rescue_import_runs')->where('status', 'COMPLETED')->orderBy('started_at')->get();
        $firstRunResult = json_decode((string) $runs->first()?->result, true, flags: JSON_THROW_ON_ERROR);
        $baseline = $firstRunResult['operational_baseline'] ?? null;
        if (! is_array($baseline)) {
            throw new RuntimeException('The first completed import run lacks its operational baseline.');
        }
        $this->operationalIsolation($checks, $baseline);
        $this->same($checks, 'run_binding_corpus', (string) $runs->last()?->corpus_fingerprint_sha256, IpilSeedMappingProfile::CanonicalCorpusFingerprint);
        $this->same($checks, 'run_binding_profile', (string) $runs->last()?->mapping_profile_identity_sha256, IpilSeedMappingProfile::Identity);
        $this->same($checks, 'run_binding_plan', (string) $runs->last()?->seed_plan_fingerprint_sha256, Gate6ExecutionAuthorization::PlanFingerprint);
        $this->same($checks, 'run_binding_manifest', (string) $runs->last()?->execution_manifest_fingerprint_sha256, Gate6ExecutionAuthorization::ManifestFingerprint);
        $secondRunCreated = null;
        if ($runs->count() >= 2) {
            $result = json_decode((string) $runs->last()->result, true, flags: JSON_THROW_ON_ERROR);
            $secondRunCreated = array_sum($result['created'] ?? []);
            $this->equal($checks, 'second_run_source_derived_creations', $secondRunCreated, 0);
        }

        $failed = array_values(array_filter($checks, fn (array $check): bool => ! $check['passed']));

        return [
            'schema_version' => 'bpls.ipil-historical-materialization-audit.v1',
            'passed' => $failed === [],
            'corpus' => ['id' => IpilSeedMappingProfile::CanonicalCorpusId, 'fingerprint_sha256' => IpilSeedMappingProfile::CanonicalCorpusFingerprint],
            'mapping_profile' => ['name' => IpilSeedMappingProfile::Name, 'identity_sha256' => IpilSeedMappingProfile::Identity],
            'seed_plan' => ['id' => Gate6ExecutionAuthorization::PlanId, 'fingerprint_sha256' => Gate6ExecutionAuthorization::PlanFingerprint],
            'execution_manifest_fingerprint_sha256' => Gate6ExecutionAuthorization::ManifestFingerprint,
            'completed_import_runs' => $runs->count(),
            'latest_import_run_id' => $runs->last()?->id,
            'second_run_created' => $secondRunCreated,
            'anchors' => ['completed_payments_php' => $paidAnchor, 'schedule_paid_php' => $schedulePaidAnchor],
            'checks' => $checks,
            'failed_checks' => $failed,
            'duration_seconds' => round(microtime(true) - $started, 3),
            'read_only' => true,
            'live_source_access' => false,
            'current_price_invoked' => false,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $checks
     * @param  array<string, int>  $baseline
     */
    private function operationalIsolation(array &$checks, array $baseline): void
    {
        $tables = [
            'users' => 'historical_users_created',
            'permit_applications' => 'operational_applications_created',
            'payment_schedules' => 'current_liabilities_created',
            'treasury_collections' => 'current_collections_created',
            'receipts' => 'current_receipts_created',
            'permits' => 'current_permits_created',
            'signature_evidences' => 'historical_signatures_created',
            'bplo_routing_works' => 'current_work_created',
            'paperless_payment_orders' => 'current_payment_orders_created',
            'post_payment_office_certifications' => 'current_certifications_created',
        ];
        foreach ($tables as $table => $key) {
            if (Schema::hasTable($table)) {
                $before = $baseline[$table] ?? null;
                if (! is_int($before)) {
                    throw new RuntimeException("The operational baseline is missing {$table}.");
                }
                $this->equal($checks, $key, DB::table($table)->count() - $before, 0);
            }
        }
        $this->equal($checks, 'historical_media_on_operational_applications', DB::table('media')->where('model_type', 'App\\Models\\PermitApplication')->count(), 0);
        $this->equal($checks, 'historical_lodging_manifests_fabricated', Schema::hasTable('permit_application_document_lodgings') ? DB::table('permit_application_document_lodgings')->count() : 0, 0);
    }

    /** @param array<string, array<string, mixed>> $checks */
    private function equal(array &$checks, string $key, int $actual, int $expected): void
    {
        $checks[$key] = ['passed' => $actual === $expected, 'expected' => $expected, 'actual' => $actual];
    }

    /** @param array<string, array<string, mixed>> $checks */
    private function same(array &$checks, string $key, string $actual, string $expected): void
    {
        $checks[$key] = ['passed' => hash_equals($expected, $actual), 'expected' => $expected, 'actual' => $actual];
    }
}
