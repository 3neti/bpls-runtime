<?php

namespace App\Enums;

enum LegacyMappingExecutionStatus: string
{
    case Executing = 'executing';
    case Completed = 'completed';
    case Failed = 'failed';
    case RolledBack = 'rolled_back';
}
