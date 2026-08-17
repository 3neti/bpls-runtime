<?php

namespace App\Actions;

use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Models\LegacyRecord;
use App\Models\PermitApplication;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

class LegacyPermitApplicationProjector
{
    /**
     * @return array{
     *   attributes: array<string, mixed>,
     *   identity: array<string, mixed>,
     *   owner_legacy_id: string|null,
     *   business_legacy_id: string|null,
     *   source_application_number: string|null,
     *   reasons: list<string>,
     *   blocked: bool
     * }
     */
    public function project(LegacyRecord $record): array
    {
        $payload = $record->payload;
        $ownerLegacyId = $this->stringOrNull($payload, 'businessOwnerId');
        $businessLegacyId = $this->stringOrNull($payload, 'businessId');
        $applicationNumber = $this->stringOrNull($payload, 'applicationNumber');
        $sourceStatus = $this->string($payload, 'status');
        $sourceType = $this->stringOrNull($payload, 'permitApplicationType');
        $submittedAt = $this->date($payload, 'submittedAt');
        $assessedAt = $this->date($payload, 'assessedAt');
        $reasons = [];
        $blocked = false;

        foreach ([
            'required_application_owner_reference_missing' => $ownerLegacyId,
            'required_application_business_reference_missing' => $businessLegacyId,
            'required_legacy_application_number_missing' => $applicationNumber,
        ] as $reason => $value) {
            if ($value === null) {
                $reasons[] = $reason;
                $blocked = true;
            }
        }

        [$status, $statusReasons, $statusBlocked] = $this->status($sourceStatus, $submittedAt !== null, $assessedAt !== null);
        [$type, $typeReasons, $typeBlocked] = $this->type($sourceType);
        $reasons = [...$reasons, ...$statusReasons, ...$typeReasons];
        $blocked = $blocked || $statusBlocked || $typeBlocked;

        if ($this->string($payload, 'submittedAt') !== '' && $submittedAt === null) {
            $reasons[] = 'invalid_submitted_timestamp';
            $blocked = true;
        }

        if ($this->string($payload, 'assessedAt') !== '' && $assessedAt === null) {
            $reasons[] = 'invalid_assessed_timestamp';
            $blocked = true;
        }

        $lines = $payload['linesOfBusiness'] ?? [];

        if (! is_array($lines)) {
            $reasons[] = 'invalid_lines_of_business_shape';
            $blocked = true;
            $lines = [];
        } elseif ($lines !== []) {
            $reasons[] = 'line_of_business_mapping_required';
        }

        if (is_array($payload['feeOverrides'] ?? null) && $payload['feeOverrides'] !== []) {
            $reasons[] = 'financial_override_reconciliation_required';
        }

        if (($payload['isDeleted'] ?? false) === true) {
            $reasons[] = 'soft_deleted_application_policy_unresolved';
        }

        if ($this->string($payload, 'rejectionReason') !== '' || $this->string($payload, 'rejectedAt') !== '') {
            $reasons[] = 'legacy_rejection_state_not_represented';
        }

        $applicationYear = $submittedAt?->year;

        if ($applicationYear === null) {
            $reasons[] = 'application_year_source_missing';
            $blocked = true;
        }

        $attributes = [
            'business_id' => null,
            'submitted_by_id' => null,
            'application_number' => null,
            'type' => $type?->value,
            'status' => $status?->value,
            'application_year' => $applicationYear,
            'submitted_at' => $status === PermitApplicationStatus::Draft ? null : $submittedAt?->toIso8601String(),
            'assessed_at' => $assessedAt?->toIso8601String(),
            'legacy_source_id' => $record->legacy_id,
            'metadata' => [
                'legacy_application_number' => $applicationNumber,
                'legacy_status' => $sourceStatus !== '' ? $sourceStatus : null,
                'legacy_mode_of_payment' => $this->stringOrNull($payload, 'modeOfPayment'),
                'legacy_approval_evidence' => array_filter([
                    'approved_at' => $this->stringOrNull($payload, 'approvedAt'),
                    'approved_by' => $this->stringOrNull($payload, 'approvedBy'),
                ]),
                'legacy_release_evidence' => array_filter([
                    'released_at' => $this->stringOrNull($payload, 'releasedAt'),
                    'status_reverted_at' => $this->stringOrNull($payload, 'statusRevertedAt'),
                    'status_revert_reason' => $this->stringOrNull($payload, 'statusRevertReason'),
                ]),
                'legacy_total_fees' => $payload['totalFees'] ?? null,
                'legacy_line_count' => count($lines),
                'official_application_number_authority' => 'unresolved',
            ],
        ];

        return [
            'attributes' => $attributes,
            'identity' => [
                'legacy_application_id' => $record->legacy_id,
                'legacy_application_number' => $this->normalize($applicationNumber ?? ''),
                'business_legacy_id' => $businessLegacyId,
            ],
            'owner_legacy_id' => $ownerLegacyId,
            'business_legacy_id' => $businessLegacyId,
            'source_application_number' => $applicationNumber,
            'reasons' => array_values(array_unique($reasons)),
            'blocked' => $blocked,
        ];
    }

