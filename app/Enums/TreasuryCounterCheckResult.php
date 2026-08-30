<?php

namespace App\Enums;

enum TreasuryCounterCheckResult: string
{
    case NoCorrection = 'no_correction';

    case MaterialCorrection = 'material_correction';
}
