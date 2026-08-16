<?php

namespace App\Enums;

enum LegacyMappingProposalStatus: string
{
    case Ready = 'ready';
    case ReviewRequired = 'review_required';
    case Blocked = 'blocked';
}
