<?php

namespace App\Support\IpilRescue;

use InvalidArgumentException;

final readonly class HistoricalAmount
{
    private function __construct(public string $sourceLexeme, public string $decimal, public ?string $minorUnits) {}

    public static function fromLexeme(string $lexeme): self
    {
        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?(?:[eE]([+-]?\d+))?$/', $lexeme, $matches) !== 1) {
            throw new InvalidArgumentException('Historical amount must be an exact JSON decimal lexeme.');
        }

        $sign = $matches[1];
        $whole = $matches[2];
        $fraction = $matches[3] ?? '';
        $exponent = (int) ($matches[4] ?? 0);
        $digits = $whole.$fraction;
        $point = strlen($whole) + $exponent;

        if ($point <= 0) {
            $whole = '0';
            $fraction = str_repeat('0', -$point).$digits;
        } elseif ($point >= strlen($digits)) {
            $whole = $digits.str_repeat('0', $point - strlen($digits));
            $fraction = '';
        } else {
            $whole = substr($digits, 0, $point);
            $fraction = substr($digits, $point);
        }

        $whole = ltrim($whole, '0') ?: '0';
        $fraction = rtrim($fraction, '0');
        $isZero = $whole === '0' && $fraction === '';
        $normalizedSign = $sign === '-' && ! $isZero ? '-' : '';
        $decimal = $normalizedSign.$whole.($fraction === '' ? '' : '.'.$fraction);
        $minorUnits = null;

        if (strlen($fraction) <= 2) {
            $minorDigits = ltrim($whole.str_pad($fraction, 2, '0'), '0') ?: '0';
            $minorUnits = $normalizedSign.$minorDigits;
        }

        return new self($lexeme, $decimal, $minorUnits);
    }

    public function isCentExact(): bool
    {
        return $this->minorUnits !== null;
    }
}
