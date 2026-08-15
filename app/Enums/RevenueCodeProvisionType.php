<?php

namespace App\Enums;

enum RevenueCodeProvisionType: string
{
    case TaxSchedule = 'tax_schedule';
    case PercentageRate = 'percentage_rate';
    case FixedFee = 'fixed_fee';
}
