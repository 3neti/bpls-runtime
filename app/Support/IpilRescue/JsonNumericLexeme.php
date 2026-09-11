<?php

namespace App\Support\IpilRescue;

use RuntimeException;

final class JsonNumericLexeme
{
    public static function field(string $json, string $field): ?string
    {
        $quoted = preg_quote(json_encode($field, JSON_THROW_ON_ERROR), '/');
        $number = '-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?';

        if (preg_match('/'.$quoted.'\s*:\s*('.$number.')/', $json, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/'.$quoted.'\s*:\s*null/', $json) === 1) {
            return null;
        }

        if (preg_match('/'.$quoted.'\s*:/', $json) === 1) {
            throw new RuntimeException("The exact JSON numeric lexeme for {$field} is invalid.");
        }

        return null;
    }
}
