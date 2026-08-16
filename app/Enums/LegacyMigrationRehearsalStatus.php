<?php

namespace App\Enums;

enum LegacyMigrationRehearsalStatus: string
{
    case Verifying = 'verifying';
    case Verified = 'verified';
    case VerificationFailed = 'verification_failed';
    case RollingBack = 'rolling_back';
    case RollbackFailed = 'rollback_failed';
    case RolledBack = 'rolled_back';
}
