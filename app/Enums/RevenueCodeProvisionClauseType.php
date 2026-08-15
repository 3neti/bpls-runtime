<?php

namespace App\Enums;

enum RevenueCodeProvisionClauseType: string
{
    case DependentRate = 'dependent_rate';
    case Eligibility = 'eligibility';
    case TaxBase = 'tax_base';
    case RateBand = 'rate_band';
    case AuthorityBoundary = 'authority_boundary';
    case TaxableReceiptCatalog = 'taxable_receipt_catalog';
    case DocumentaryRequirement = 'documentary_requirement';
    case AmountCeiling = 'amount_ceiling';
    case Exemption = 'exemption';
    case PaymentTiming = 'payment_timing';
}
