<?php

namespace App\Actions;

use App\Models\LegacyDocumentObjectReconciliation;
use App\Models\PermitApplicationDocument;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LegacyDocumentObjectIntegrity
{
    public const MaximumSizeBytes = 10 * 1024 * 1024;

    /** @var array<string, string> */
    private const Extensions = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /** @return array{checksum: string, size_bytes: int, mime_type: string, extension: string} */
    public function inspectLocalFile(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Document object is unavailable or unreadable.');
        }

        $checksum = hash_file('sha256', $path);
        $size = filesize($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if (! is_string($checksum) || ! is_int($size) || ! is_string($mime)) {
            throw new RuntimeException('Document object integrity metadata could not be determined.');
        }

        return $this->validatedInspection($checksum, $size, $mime);
    }

    /** @return array{checksum: string, size_bytes: int, mime_type: string, extension: string} */
    public function inspectStoredObject(string $disk, string $path): array
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            throw new RuntimeException('Staged document object is missing from private storage.');
        }

        $stream = $storage->readStream($path);
        if (! is_resource($stream)) {
            throw new RuntimeException('Staged document object could not be read from private storage.');
        }

        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);
            $checksum = hash_final($context);
        } finally {
            fclose($stream);
        }

        $size = $storage->size($path);
        $mime = $storage->mimeType($path);

        return $this->validatedInspection($checksum, $size, is_string($mime) ? $mime : '');
    }

    /** @return array{checksum: string, size_bytes: int, mime_type: string, extension: string} */
    public function assertReconciledObject(LegacyDocumentObjectReconciliation $reconciliation): array
    {
        $inspection = $this->inspectStoredObject($reconciliation->staged_disk, $reconciliation->staged_path);

        if (! hash_equals($reconciliation->object_checksum, $inspection['checksum'])
            || $reconciliation->size_bytes !== $inspection['size_bytes']
            || $reconciliation->mime_type !== $inspection['mime_type']) {
            throw new RuntimeException("Legacy document reconciliation [{$reconciliation->id}] no longer matches its staged object.");
        }

        return $inspection;
    }

    /** @return array{checksum: string, size_bytes: int, mime_type: string, extension: string} */
    public function assertDocumentObject(PermitApplicationDocument $document, string $expectedChecksum): array
    {
        $inspection = $this->inspectStoredObject($document->storage_disk, $document->path);

        if (! hash_equals($expectedChecksum, $inspection['checksum'])
            || $document->size_bytes !== $inspection['size_bytes']
            || $document->mime_type !== $inspection['mime_type']) {
            throw new RuntimeException("Permit application document [{$document->id}] no longer matches its migrated object.");
        }

        return $inspection;
    }

    public function copyLocalFile(string $sourcePath, string $disk, string $destinationPath): bool
    {
        $stream = fopen($sourcePath, 'rb');
        if (! is_resource($stream)) {
            throw new RuntimeException('Document object could not be opened for staging.');
        }

        try {
            return Storage::disk($disk)->put($destinationPath, $stream) !== false;
        } finally {
            fclose($stream);
        }
    }

    public function copyStoredObject(string $sourceDisk, string $sourcePath, string $destinationDisk, string $destinationPath): bool
    {
        $stream = Storage::disk($sourceDisk)->readStream($sourcePath);
        if (! is_resource($stream)) {
            throw new RuntimeException('Staged document object could not be opened for migration.');
        }

        try {
            return Storage::disk($destinationDisk)->put($destinationPath, $stream) !== false;
        } finally {
            fclose($stream);
        }
    }

    public function extensionForMime(string $mimeType): string
    {
        return self::Extensions[$mimeType] ?? throw new RuntimeException("Document MIME type [{$mimeType}] is not permitted.");
    }

    /** @return array{checksum: string, size_bytes: int, mime_type: string, extension: string} */
    private function validatedInspection(string $checksum, int $size, string $mime): array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1) {
            throw new RuntimeException('Document object checksum is invalid.');
        }
        if ($size < 1 || $size > self::MaximumSizeBytes) {
            throw new RuntimeException('Document object must contain 1 byte to 10 megabytes.');
        }

        return [
            'checksum' => $checksum,
            'size_bytes' => $size,
            'mime_type' => $mime,
            'extension' => $this->extensionForMime($mime),
        ];
    }
}
