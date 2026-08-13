<?php

namespace App\Exceptions;

use App\Models\Receipt;
use RuntimeException;

class UnresolvedReceiptPolicy extends RuntimeException
{
    public static function voiding(Receipt $receipt): self
    {
        return new self("Receipt voiding policy is unresolved for receipt [{$receipt->id}].");
    }
}
