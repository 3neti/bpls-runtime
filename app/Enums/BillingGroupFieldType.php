<?php

namespace App\Enums;

enum BillingGroupFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Currency = 'currency';
    case Date = 'date';
    case Dropdown = 'dropdown';
    case Checkbox = 'checkbox';
}
