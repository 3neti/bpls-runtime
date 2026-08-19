<?php

namespace App\Enums;

enum AssessmentDecisionAction: string
{
    case Approved = 'approved';
    case ReturnedForCorrection = 'returned_for_correction';
}
