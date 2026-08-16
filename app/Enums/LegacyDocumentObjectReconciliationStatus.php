<?php

namespace App\Enums;

enum LegacyDocumentObjectReconciliationStatus: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
