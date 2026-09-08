<?php

namespace App\Enums;

enum FeeDeterminationChannel: string
{
    case ConcernedOfficePaymentOrder = 'concerned_office_payment_order';
    case TreasuryLineOfBusiness = 'treasury_lob';
    case AutomaticAssessment = 'automatic_assessment';
    case ReferenceOnly = 'reference_only';
}
