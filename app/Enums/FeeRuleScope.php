<?php

namespace App\Enums;

enum FeeRuleScope: string
{
    case Application = 'application';
    case LineOfBusiness = 'line_of_business';
}
