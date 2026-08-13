<?php

namespace App\Enums;

enum TreasuryCollectionStatus: string
{
    case PendingReceipt = 'pending_receipt';
    case Receipted = 'receipted';
    case Voided = 'voided';
}
