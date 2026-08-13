<?php

namespace App\Enums;

enum FeeRuleCalculationType: string
{
    case Fixed = 'fixed';
    case Range = 'range';
    case Formula = 'formula';
}
