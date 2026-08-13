<?php

namespace App\Actions;

use App\Exceptions\UnresolvedReceiptPolicy;
use App\Models\Receipt;
use App\Models\User;

class VoidReceipt
{
    public function handle(Receipt $receipt, ?User $voidedBy = null): never
    {
        throw UnresolvedReceiptPolicy::voiding($receipt);
    }
}
