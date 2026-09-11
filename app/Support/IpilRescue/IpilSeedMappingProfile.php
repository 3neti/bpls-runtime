<?php

namespace App\Support\IpilRescue;

use InvalidArgumentException;

final readonly class IpilSeedMappingProfile
{
    public const Name = 'ipil-rescue-mapping-v1.0.0';

    public const Contract = 'bpls.ipil-source-to-bpls-mapping.v1';

    public const Identity = 'edae710f7e29dcecb148d24e349eb4e0e3d6747704231a298d1d63bb97ab789c';

    public const CanonicalCorpusId = 'ipil-20260910t153224z-2ab19c17';

    public const CanonicalCorpusFingerprint = 'd799a0c4da562094f3433f7ebe5b6f5175b4640e5738c518d4c2c51dc731fa81';

    /** @param array<string, array{count: int, disposition: string, confidence: string}> $tables */
    public function __construct(
        public string $name,
        public string $contract,
        public string $identity,
        public string $corpusId,
        public string $corpusFingerprint,
        public int $databaseRows,
        public int $mediaMetadataRecords,
        public int $mediaSourceIdentities,
        public int $pricingRecords,
        public array $tables,
        /** @var array<string, mixed> */
        public array $acceptedEvidence = [],
    ) {}

    public static function canonical(): self
    {
        $rows = [
            'activity_logs' => [127363, 'PRESERVE_UNINTERPRETED', 'PROBABLE'], 'assetSizes' => [3, 'MAP_AS_HISTORICAL_EVIDENCE', 'PROBABLE'],
            'authAccounts' => [33, 'IGNORE_WITH_EVIDENCE_BASED_REASON', 'ESTABLISHED'], 'authRateLimits' => [2, 'IGNORE_WITH_EVIDENCE_BASED_REASON', 'ESTABLISHED'],
            'authRefreshTokens' => [76352, 'IGNORE_WITH_EVIDENCE_BASED_REASON', 'ESTABLISHED'], 'authSessions' => [2256, 'IGNORE_WITH_EVIDENCE_BASED_REASON', 'ESTABLISHED'],
            'authVerificationCodes' => [0, 'IGNORE_WITH_EVIDENCE_BASED_REASON', 'ESTABLISHED'], 'authVerifiers' => [0, 'IGNORE_WITH_EVIDENCE_BASED_REASON', 'ESTABLISHED'],
            'barangays' => [44, 'REFERENCE_DATA', 'AMBIGUOUS'], 'billing_group_fee_fields' => [5, 'PRESERVE_UNINTERPRETED', 'PROBABLE'],
            'billing_group_fees' => [225, 'MAP_AS_HISTORICAL_EVIDENCE', 'PROBABLE'], 'billing_group_fields' => [12, 'PRESERVE_UNINTERPRETED', 'PROBABLE'],
            'billing_group_line_items' => [45413, 'MAP_AS_HISTORICAL_EVIDENCE', 'PROBABLE'], 'billing_group_print_layouts' => [4, 'DEFER', 'PROBABLE'],
            'billing_group_records' => [25372, 'MAP_AS_HISTORICAL_EVIDENCE', 'PROBABLE'], 'billing_groups' => [5, 'REFERENCE_DATA', 'PROBABLE'],
            'business_categories' => [14, 'REFERENCE_DATA', 'AMBIGUOUS'], 'business_nature_categories' => [32, 'REFERENCE_DATA', 'AMBIGUOUS'],
            'business_owners' => [3194, 'MAP_AS_HISTORICAL_EVIDENCE', 'PROBABLE'], 'business_permit_applications' => [3137, 'MAP_AS_HISTORICAL_EVIDENCE', 'ESTABLISHED'],
            'business_subcategories' => [58, 'REFERENCE_DATA', 'AMBIGUOUS'], 'businesses' => [3212, 'MAP_AS_HISTORICAL_EVIDENCE', 'PROBABLE'],
            'cities' => [38, 'REFERENCE_DATA', 'AMBIGUOUS'], 'clearance_types' => [5, 'REFERENCE_DATA', 'PROBABLE'], 'counters' => [2, 'PRESERVE_UNINTERPRETED', 'AMBIGUOUS'],
            'departments' => [1, 'REFERENCE_DATA', 'PROBABLE'], 'division_groups' => [832, 'REFERENCE_DATA', 'PROBABLE'], 'divisions' => [23, 'REFERENCE_DATA', 'PROBABLE'],
            'fee_names' => [28, 'REFERENCE_DATA', 'PROBABLE'], 'fee_overrides' => [9, 'MAP_AS_HISTORICAL_EVIDENCE', 'AMBIGUOUS'], 'fees' => [294, 'REFERENCE_DATA', 'AMBIGUOUS'],
            'groups' => [833, 'REFERENCE_DATA', 'PROBABLE'], 'majors' => [8, 'REFERENCE_DATA', 'PROBABLE'], 'manual_transactions' => [0, 'IGNORE_WITH_EVIDENCE_BASED_REASON', 'ESTABLISHED'],
            'mayorsPermitCategories' => [4, 'MAP_AS_HISTORICAL_EVIDENCE', 'PROBABLE'], 'payment_schedule_config' => [0, 'PRESERVE_UNINTERPRETED', 'ESTABLISHED'],
            'payment_schedules' => [7648, 'MAP_AS_HISTORICAL_EVIDENCE', 'ESTABLISHED'], 'payments' => [5874, 'MAP_AS_HISTORICAL_EVIDENCE', 'ESTABLISHED'],
            'permissions' => [141, 'DEFER', 'ESTABLISHED'], 'permit_clearances' => [14615, 'MAP_AS_HISTORICAL_EVIDENCE', 'PROBABLE'],
            'permit_layouts' => [1, 'DEFER', 'ESTABLISHED'], 'permits' => [2766, 'MAP_AS_HISTORICAL_EVIDENCE', 'AMBIGUOUS'],
            'platform_settings' => [1, 'PRESERVE_UNINTERPRETED', 'ESTABLISHED'], 'provinces' => [13, 'REFERENCE_DATA', 'AMBIGUOUS'],
            'receipt_layouts' => [3, 'DEFER', 'ESTABLISHED'], 'report_export_chunks' => [0, 'IGNORE_WITH_EVIDENCE_BASED_REASON', 'ESTABLISHED'],
            'report_exports' => [11, 'PRESERVE_UNINTERPRETED', 'ESTABLISHED'], 'role_permissions' => [517, 'DEFER', 'ESTABLISHED'], 'roles' => [10, 'DEFER', 'ESTABLISHED'],
            'saved_reports' => [10, 'PRESERVE_UNINTERPRETED', 'PROBABLE'], 'surcharge_penalty_config' => [1, 'DEFER', 'AMBIGUOUS'],
            'unitsOfMeasurement' => [4381, 'MAP_AS_HISTORICAL_EVIDENCE', 'PROBABLE'], 'users' => [28, 'PRESERVE_UNINTERPRETED', 'ESTABLISHED'],
        ];
        $tables = [];

        foreach ($rows as $table => [$count, $disposition, $confidence]) {
            $tables[$table] = compact('count', 'disposition', 'confidence');
        }

        return new self(self::Name, self::Contract, self::Identity, self::CanonicalCorpusId, self::CanonicalCorpusFingerprint, 324833, 16, 35, 5, $tables, self::evidence());
    }

    /** @param array<string, int> $tables */
    public static function synthetic(string $corpusId, string $corpusFingerprint, array $tables, int $mediaRecords, int $pricingRecords): self
    {
        if (! app()->environment('testing')) {
            throw new InvalidArgumentException('Synthetic seed profiles are available only in tests.');
        }

        $ledger = [];
        foreach ($tables as $table => $count) {
            $ledger[$table] = ['count' => $count, 'disposition' => 'PRESERVE_UNINTERPRETED', 'confidence' => 'ESTABLISHED'];
        }

        return new self('synthetic-seed-profile', self::Contract, hash('sha256', CanonicalJson::encode($ledger)), $corpusId, $corpusFingerprint, array_sum($tables), $mediaRecords, $mediaRecords, $pricingRecords, $ledger);
    }

    /** @return array<string, mixed> */
    private static function evidence(): array
    {
        return [
            'owners' => ['rows' => 3194, 'collision_groups' => 286, 'rows_in_collision_groups' => 697, 'automatic_merges' => 0],
            'businesses' => ['rows' => 3212, 'collision_groups' => 48, 'rows_in_collision_groups' => 128, 'automatic_merges' => 0],
            'applications' => ['rows' => 3137, 'types' => ['Renewal' => 2621, 'New' => 461, 'Additional' => 55], 'statuses' => ['Released' => 2907, 'Assessment' => 199, 'Pending Payment' => 28, 'Draft' => 3]],
            'lines_of_business' => ['items' => 4236, 'usable_normalized_labels' => 562, 'probable' => 559, 'ambiguous' => 1, 'unmatched' => 2, 'unlabelled' => 5],
            'barangays' => ['rows' => 44, 'normalized_proposals' => 24, 'unresolved' => 20, 'automatic_psgc_mappings' => 0],
            'financial' => ['schedules' => 7648, 'schedule_statuses' => ['paid' => 5741, 'pending' => 1904, 'partial' => 3], 'payments' => 5874, 'payment_statuses' => ['completed' => 5744, 'failed' => 129, 'pending' => 1], 'receipt_claims' => 5873, 'duplicate_receipt_groups' => 196, 'events_in_duplicate_groups' => 744, 'paid_anchor_decimal' => '93295317.20', 'non_cent_application_totals' => 348, 'non_cent_schedule_totals' => 24],
            'permits' => ['rows' => 2766, 'application_linked' => 2751, 'missing_application' => 15, 'broken_business' => 10, 'broken_owner' => 10],
            'clearances' => ['rows' => 14615, 'broken_type_references' => 110],
            'media' => ['rows' => 35, 'typed' => 16, 'unresolved' => 19],
            'pricing' => ['records' => 5, 'state' => 'candidate-only'],
            'unresolved' => [
                'barangays' => 20,
                'lob_ambiguous' => 1,
                'lob_unmatched' => 2,
                'lob_unlabelled' => 5,
                'media_objects' => 19,
                'permit_missing_application' => 15,
                'permit_broken_business' => 10,
                'permit_broken_owner' => 10,
                'clearance_broken_type' => 110,
            ],
        ];
    }
}
