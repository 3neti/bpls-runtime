<?php

namespace App\Support\IpilRescue;

final readonly class IpilCullResult
{
    /** @param array{database_rows: int, media_metadata: int, media_bytes: int, pricing_records: int, exceptions: int} $counts */
    public function __construct(
        public string $snapshotId,
        public string $snapshotPath,
        public string $corpusFingerprint,
        public array $counts,
        public int $databaseTables,
        public int $mediaRetrieved,
        public int $mediaMissing,
        public int $mediaFailures,
        public int $unresolvedMedia,
        public int $mediaBytes,
        public string $sourceSchemaFingerprint,
        public string $pricingFingerprint,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'passed' => true,
            'snapshot' => $this->snapshotId,
            'snapshot_path' => $this->snapshotPath,
            'state' => 'FINALIZED',
            'database' => [
                'tables' => $this->databaseTables,
                'rows' => $this->counts['database_rows'],
                'schema_fingerprint' => $this->sourceSchemaFingerprint,
            ],
            'media' => [
                'document_records' => $this->counts['media_metadata'],
                'objects_retrieved' => $this->mediaRetrieved,
                'bytes_rescued' => $this->mediaBytes,
                'missing_source_bytes' => $this->mediaMissing,
                'retrieval_failures' => $this->mediaFailures,
                'unresolved_objects' => $this->unresolvedMedia,
            ],
            'pricing' => [
                'records' => $this->counts['pricing_records'],
                'fingerprint' => $this->pricingFingerprint,
            ],
            'exceptions' => $this->counts['exceptions'],
            'verification' => 'PASS',
            'corpus_fingerprint' => $this->corpusFingerprint,
            'domain_writes' => false,
            'bulk_rescue_executed' => false,
        ];
    }
}
