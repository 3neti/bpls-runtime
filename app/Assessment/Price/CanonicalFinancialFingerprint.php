<?php

namespace App\Assessment\Price;

use Illuminate\Support\Arr;

final class CanonicalFinancialFingerprint
{
    /** @param array<string, mixed> $payload */
    public function hash(array $payload): string
    {
        return hash('sha256', json_encode(
            $this->normalize($payload),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    /**
     * @param  array<mixed>  $payload
     * @return array<mixed>
     */
    private function normalize(array $payload): array
    {
        if (Arr::isAssoc($payload)) {
            unset($payload['symbol'], $payload['formatted'], $payload['display']);
            ksort($payload);
        }

        return array_map(
            fn (mixed $value): mixed => is_array($value) ? $this->normalize($value) : $value,
            $payload,
        );
    }
}
