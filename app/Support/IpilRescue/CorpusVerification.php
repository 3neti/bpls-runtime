<?php

namespace App\Support\IpilRescue;

final readonly class CorpusVerification
{
    public function __construct(
        public string $corpusId,
        public string $corpusFingerprint,
        public int $verifiedFileCount,
        public int $sourceIdentityCount,
        /** @var array{database_rows: int, media_metadata: int, media_bytes: int, pricing_records: int, exceptions: int} */
        public array $semanticCounts,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'passed' => true,
            'integrity_passed' => true,
            'semantic_contracts_passed' => true,
            'verification_complete' => true,
            'offline' => true,
            'healed' => false,
            'domain_writes' => false,
            'corpus_id' => $this->corpusId,
            'corpus_fingerprint' => $this->corpusFingerprint,
            'verified_file_count' => $this->verifiedFileCount,
            'source_identity_count' => $this->sourceIdentityCount,
            'counts' => $this->semanticCounts,
        ];
    }
}
