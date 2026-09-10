<?php

namespace App\Actions;

use App\Support\IpilRescue\CorpusVerification;
use App\Support\IpilRescue\RescueCorpusManifest;
use App\Support\IpilRescue\SourceIdentity;
use JsonException;
use RuntimeException;

final class VerifyIpilRescueCorpus
{
    public function handle(string $corpusPath): CorpusVerification
    {
        if (str_contains($corpusPath, '://')) {
            throw new RuntimeException('The rescue corpus must be a local filesystem path; URLs are forbidden.');
        }

        $root = realpath($corpusPath);

        if ($root === false || ! is_dir($root)) {
            throw new RuntimeException('The rescue corpus directory does not exist.');
        }

        if ($this->isWithin($root, public_path())) {
            throw new RuntimeException('A rescue corpus cannot be verified from the public web root.');
        }

        $configuredPrivateRescueRoot = storage_path('app/private/ipil-rescue');

        if (is_link($configuredPrivateRescueRoot)) {
            throw new RuntimeException('The configured private rescue root cannot be a symbolic link.');
        }

        $privateRescueRoot = realpath($configuredPrivateRescueRoot);
        $isInApprovedPrivateRoot = $privateRescueRoot !== false && $this->isWithin($root, $privateRescueRoot);

        if (in_array('.git', explode(DIRECTORY_SEPARATOR, $root), true) || file_exists($root.DIRECTORY_SEPARATOR.'.git')) {
            throw new RuntimeException('A rescue corpus cannot be inside or contain Git metadata.');
        }

        if ($this->isWithin($root, base_path()) && ! $isInApprovedPrivateRoot) {
            throw new RuntimeException('A rescue corpus inside this repository must use the ignored private rescue root.');
        }

        if (! $isInApprovedPrivateRoot && $this->hasGitAncestor($root)) {
            throw new RuntimeException('A rescue corpus cannot be inside or contain Git metadata.');
        }

        $manifestPayload = $this->readJsonObject($this->localFile($root, 'corpus.json'));
        $manifest = RescueCorpusManifest::fromArray($manifestPayload);
        $checksums = $this->readChecksums($this->localFile($root, 'verification/checksums.sha256'));

        if ($checksums !== $manifest->bindings) {
            throw new RuntimeException('The checksum manifest does not exactly match the corpus bindings.');
        }

        $this->verifyCompleteInventory($root, $manifest);

        foreach ($manifest->bindings as $relativePath => $expectedChecksum) {
            $actualChecksum = hash_file('sha256', $this->localFile($root, $relativePath));

            if ($actualChecksum === false || ! hash_equals($expectedChecksum, $actualChecksum)) {
                throw new RuntimeException("Checksum verification failed for {$relativePath}.");
            }
        }

        $sourceIdentityCount = $this->verifySourceIdentities(
            $this->localFile($root, 'provenance/source-identities.jsonl'),
            $manifest,
        );
        $expectedSourceIdentities = $manifest->counts['database_rows']
            + $manifest->counts['media_metadata']
            + $manifest->counts['pricing_records'];

        if ($sourceIdentityCount !== $expectedSourceIdentities) {
            throw new RuntimeException(
                "Source identity count {$sourceIdentityCount} does not match the manifest count {$expectedSourceIdentities}.",
            );
        }

        return new CorpusVerification(
            $manifest->corpusId,
            $manifest->fingerprint(),
            count($manifest->bindings),
            $sourceIdentityCount,
        );
    }

    private function verifyCompleteInventory(string $root, RescueCorpusManifest $manifest): void
    {
        $actual = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new RuntimeException('Symbolic links are forbidden in rescue corpora.');
            }

            if ($file->getFilename() === '.git') {
                throw new RuntimeException('A rescue corpus cannot contain Git metadata.');
            }

            if (! $file->isFile()) {
                continue;
            }

            $actual[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
        }

        $expected = [
            'corpus.json',
            'verification/checksums.sha256',
            ...array_keys($manifest->bindings),
        ];
        sort($actual);
        sort($expected);

