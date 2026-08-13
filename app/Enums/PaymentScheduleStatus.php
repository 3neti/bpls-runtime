<?php

namespace App\Enums;

enum PaymentScheduleStatus: string
{
    case Pending = 'pending';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Voided = 'voided';
}
