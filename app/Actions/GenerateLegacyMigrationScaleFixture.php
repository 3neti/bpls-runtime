<?php

namespace App\Actions;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;
use SplFileObject;

final class GenerateLegacyMigrationScaleFixture
{
    public const SchemaVersion = 'bpls.legacy-scale-fixture.v1';

    /**
     * @param  array<string, mixed>  $profile
     * @return array{manifest_path: string, artifact_root: string, profile_hash: string, profile: array<string, mixed>, dataset_counts: array<string, int>}
     */
    public function handle(string $runReference, array $profile): array
    {
        $this->assertEnvironment();
        $this->assertRunReference($runReference);
        $profile = $this->validatedProfile($profile);
        $profileHash = $this->hash($profile);
        $artifactRoot = "legacy-migrations/scale-fixtures/{$runReference}";
        $absoluteRoot = Storage::disk('local')->path($artifactRoot);
        File::ensureDirectoryExists($absoluteRoot);

        $profilePath = $absoluteRoot.'/profile.json';
        if (File::exists($profilePath)) {
            $existing = json_decode((string) File::get($profilePath), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($existing) || ! hash_equals($profileHash, $this->hash($existing))) {
                throw new RuntimeException("Scale fixture run [{$runReference}] is bound to a different profile.");
            }
        } else {
            File::put($profilePath, $this->json($profile)."\n");
        }

        $datasets = $this->writeDatasets($absoluteRoot, $profile);
        $manifest = [
            'schema_version' => StageLegacyExport::SchemaVersion,
            'source' => [
                'key' => 'SYNTHETIC-SCALE-'.strtoupper(substr($profileHash, 0, 16)),
                'title' => 'Deterministic production-shaped legacy scale fixture',
                'source_type' => 'synthetic_scale_fixture',
                'baseline' => $profileHash,
                'archive_checksum' => $this->hash(array_column($datasets, 'sha256', 'key')),
                'provenance' => [
                    'origin' => 'deterministic_generated_fixture',
                    'environment' => 'synthetic',
                    'profile_key' => $profile['key'],
                    'profile_hash' => $profileHash,
                    'production_data' => false,
                    'production_export' => false,
                    'production_scale_claimed' => false,
                ],
            ],
            'datasets' => $datasets,
        ];
        $manifestPath = $absoluteRoot.'/manifest.json';
        $manifestJson = $this->json($manifest)."\n";

        if (File::exists($manifestPath) && ! hash_equals(hash('sha256', (string) File::get($manifestPath)), hash('sha256', $manifestJson))) {
            throw new RuntimeException("Scale fixture run [{$runReference}] is bound to a different manifest.");
        }
        File::put($manifestPath, $manifestJson);

        return [
            'manifest_path' => $manifestPath,
            'artifact_root' => $artifactRoot,
            'profile_hash' => $profileHash,
            'profile' => $profile,
            'dataset_counts' => array_column($datasets, 'record_count', 'key'),
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return list<array<string, mixed>>
     */
    private function writeDatasets(string $root, array $profile): array
    {
        $counts = $profile['counts'];
        $owners = (int) $counts['business_owners'];
        $businesses = (int) $counts['businesses'];
        $applications = (int) $counts['business_permit_applications'];
        $schedules = (int) $counts['payment_schedules'];
        $payments = (int) $counts['payments'];
        $permits = (int) $counts['permits'];
        $clearances = (int) $counts['permit_clearances'];
        $linesPerApplication = (int) $profile['lines_per_application'];

        $definitions = [
            'business_owners' => [
                'entity_type' => 'business_owner',
                'count' => $owners,
                'references' => [],
                'row' => fn (int $index): array => [
                    '_id' => $this->id('owner', $index),
                    'firstName' => 'Synthetic',
                    'lastName' => sprintf('Owner %06d', $index),
                    'ownerType' => 'Individual',
                ],
            ],
            'businesses' => [
                'entity_type' => 'business',
                'count' => $businesses,
                'references' => [[
                    'field' => 'ownerId',
                    'target_dataset' => 'business_owners',
                    'required' => true,
                    'cardinality' => 'one',
                ]],
                'row' => fn (int $index): array => [
                    '_id' => $this->id('business', $index),
                    'ownerId' => $this->id('owner', $this->distributedIndex($index, $owners)),
                    'name' => sprintf('Synthetic Business %06d', $index),
                    'ownershipType' => 'Sole Proprietorship',
                ],
            ],
            'business_permit_applications' => [
                'entity_type' => 'business_permit_application',
                'count' => $applications,
                'references' => [
                    ['field' => 'businessOwnerId', 'target_dataset' => 'business_owners', 'required' => true, 'cardinality' => 'one'],
                    ['field' => 'businessId', 'target_dataset' => 'businesses', 'required' => true, 'cardinality' => 'one'],
                ],
                'row' => function (int $index) use ($owners, $businesses, $linesPerApplication): array {
                    $businessIndex = $this->distributedIndex($index, $businesses);
                    $status = ['Draft', 'Assessment', 'Approval', 'Pending Payment', 'Released'][($index - 1) % 5];
                    $payload = [
                        '_id' => $this->id('application', $index),
                        'businessOwnerId' => $this->id('owner', $this->distributedIndex($businessIndex, $owners)),
                        'businessId' => $this->id('business', $businessIndex),
                        'applicationNumber' => sprintf('SYN-BPA-%06d', $index),
                        'permitApplicationType' => ['New', 'Renewal', 'Additional'][($index - 1) % 3],
                        'status' => $status,
                        'linesOfBusiness' => $linesPerApplication === 0 ? [] : array_map(fn (int $line): array => [
                            'businessCategory' => sprintf('Synthetic Category %02d', (($index + $line) % 12) + 1),
                            'capitalInvestment' => (string) (100_000 + ($index * 100) + $line),
                            'grossSales' => (string) (200_000 + ($index * 100) + $line),
                        ], range(1, $linesPerApplication)),
                    ];
                    if ($status !== 'Draft') {
                        $payload['submittedAt'] = '2026-01-02T08:00:00+08:00';
                    }
                    if (in_array($status, ['Approval', 'Pending Payment', 'Released'], true)) {
                        $payload['assessedAt'] = '2026-01-03T08:00:00+08:00';
                    }
                    if ($status === 'Released') {
                        $payload['releasedAt'] = '2026-01-05T08:00:00+08:00';
                    }

                    return $payload;
                },
            ],
            'payment_schedules' => [
                'entity_type' => 'payment_schedule',
                'count' => $schedules,
                'references' => [[
                    'field' => 'applicationId',
                    'target_dataset' => 'business_permit_applications',
                    'required' => true,
                    'cardinality' => 'one',
                ]],
                'row' => fn (int $index): array => [
                    '_id' => $this->id('schedule', $index),
                    'applicationId' => $this->id('application', $this->distributedIndex($index, $applications)),
                    'sectionNumber' => 1,
                    'dueDate' => '2026-01-20',
                    'status' => 'pending',
                    'fees' => [[
                        'feeId' => 'synthetic-fee-inspection',
                        'feeName' => 'Synthetic Inspection Fee',
                        'feeCategory' => 'Fee',
                        'originalAmount' => '350.00',
                        'sectionAmount' => '350.00',
                    ]],
                    'surcharge' => '0.00',
                    'penalty' => '0.00',
                    'totalAmount' => '350.00',
                    'paidAmount' => '0.00',
                ],
            ],
            'payments' => [
                'entity_type' => 'payment',
                'count' => $payments,
                'references' => [
                    ['field' => 'applicationId', 'target_dataset' => 'business_permit_applications', 'required' => true, 'cardinality' => 'one'],
                    ['field' => 'scheduleId', 'target_dataset' => 'payment_schedules', 'required' => true, 'cardinality' => 'one'],
                ],
                'row' => function (int $index) use ($applications, $schedules): array {
                    $scheduleIndex = $this->distributedIndex($index, $schedules);

                    return [
                        '_id' => $this->id('payment', $index),
                        'applicationId' => $this->id('application', $this->distributedIndex($scheduleIndex, $applications)),
                        'scheduleId' => $this->id('schedule', $scheduleIndex),
                        'transactionNumber' => sprintf('SYN-TXN-%06d', $index),
                        'amount' => '100.00',
                        'paymentMethod' => 'Cash',
                        'status' => 'completed',
                        'paidAt' => '2026-01-10T08:00:00+08:00',
                        'processedBy' => 'synthetic-operator',
                    ];
                },
            ],
            'permit_clearances' => [
                'entity_type' => 'permit_clearance',
                'count' => $clearances,
                'references' => [[
                    'field' => 'applicationId',
                    'target_dataset' => 'business_permit_applications',
                    'required' => true,
                    'cardinality' => 'one',
                ]],
                'row' => fn (int $index): array => [
                    '_id' => $this->id('clearance', $index),
                    'applicationId' => $this->id('application', $this->distributedIndex($index, $applications)),
                    'clearanceTypeId' => sprintf('synthetic-clearance-type-%02d', (($index - 1) % 5) + 1),
                    'isCompleted' => false,
                    'assignedAt' => '2026-01-04T08:00:00+08:00',
                ],
            ],
            'permits' => [
                'entity_type' => 'permit',
                'count' => $permits,
                'references' => [[
                    'field' => 'applicationId',
                    'target_dataset' => 'business_permit_applications',
                    'required' => true,
                    'cardinality' => 'one',
                ]],
                'row' => fn (int $index): array => [
                    '_id' => $this->id('permit', $index),
                    'applicationId' => $this->id('application', $this->distributedIndex($index, $applications)),
                    'permitNumber' => sprintf('SYN-BP-%06d', $index),
                    'issuedBy' => 'synthetic-authority-placeholder',
                    'issuedAt' => '2026-01-05T08:00:00+08:00',
                    'dateReleased' => '2026-01-05T08:00:00+08:00',
                    'expiryDate' => '2026-12-31T23:59:59+08:00',
                    'status' => 'Active',
                ],
            ],
        ];

        $datasets = [];
        foreach ($definitions as $key => $definition) {
            $path = $root.'/'.$key.'.jsonl';
            $file = new SplFileObject($path, 'wb');
            for ($index = 1; $index <= $definition['count']; $index++) {
                $written = $file->fwrite(json_encode(($definition['row'])($index), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
                if ($written === false) {
                    throw new RuntimeException("Scale fixture dataset [{$key}] could not be written.");
                }
            }
            $checksum = hash_file('sha256', $path);
            if (! is_string($checksum)) {
                throw new RuntimeException("Scale fixture dataset [{$key}] could not be checksummed.");
            }
            $datasets[] = [
                'key' => $key,
                'entity_type' => $definition['entity_type'],
                'file' => $key.'.jsonl',
                'sha256' => $checksum,
                'record_count' => $definition['count'],
                'identity_field' => '_id',
                'references' => $definition['references'],
            ];
        }

        return $datasets;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array{
     *   schema_version: string,
     *   key: string,
     *   counts: array{business_owners: int, businesses: int, business_permit_applications: int, payment_schedules: int, payments: int, permit_clearances: int, permits: int},
     *   lines_per_application: int,
     *   evidence: array<string, mixed>
     * }
     */
    private function validatedProfile(array $profile): array
    {
        $counts = $profile['counts'] ?? null;
        if (! is_array($counts)) {
            throw new RuntimeException('Scale fixture profile counts are required.');
        }
        $limits = [
            'business_owners' => [1, 10_000],
            'businesses' => [1, 20_000],
            'business_permit_applications' => [1, 20_000],
            'payment_schedules' => [0, 20_000],
            'payments' => [0, 100_000],
            'permit_clearances' => [0, 50_000],
            'permits' => [0, 20_000],
        ];
        $normalizedCounts = [
            'business_owners' => $this->validatedCount($counts, 'business_owners', $limits['business_owners']),
            'businesses' => $this->validatedCount($counts, 'businesses', $limits['businesses']),
            'business_permit_applications' => $this->validatedCount($counts, 'business_permit_applications', $limits['business_permit_applications']),
            'payment_schedules' => $this->validatedCount($counts, 'payment_schedules', $limits['payment_schedules']),
            'payments' => $this->validatedCount($counts, 'payments', $limits['payments']),
            'permit_clearances' => $this->validatedCount($counts, 'permit_clearances', $limits['permit_clearances']),
            'permits' => $this->validatedCount($counts, 'permits', $limits['permits']),
        ];
        if ($normalizedCounts['payments'] > 0 && $normalizedCounts['payment_schedules'] === 0) {
            throw new RuntimeException('Scale fixture payments require at least one payment schedule.');
        }
        if (array_sum($normalizedCounts) > 150_000) {
            throw new RuntimeException('Scale fixture profiles are limited to 150,000 source records.');
        }
        $lines = $profile['lines_per_application'] ?? null;
        if (! is_int($lines) || $lines < 0 || $lines > 20) {
            throw new RuntimeException('Scale fixture lines_per_application must be an integer from 0 to 20.');
        }
        $key = $profile['key'] ?? null;
        if (! is_string($key) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $key) !== 1) {
            throw new RuntimeException('Scale fixture profile key must contain 3-100 safe characters.');
        }

        return [
            'schema_version' => self::SchemaVersion,
            'key' => $key,
            'counts' => $normalizedCounts,
            'lines_per_application' => $lines,
            'evidence' => [
                'source_id' => 'LIVE-APP-001',
                'observed_at' => '2026-08-16',
                'method' => 'authenticated_read_only_ui_aggregate_observation',
                'exact_observations' => $profile['evidence']['exact_observations'] ?? [],
                'synthetic_assumptions' => $profile['evidence']['synthetic_assumptions'] ?? [],
                'production_records_in_fixture' => false,
                'personal_data_in_fixture' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $counts
     * @param  array{0: int, 1: int}  $range
     */
    private function validatedCount(array $counts, string $key, array $range): int
    {
        [$minimum, $maximum] = $range;
        $value = $counts[$key] ?? null;
        if (! is_int($value) || $value < $minimum || $value > $maximum) {
            throw new RuntimeException("Scale fixture count [{$key}] must be an integer from {$minimum} to {$maximum}.");
        }

        return $value;
    }

    private function distributedIndex(int $index, int $total): int
    {
        return (($index - 1) % $total) + 1;
    }

    private function id(string $prefix, int $index): string
    {
        return sprintf('%s-%06d', $prefix, $index);
    }

    /** @param array<string, mixed> $value */
    private function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $value
     * @throws JsonException
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function assertEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy scale fixtures are restricted to local and testing environments.');
        }
    }

    private function assertRunReference(string $runReference): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,69}$/', $runReference) !== 1) {
            throw new RuntimeException('Scale fixture run reference must contain 3-70 safe characters.');
        }
    }
}
