<?php

namespace App\Enums;

enum PaymentScheduleLineStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Waived = 'waived';
    case Voided = 'voided';
}
