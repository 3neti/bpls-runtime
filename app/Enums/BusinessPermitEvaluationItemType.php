<?php

namespace App\Enums;

enum BusinessPermitEvaluationItemType: string
{
    case Fact = 'fact';
    case Determination = 'determination';
    case Charge = 'charge';
}
