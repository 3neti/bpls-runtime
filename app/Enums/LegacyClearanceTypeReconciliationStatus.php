<?php

namespace App\Enums;

enum LegacyClearanceTypeReconciliationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
