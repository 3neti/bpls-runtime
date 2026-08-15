<?php

namespace App\Enums;

enum RevenueCodeProvisionType: string
{
    case TaxSchedule = 'tax_schedule';
    case PercentageRate = 'percentage_rate';
    case FixedFee = 'fixed_fee';
    case AdministrativeRule = 'administrative_rule';
    case EvidenceRequirement = 'evidence_requirement';
    case PresumptiveIncomeSchedule = 'presumptive_income_schedule';
}
