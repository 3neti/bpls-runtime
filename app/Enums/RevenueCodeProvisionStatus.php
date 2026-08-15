<?php

namespace App\Enums;

enum RevenueCodeProvisionStatus: string
{
    case Recorded = 'recorded';
    case ReconciliationRequired = 'reconciliation_required';
    case Reconciled = 'reconciled';
}
