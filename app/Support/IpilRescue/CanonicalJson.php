<?php

namespace App\Support\IpilRescue;

use JsonException;

final class CanonicalJson
{
    /**
     * @param  array<string, mixed>  $value
     *
     * @throws JsonException
     */
    public static function encode(array $value): string
    {
        return json_encode(
            self::normalize($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        );
    }

    private static function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::normalize(...), $value);
        }

        ksort($value);

        return array_map(self::normalize(...), $value);
    }
}
