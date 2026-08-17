<?php

namespace App\Enums;

enum PermitApplicationStatus: string
{
    case Draft = 'draft';
    case Assessment = 'assessment';
    case Approval = 'approval';
    case PendingPayment = 'pending_payment';
    case Released = 'released';
    case HistoricalEvidence = 'historical_evidence';
    case Cancelled = 'cancelled';
}
