<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class ReceiptCivilTime
{
    public static function timezone(): string
    {
        return (string) config('municipality.timezone', 'Asia/Manila');
    }

    public static function iso(CarbonInterface $instant): string
    {
        return $instant->copy()->setTimezone(self::timezone())->toIso8601String();
    }

    public static function date(CarbonInterface $instant, string $format = 'Y-m-d'): string
    {
        return $instant->copy()->setTimezone(self::timezone())->format($format);
    }
}
