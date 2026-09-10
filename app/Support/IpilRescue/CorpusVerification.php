<?php

namespace App\Support\IpilRescue;

final readonly class CorpusVerification
{
    public function __construct(
        public string $corpusId,
        public string $corpusFingerprint,
        public int $verifiedFileCount,
        public int $sourceIdentityCount,
    ) {}

    /** @return array<string, bool|int|string> */
    public function toArray(): array
    {
        return [
            'passed' => false,
            'integrity_passed' => true,
            'verification_complete' => false,
            'offline' => true,
            'healed' => false,
            'domain_writes' => false,
            'corpus_id' => $this->corpusId,
            'corpus_fingerprint' => $this->corpusFingerprint,
            'verified_file_count' => $this->verifiedFileCount,
            'source_identity_count' => $this->sourceIdentityCount,
        ];
    }
}
