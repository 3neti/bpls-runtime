<?php

namespace App\Enums;

enum LegacyMigrationReadinessStatus: string
{
    case Assessing = 'assessing';
    case RehearsalReady = 'rehearsal_ready';
    case Blocked = 'blocked';
}
