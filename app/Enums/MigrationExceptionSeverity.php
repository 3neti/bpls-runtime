<?php

namespace App\Enums;

enum MigrationExceptionSeverity: string
{
    case Warning = 'warning';
    case Error = 'error';
}
