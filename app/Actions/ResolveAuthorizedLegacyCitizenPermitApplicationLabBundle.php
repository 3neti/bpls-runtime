<?php

namespace App\Actions;

use App\StakeholderPreview\StakeholderPreviewSafety;
use JsonException;
use RuntimeException;

/** @phpstan-import-type LegacyLabSpecimen from ResolveLegacyCitizenPermitApplicationLabPool */
class ResolveAuthorizedLegacyCitizenPermitApplicationLabBundle
{
    private const string SchemaVersion = 'bpls.authorized-legacy-citizen-lab-pool.v1';

    public function __construct(private readonly StakeholderPreviewSafety $safety) {}

    /** @return list<LegacyLabSpecimen>|null */
    public function handle(): ?array
    {
        $encodedBundle = config('stakeholder_preview.legacy_lab_specimen_bundle');

        if (! is_string($encodedBundle) || trim($encodedBundle) === '') {
            return null;
        }

        if (! $this->safety->allowsAuthorizedLegacySpecimens()) {
            throw new RuntimeException('The authorized legacy laboratory bundle is configured outside its private review profile.');
        }

        $compressedBundle = base64_decode($encodedBundle, true);
        $decodedBundle = is_string($compressedBundle) ? gzdecode($compressedBundle) : false;

        if (! is_string($decodedBundle)) {
            throw new RuntimeException('The authorized legacy laboratory bundle cannot be decoded.');
        }

        try {
            $envelope = json_decode($decodedBundle, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The authorized legacy laboratory bundle contains invalid JSON.', 0, $exception);
        }

        if (! is_array($envelope)
            || ($envelope['schema_version'] ?? null) !== self::SchemaVersion
            || ($envelope['source_snapshot'] ?? null) !== ResolveLegacyCitizenPermitApplicationLabPool::SourceSnapshot
            || ($envelope['specimen_count'] ?? null) !== count(ResolveLegacyCitizenPermitApplicationLabPool::SourceBusinessCategories)) {
            throw new RuntimeException('The authorized legacy laboratory bundle has an unsupported identity or specimen count.');
        }

        $specimens = $envelope['specimens'] ?? null;

        if (! is_array($specimens) || ! array_is_list($specimens)) {
            throw new RuntimeException('The authorized legacy laboratory bundle specimens must be a list.');
        }

        $poolHash = $this->fingerprint($specimens);
        $configuredHash = config('stakeholder_preview.legacy_lab_specimen_pool_sha256');

        if (($envelope['source_pool_sha256'] ?? null) !== $poolHash
            || ! is_string($configuredHash)
            || ! hash_equals($configuredHash, $poolHash)) {
            throw new RuntimeException('The authorized legacy laboratory bundle does not match its approved source-pool fingerprint.');
        }

        $categories = collect($specimens)->pluck('source_business_category')->all();

        if ($categories !== ResolveLegacyCitizenPermitApplicationLabPool::SourceBusinessCategories) {
            throw new RuntimeException('The authorized legacy laboratory bundle does not preserve the approved local specimen order.');
        }

        return array_map(fn (mixed $specimen): array => $this->validatedSpecimen($specimen), $specimens);
    }

    /** @param array<mixed> $value */
    public function fingerprint(array $value): string
    {
        return hash('sha256', json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        ));
    }

    /** @param list<LegacyLabSpecimen> $specimens */
    public function encode(array $specimens): string
    {
        $encoded = json_encode([
            'schema_version' => self::SchemaVersion,
            'source_snapshot' => ResolveLegacyCitizenPermitApplicationLabPool::SourceSnapshot,
            'specimen_count' => count($specimens),
            'source_pool_sha256' => $this->fingerprint($specimens),
            'specimens' => $specimens,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);

        $compressed = gzencode($encoded, 9);

        if (! is_string($compressed)) {
            throw new RuntimeException('The authorized legacy laboratory bundle could not be compressed.');
        }

        return base64_encode($compressed);
    }

    /** @return LegacyLabSpecimen */
    private function validatedSpecimen(mixed $specimen): array
    {
        if (! is_array($specimen) || array_is_list($specimen)) {
            throw new RuntimeException('The authorized legacy laboratory bundle contains an invalid specimen.');
        }

        $historicalAssessment = $specimen['historical_assessment'] ?? null;
        $fields = $specimen['fields'] ?? null;
        $activity = $specimen['activity'] ?? null;

        if (! is_array($historicalAssessment)
            || array_is_list($historicalAssessment)
            || ! is_array($fields)
            || array_is_list($fields)
            || ! is_array($activity)
            || array_is_list($activity)) {
            throw new RuntimeException('The authorized legacy laboratory bundle contains an incomplete specimen.');
        }

        $validatedFields = [];

        foreach ($fields as $key => $value) {
            if (! is_string($key)
                || ! in_array($key, BuildCitizenPermitApplicationLabFixture::AllowedFields, true)
                || (! is_scalar($value) && $value !== null)) {
                throw new RuntimeException('The authorized legacy laboratory bundle contains an invalid form field.');
            }

            $validatedFields[$key] = $value;
        }

        $quantity = $activity['quantity'] ?? null;
        $startedOn = $activity['started_on'] ?? null;

        if (($specimen['classification'] ?? null) !== 'authorized_legacy_source_lab_only'
            || ($specimen['source_kind'] ?? null) !== 'immutable_production_backup'
            || ($activity['line_of_business_code'] ?? null) !== ResolveLegacyCitizenPermitApplicationLabPool::CatalogCode
            || ! is_int($quantity)
            || $quantity < 1
            || (! is_string($startedOn) && $startedOn !== null)) {
            throw new RuntimeException('The authorized legacy laboratory bundle contains an invalid activity.');
        }

        return [
            'fixture_id' => $this->requiredString($specimen, 'fixture_id'),
            'label' => $this->requiredString($specimen, 'label'),
            'classification' => $this->requiredString($specimen, 'classification'),
            'source_kind' => $this->requiredString($specimen, 'source_kind'),
            'source_reference' => $this->requiredString($specimen, 'source_reference'),
            'source_business_category' => $this->requiredString($specimen, 'source_business_category'),
            'source_note' => $this->requiredString($specimen, 'source_note'),
            'historical_assessment' => $historicalAssessment,
            'fields' => $validatedFields,
            'activity' => [
                'line_of_business_code' => $this->requiredString($activity, 'line_of_business_code'),
                'quantity' => $quantity,
                'capital_investment_pesos' => $this->requiredString($activity, 'capital_investment_pesos'),
                'essential_gross_sales_pesos' => $this->requiredString($activity, 'essential_gross_sales_pesos'),
                'non_essential_gross_sales_pesos' => $this->requiredString($activity, 'non_essential_gross_sales_pesos'),
                'started_on' => $startedOn,
            ],
        ];
    }

    /** @param array<mixed> $values */
    private function requiredString(array $values, string $key): string
    {
        $value = $values[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("The authorized legacy laboratory bundle requires [{$key}].");
        }

        return $value;
    }
}
