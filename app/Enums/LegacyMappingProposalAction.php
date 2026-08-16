<?php

namespace App\Enums;

enum LegacyMappingProposalAction: string
{
    case Create = 'create';
    case LinkExactLegacyId = 'link_exact_legacy_id';
    case Review = 'review';
    case Blocked = 'blocked';
}