        if ($actual !== $expected) {
            throw new RuntimeException('The rescue corpus contains missing or unbound files.');
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function readJsonObject(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read {$path}.");
        }

        $payload = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($payload) || array_is_list($payload)) {
            throw new RuntimeException("Expected a JSON object in {$path}.");
        }

        return $payload;
    }

    /** @return array<string, string> */
    private function readChecksums(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Unable to read the checksum manifest.');
        }

        $checksums = [];
        $lines = preg_split('/\R/', trim($contents));

        if ($lines === false || $lines === ['']) {
            throw new RuntimeException('The checksum manifest is empty.');
        }

        foreach ($lines as $line) {
            if (preg_match('/^([a-f0-9]{64})  (.+)$/', $line, $matches) !== 1) {
                throw new RuntimeException('The checksum manifest contains an invalid line.');
            }

            $relativePath = $matches[2];

            if (! RescueCorpusManifest::isSafeRelativePath($relativePath)) {
                throw new RuntimeException('The checksum manifest contains an unsafe path.');
            }

            if (array_key_exists($relativePath, $checksums)) {
                throw new RuntimeException("The checksum manifest repeats {$relativePath}.");
            }

            $checksums[$relativePath] = $matches[1];
        }

        ksort($checksums);

        return $checksums;
    }

    private function verifySourceIdentities(string $path, RescueCorpusManifest $manifest): int
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Unable to read the source identity registry.');
        }

        if (trim($contents) === '') {
            return 0;
        }

        $lines = preg_split('/\R/', trim($contents));

        if ($lines === false) {
            throw new RuntimeException('Unable to parse the source identity registry.');
        }

        $seen = [];

        foreach ($lines as $lineNumber => $line) {
            try {
                $payload = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException(
                    'Invalid JSON in source identity line '.($lineNumber + 1).'.',
                    previous: $exception,
                );
            }

            if (! is_array($payload) || array_is_list($payload)) {
                throw new RuntimeException('Each source identity line must be a JSON object.');
            }

            $identity = SourceIdentity::fromArray($payload);

            if (
                $identity->corpusId !== $manifest->corpusId
                || $identity->sourceSystem !== $manifest->source['system']
                || $identity->deploymentIdentitySha256 !== $manifest->source['deployment_identity_sha256']
            ) {
                throw new RuntimeException('A source identity does not belong to this corpus and source.');
            }

            if (! array_key_exists($identity->rawEvidenceLocator, $manifest->bindings)) {
                throw new RuntimeException('A source identity points to evidence that is not checksum bound.');
            }

            $identityKey = $identity->dataset."\0".$identity->sourceKeySha256;

            if (isset($seen[$identityKey])) {
                throw new RuntimeException('The source identity registry contains a duplicate source key.');
            }

            $seen[$identityKey] = true;
        }

        return count($lines);
    }

    private function localFile(string $root, string $relativePath): string
    {
        if (! RescueCorpusManifest::isSafeRelativePath($relativePath)) {
            throw new RuntimeException('A rescue corpus path is unsafe.');
        }

        $candidate = $root;

        foreach (explode('/', $relativePath) as $segment) {
            $candidate .= DIRECTORY_SEPARATOR.$segment;

            if (is_link($candidate)) {
                throw new RuntimeException("Symbolic links are forbidden in rescue corpora: {$relativePath}.");
            }
        }

        $resolved = realpath($candidate);

        if ($resolved === false || ! is_file($resolved) || ! $this->isWithin($resolved, $root)) {
            throw new RuntimeException("The rescue corpus file {$relativePath} is missing or outside its root.");
        }

        return $resolved;
    }

    private function isWithin(string $path, string $root): bool
    {
        $resolvedRoot = realpath($root);

        if ($resolvedRoot === false) {
            return false;
        }

        return $path === $resolvedRoot || str_starts_with($path, $resolvedRoot.DIRECTORY_SEPARATOR);
    }

    private function hasGitAncestor(string $path): bool
    {
        $current = $path;

        while (true) {
            if (file_exists($current.DIRECTORY_SEPARATOR.'.git')) {
                return true;
            }

            $parent = dirname($current);

            if ($parent === $current) {
                return false;
            }

            $current = $parent;
        }
    }
}
