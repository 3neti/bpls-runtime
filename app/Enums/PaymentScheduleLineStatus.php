<?php

namespace App\Enums;

enum PaymentScheduleLineStatus: string
{
    case Pending = 'pending';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Waived = 'waived';
    case Voided = 'voided';
}
