<?php

namespace App\Enums;

enum MigrationExceptionStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
