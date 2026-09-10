<?php

namespace App\Support\IpilRescue;

use Illuminate\Http\Client\Factory;
use RuntimeException;
use Throwable;

final class ConvexAuthenticatedMediaRetriever
{
    public function __construct(private readonly Factory $http) {}

    public function assertAuthenticated(string $url, string $token): void
    {
        try {
            $value = $this->query($url, $token, 'users:getCurrentUser', []);

            if ($value === null) {
                throw new RuntimeException('The configured Convex user token is not authenticated.');
            }

            $probe = $this->query($url, $token, 'businesses:getDocumentUrls', ['storageIds' => []]);

            if (! is_array($probe)) {
                throw new RuntimeException('The configured Convex user cannot establish read-only media access.');
            }
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException && str_starts_with($exception->getMessage(), 'The configured Convex')) {
                throw $exception;
            }

            throw new RuntimeException('Authenticated Convex media preflight failed.', previous: $exception);
        }
    }

    /** @return array{status: string, bytes: string|null, attempts: int, error: string|null} */
    public function retrieve(string $url, string $token, string $storageId, int $maximumAttempts): array
    {
        $attempts = 0;

        while ($attempts < $maximumAttempts) {
            $attempts++;

            try {
                $downloadUrl = $this->query($url, $token, 'businesses:getDocumentUrl', ['storageId' => $storageId]);

                if (! is_string($downloadUrl) || $downloadUrl === '') {
                    return ['status' => 'source-missing', 'bytes' => null, 'attempts' => $attempts, 'error' => 'Source returned no media URL.'];
                }

                $response = $this->http->timeout(60)->get($downloadUrl);

                if ($response->successful()) {
                    return ['status' => 'retrieved', 'bytes' => $response->body(), 'attempts' => $attempts, 'error' => null];
                }

                if ($response->status() === 404) {
                    return ['status' => 'source-missing', 'bytes' => null, 'attempts' => $attempts, 'error' => 'Source media bytes were not found.'];
                }

                if (in_array($response->status(), [401, 403], true)) {
                    return ['status' => 'access-denied', 'bytes' => null, 'attempts' => $attempts, 'error' => 'Source denied media retrieval.'];
                }
            } catch (Throwable $exception) {
                if ($attempts >= $maximumAttempts) {
                    return ['status' => 'retrieval-failed', 'bytes' => null, 'attempts' => $attempts, 'error' => 'Authenticated media retrieval failed after bounded retries.'];
                }
            }
        }

        return ['status' => 'retrieval-failed', 'bytes' => null, 'attempts' => $attempts, 'error' => 'Authenticated media retrieval failed after bounded retries.'];
    }

    /** @param array<string, mixed> $arguments */
    private function query(string $url, string $token, string $path, array $arguments): mixed
    {
        $response = $this->http
            ->withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->post(rtrim($url, '/').'/api/query', [
                'path' => $path,
                'args' => $arguments,
                'format' => 'json',
            ])
            ->throw()
            ->json();

        if (! is_array($response) || ($response['status'] ?? null) !== 'success') {
            throw new RuntimeException('Convex query did not return a successful response.');
        }

        return $response['value'] ?? null;
    }
}
