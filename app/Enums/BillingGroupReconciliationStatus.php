<?php

namespace App\Enums;

enum BillingGroupReconciliationStatus: string
{
    case PendingMunicipalDecision = 'pending_municipal_decision';
}
