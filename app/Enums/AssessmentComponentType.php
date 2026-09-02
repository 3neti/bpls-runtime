<?php

namespace App\Enums;

enum AssessmentComponentType: string
{
    case GovernedFee = 'governed_fee';
    case BusinessTax = 'business_tax';
    case PaperlessPaymentOrder = 'paperless_payment_order';
    case Surcharge = 'surcharge';
    case Penalty = 'penalty';
    case Adjustment = 'adjustment';
}
