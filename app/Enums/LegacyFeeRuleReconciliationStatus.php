<?php

namespace App\Enums;

enum LegacyFeeRuleReconciliationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
