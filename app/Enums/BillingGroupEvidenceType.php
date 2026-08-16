<?php

namespace App\Enums;

enum BillingGroupEvidenceType: string
{
    case Ordinance = 'ordinance';
    case TermsOfReference = 'terms_of_reference';
    case LegacyConfiguration = 'legacy_configuration';
    case ObservedTreasuryPractice = 'observed_treasury_practice';
    case MunicipalSubmission = 'municipal_submission';
}
