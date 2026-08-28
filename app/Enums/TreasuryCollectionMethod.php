<?php

namespace App\Enums;

enum TreasuryCollectionMethod: string
{
    case Cash = 'cash';
    case Check = 'check';
    case MoneyOrder = 'money_order';
    case QrPh = 'qr_ph';
    case Other = 'other';
}