    /**
     * Project an exact legacy Released assertion without materializing current release authority.
     *
     * @return array{
     *   attributes: array<string, mixed>,
     *   identity: array<string, mixed>,
     *   owner_legacy_id: string|null,
     *   business_legacy_id: string|null,
     *   source_application_number: string|null,
     *   reasons: list<string>,
     *   blocked: bool
     * }
     */
    public function projectHistoricalEvidence(LegacyRecord $record): array
    {
        $projection = $this->project($record);

        if (data_get($projection, 'attributes.metadata.legacy_status') !== 'Released') {
            return [
                ...$projection,
                'reasons' => [...$projection['reasons'], 'historical_release_projection_requires_released_source_status'],
                'blocked' => true,
            ];
        }

        $metadata = $projection['attributes']['metadata'];
        $sourceDeclarations = $record->payload['linesOfBusiness'] ?? null;
        $sourceDeclarations = is_array($sourceDeclarations) ? $sourceDeclarations : [];
        $metadata['historical_semantics'] = [
            'schema_version' => 'bpls.legacy-application-historical-semantics.v1',
            'source_status' => 'Released',
            'source_status_confidence' => 'exact',
            'semantic_disposition' => 'historical_only',
            'release_authority_provenance' => 'unresolved',
            'current_release_authorized' => false,
            'current_legal_effect_verified' => false,
            'current_permit_validity_verified' => false,
            'operationally_eligible' => false,
            'source_application_payload_hash' => $record->payload_hash,
            'source_declarations' => collect($sourceDeclarations)
                ->filter(fn (mixed $line): bool => is_array($line))
                ->values()
                ->map(fn (array $line, int $index): array => [
                    'source_index' => $index,
                    'source_fact_sha256' => $this->hashCanonical($line),
                    'semantic_disposition' => 'historical_only',
                    'current_line_of_business_identity' => 'unresolved',
                ])
                ->all(),
        ];
        $metadata['terminal_state'] = [
            'status' => PermitApplicationStatus::HistoricalEvidence->value,
            'is_terminal' => true,
            'can_continue' => false,
            'reason' => 'Exact legacy lifecycle assertion preserved as non-operational historical evidence.',
        ];
        $projection['attributes']['status'] = PermitApplicationStatus::HistoricalEvidence->value;
        $projection['attributes']['metadata'] = $metadata;
        $projection['reasons'] = array_values(array_filter(
            $projection['reasons'],
            fn (string $reason): bool => $reason !== 'legacy_release_authority_unresolved',
        ));

        return $projection;
    }

    /** @param array<string, mixed> $value */
    public function hashCanonical(array $value): string
    {
        return hash('sha256', $this->canonicalJson($value));
    }

    public function targetSnapshotHash(PermitApplication $application): string
    {
        return $this->hashCanonical([
            'id' => $application->id,
            'business_id' => $application->business_id,
            'submitted_by_id' => $application->submitted_by_id,
            'application_number' => $application->application_number,
            'type' => $application->type->value,
            'status' => $application->status->value,
            'application_year' => $application->application_year,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'assessed_at' => $application->assessed_at?->toIso8601String(),
            'legacy_source_id' => $application->legacy_source_id,
            'metadata' => $application->metadata,
            'created_at' => $application->created_at?->toIso8601String(),
            'updated_at' => $application->updated_at?->toIso8601String(),
        ]);
    }

    /** @return array{PermitApplicationStatus|null, list<string>, bool} */
    private function status(string $sourceStatus, bool $hasSubmittedAt, bool $hasAssessedAt): array
    {
        return match ($sourceStatus) {
            'Draft' => [PermitApplicationStatus::Draft, $hasSubmittedAt ? ['legacy_draft_submission_timestamp_conflict'] : [], false],
            'Assessment' => [PermitApplicationStatus::Assessment, $hasSubmittedAt ? [] : ['submitted_timestamp_required_for_processing_state'], ! $hasSubmittedAt],
            'Approval' => [PermitApplicationStatus::Approval, ['assessment_snapshot_migration_required'], ! $hasAssessedAt],
            'Pending Payment' => [PermitApplicationStatus::PendingPayment, ['assessment_and_payment_schedule_migration_required'], false],
            'Released' => [PermitApplicationStatus::Released, ['legacy_release_authority_unresolved'], false],
            default => [null, ['unsupported_legacy_application_status'], true],
        };
    }

    /** @return array{PermitApplicationType|null, list<string>, bool} */
    private function type(?string $sourceType): array
    {
        return match ($sourceType) {
            'New' => [PermitApplicationType::New, [], false],
            'Renewal' => [PermitApplicationType::Renewal, [], false],
            'Additional' => [PermitApplicationType::Additional, [], false],
            null => [PermitApplicationType::New, ['legacy_missing_type_default_requires_acceptance'], false],
            default => [null, ['unsupported_legacy_application_type'], true],
        };
    }

    /** @param array<string, mixed> $payload */
    private function date(array $payload, string $key): ?CarbonImmutable
    {
        $value = $this->string($payload, $key);

        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->squish()->lower()->toString();
    }

    /** @param array<string, mixed> $payload */
    private function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) || is_int($value) ? Str::of((string) $value)->trim()->toString() : '';
    }

    /** @param array<string, mixed> $payload */
    private function stringOrNull(array $payload, string $key): ?string
    {
        $value = $this->string($payload, $key);

        return $value === '' ? null : $value;
    }

    /** @param array<array-key, mixed> $value */
    private function canonicalJson(array $value): string
    {
        return json_encode($this->sortRecursively($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function sortRecursively(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursively($item);
            }
        }

        return $value;
    }
}
