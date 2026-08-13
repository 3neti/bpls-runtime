<?php

namespace App\Enums;

enum FeeRuleCategory: string
{
    case Tax = 'tax';
    case Fee = 'fee';
    case Clearance = 'clearance';
    case Other = 'other';
}
