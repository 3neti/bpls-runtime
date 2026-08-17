<?php

namespace App\Enums;

enum LegacyFinancialMigrationDisposition: string
{
    case DeterministicAndRehearsalEligible = 'deterministic_and_rehearsal_eligible';
    case DeterministicHistoricalSnapshotIncompleteProvenance = 'deterministic_historical_snapshot_incomplete_provenance';
    case ReconciliationRequired = 'reconciliation_required';
    case QuarantinedHistoricalEvidence = 'quarantined_historical_evidence';
    case AuthorityBlocked = 'authority_blocked';
}
