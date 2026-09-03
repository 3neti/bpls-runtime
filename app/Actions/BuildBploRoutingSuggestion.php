<?php

namespace App\Actions;

use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use JsonException;
use Symfony\Component\Yaml\Yaml;
use UnexpectedValueException;

class BuildBploRoutingSuggestion
{
    /**
     * @return array{
     *     profile_version: string,
     *     profile_keys: list<string>,
     *     situational_context: string,
     *     suggested_work: list<array{office_code: string, office_label: string, situational_reason: string, required_work: string, permit_application_line_id: int}>,
     *     application_facts_snapshot: array<string, mixed>
     * }|null
     */
    public function handle(PermitApplication $permitApplication): ?array
    {
        $application = $permitApplication->loadMissing('lines.lineOfBusiness');
        $configuration = $this->configuration();
        $sourceCategory = data_get($application->metadata, 'laboratory_assessment_reconciliation.source_business_category');
        $sourceCategory = is_string($sourceCategory) && $sourceCategory !== '' ? $sourceCategory : null;
        $matchedProfiles = collect($configuration['profiles'])
            ->filter(function (array $profile) use ($application, $sourceCategory): bool {
                if ($sourceCategory !== null) {
                    return in_array($sourceCategory, $profile['source_business_categories'], true);
                }

                return $application->lines->contains(
                    fn (PermitApplicationLine $line): bool => in_array($line->lineOfBusiness?->code, $profile['line_of_business_codes'], true),
                );
            });

        if ($matchedProfiles->isEmpty()) {
            return null;
        }

        $suggestedWork = $matchedProfiles->flatMap(function (array $profile) use ($application): array {
            return $application->lines
                ->filter(fn (PermitApplicationLine $line): bool => in_array($line->lineOfBusiness?->code, $profile['line_of_business_codes'], true))
                ->flatMap(fn (PermitApplicationLine $line): array => array_map(fn (array $office): array => [
                    'office_code' => $office['code'],
                    'office_label' => $office['label'],
                    'situational_reason' => $office['reason'],
                    'required_work' => $office['required_work'],
                    'permit_application_line_id' => $line->id,
                ], $profile['offices']))
                ->all();
        })->unique(fn (array $work): string => $work['office_code'].'|'.$work['permit_application_line_id'])->values();

        if ($suggestedWork->isEmpty()) {
            return null;
        }

        $facts = [
            'permit_application_id' => $application->id,
            'application_type' => $application->type->value,
            'application_year' => $application->application_year,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'source_business_category' => $sourceCategory,
            'declared_lines' => $application->lines->sortBy('id')->map(fn (PermitApplicationLine $line): array => [
                'permit_application_line_id' => $line->id,
                'line_of_business_id' => $line->line_of_business_id,
                'line_of_business_code' => $line->lineOfBusiness?->code,
                'declared_gross_sales_cents' => $line->declared_gross_sales_cents,
                'capital_investment_cents' => $line->capital_investment_cents,
            ])->values()->all(),
        ];
        $facts['facts_hash'] = $this->hash($facts);

        return [
            'profile_version' => $configuration['profile_version'],
            'profile_keys' => array_values($matchedProfiles->keys()->all()),
            'situational_context' => 'Provisional laboratory routing suggested from the lodged Application facts. BPLO may confirm or change it before the review deadline.',
            'suggested_work' => array_values($suggestedWork->all()),
            'application_facts_snapshot' => [
                ...$facts,
                'semantic_classification' => 'provisional_uat',
                'production_authority' => false,
                'suggestion_is_determination' => false,
            ],
        ];
    }

    /** @return array{profile_version: string, profiles: array<string, array{source_business_categories: list<string>, line_of_business_codes: list<string>, offices: list<array{code: string, label: string, reason: string, required_work: string}>}>} */
    private function configuration(): array
    {
        $path = config('bplo.routing_sentinel.profile_path');
        if (! is_string($path) || ! is_file($path)) {
            throw new UnexpectedValueException('The BPLO routing suggestion profile is unavailable.');
        }

        $configuration = Yaml::parseFile($path, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        if (! is_array($configuration)
            || ($configuration['schema_version'] ?? null) !== 1
            || ($configuration['classification'] ?? null) !== 'provisional_uat'
            || ($configuration['production_authority'] ?? null) !== false
            || ! is_string($configuration['profile_version'] ?? null)
            || ! is_array($configuration['profiles'] ?? null)) {
            throw new UnexpectedValueException('The BPLO routing suggestion profile contract is invalid.');
        }

        foreach ($configuration['profiles'] as $key => $profile) {
            if (! is_string($key)
                || ! is_array($profile)
                || ! $this->stringList($profile['source_business_categories'] ?? null)
                || ! $this->stringList($profile['line_of_business_codes'] ?? null, false)
                || ! is_array($profile['offices'] ?? null)
                || $profile['offices'] === []) {
                throw new UnexpectedValueException('A BPLO routing suggestion profile entry is invalid.');
            }

            foreach ($profile['offices'] as $office) {
                if (! is_array($office)
                    || collect(['code', 'label', 'reason', 'required_work'])->contains(
                        fn (string $field): bool => ! is_string($office[$field] ?? null) || trim($office[$field]) === '',
                    )) {
                    throw new UnexpectedValueException('A BPLO routing suggestion office entry is invalid.');
                }
            }
        }

        /** @var array{profile_version: string, profiles: array<string, array{source_business_categories: list<string>, line_of_business_codes: list<string>, offices: list<array{code: string, label: string, reason: string, required_work: string}>}>} $configuration */
        return $configuration;
    }

    private function stringList(mixed $value, bool $allowEmpty = true): bool
    {
        return is_array($value)
            && array_is_list($value)
            && ($allowEmpty || $value !== [])
            && collect($value)->every(fn (mixed $item): bool => is_string($item) && trim($item) !== '');
    }

    /** @param array<string, mixed> $facts */
    private function hash(array $facts): string
    {
        try {
            return hash('sha256', json_encode($facts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new UnexpectedValueException('The BPLO routing suggestion facts could not be fingerprinted.', previous: $exception);
        }
    }
}
