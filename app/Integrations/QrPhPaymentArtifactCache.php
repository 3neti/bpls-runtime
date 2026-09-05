<?php

namespace App\Integrations;

use App\Models\XChangePaymentAttempt;
use Illuminate\Support\Facades\Cache;

class QrPhPaymentArtifactCache
{
    public function store(
        XChangePaymentAttempt $attempt,
        string $mimeType,
        string $base64Payload,
    ): void {
        if ($attempt->expires_at === null || $attempt->expires_at->isPast()) {
            return;
        }

        Cache::put($this->key($attempt), [
            'mime_type' => $mimeType,
            'base64_payload' => $base64Payload,
        ], $attempt->expires_at);
    }

    public function dataUrl(XChangePaymentAttempt $attempt): ?string
    {
        if ($attempt->expires_at === null || $attempt->expires_at->isPast()) {
            return null;
        }

        $artifact = Cache::get($this->key($attempt));
        if (! is_array($artifact)
            || ! is_string($artifact['mime_type'] ?? null)
            || ! is_string($artifact['base64_payload'] ?? null)) {
            return null;
        }

        return 'data:'.$artifact['mime_type'].';base64,'.$artifact['base64_payload'];
    }

    private function key(XChangePaymentAttempt $attempt): string
    {
        return 'qr-ph:artifact:attempt:'.$attempt->id.':'.hash('sha256', (string) $attempt->reference);
    }
}
