<?php

namespace App\Enums;

enum PermitClearanceStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Blocked = 'blocked';
}
