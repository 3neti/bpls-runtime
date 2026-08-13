<?php

namespace App\Enums;

enum PermitApplicationType: string
{
    case New = 'new';
    case Renewal = 'renewal';
    case Additional = 'additional';
    case Amendment = 'amendment';
    case Transfer = 'transfer';
    case Retirement = 'retirement';
}
