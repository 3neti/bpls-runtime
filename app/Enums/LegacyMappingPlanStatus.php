<?php

namespace App\Enums;

enum LegacyMappingPlanStatus: string
{
    case Planning = 'planning';
    case Planned = 'planned';
    case PlannedWithExceptions = 'planned_with_exceptions';
    case Failed = 'failed';
}
