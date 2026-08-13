<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Bplo = 'bplo';
    case Treasury = 'treasury';
    case Citizen = 'citizen';
}
