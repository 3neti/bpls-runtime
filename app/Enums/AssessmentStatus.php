<?php

namespace App\Enums;

enum AssessmentStatus: string
{
    case Draft = 'draft';
    case Computed = 'computed';
    case Voided = 'voided';
}
