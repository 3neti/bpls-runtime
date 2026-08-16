<?php

namespace App\Enums;

enum LegacyLineOfBusinessReconciliationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
