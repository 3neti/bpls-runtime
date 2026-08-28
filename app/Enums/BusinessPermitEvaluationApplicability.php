<?php

namespace App\Enums;

enum BusinessPermitEvaluationApplicability: string
{
    case Applicable = 'applicable';
    case NotApplicable = 'not_applicable';
    case Undetermined = 'undetermined';
}
