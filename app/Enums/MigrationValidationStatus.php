<?php

namespace App\Enums;

enum MigrationValidationStatus: string
{
    case Passed = 'passed';
    case Warning = 'warning';
    case Failed = 'failed';
}
