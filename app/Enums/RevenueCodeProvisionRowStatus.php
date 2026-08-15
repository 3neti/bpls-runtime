<?php

namespace App\Enums;

enum RevenueCodeProvisionRowStatus: string
{
    case Exact = 'exact';
    case ReconciliationRequired = 'reconciliation_required';
}
