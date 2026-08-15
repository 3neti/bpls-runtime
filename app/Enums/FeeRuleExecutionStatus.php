<?php

namespace App\Enums;

enum FeeRuleExecutionStatus: string
{
    case Executable = 'executable';
    case Blocked = 'blocked';
}
