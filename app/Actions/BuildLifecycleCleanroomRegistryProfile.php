<?php

namespace App\Actions;

use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use Symfony\Component\Yaml\Yaml;
use UnexpectedValueException;

class BuildLifecycleCleanroomRegistryProfile
{
    public function __construct(
        private readonly BuildLaboratoryAssessmentReconciliation $reconciliation,
    ) {}

    /**
     * @return array{
     *     kind: string,
     *     profile_version: string,
     *     scope: string,
     *     source_reference: string,
     *     expected_total_amount_cents: int,
     *     responsibilities: list<array<string, mixed>>,
     *     statement: string
     * }|null
     */
    public function handle(PermitApplication $application): ?array
    {
        $metadata = data_get($application->metadata, 'laboratory_assessment_reconciliation');
        if (! is_array($metadata)) {
            return null;
        }
        if (($metadata['schema_version'] ?? null) !== 'bpls.laboratory-assessment-reconciliation.v1'
            || ($metadata['semantic_classification'] ?? null) !== 'observational_legacy_financial_evidence'
            || ($metadata['operational_authority'] ?? null) !== false
            || ($metadata['production_liability'] ?? null) !== false) {
            throw new UnexpectedValueException('The registry specimen does not carry the guarded laboratory reconciliation contract.');
        }
        $sourceReference = $metadata['source_reference'] ?? null;
        if (! is_string($sourceReference) || trim($sourceReference) === '') {
            throw new UnexpectedValueException('The registry specimen has no exact source application reference.');
        }

        $historicalAssessment = $metadata['historical_assessment'] ?? null;
        if (! $this->reconciliation->sourceEvidenceValid($historicalAssessment) || ! is_array($historicalAssessment)) {
            throw new UnexpectedValueException('The registry specimen assessment evidence failed its integrity check.');
        }
        if (($historicalAssessment['source_internal_reconciles'] ?? null) !== true) {
            throw new UnexpectedValueException('The registry specimen source total does not reconcile to its preserved components.');
        }

        $configuration = $this->configuration();
        $sourceCategory = $metadata['source_business_category'] ?? null;
        if (! is_string($sourceCategory) || ! in_array($sourceCategory, $configuration['source_business_categories'], true)) {
            throw new UnexpectedValueException('The registry specimen business category has no laboratory assessment profile.');
        }

        $application->loadMissing('lines.lineOfBusiness');
        $lines = $application->lines->filter(
            fn (PermitApplicationLine $line): bool => in_array($line->lineOfBusiness?->code, $configuration['line_of_business_codes'], true),
        )->values();
        if ($lines->count() !== 1) {
            throw new UnexpectedValueException('The registry specimen assessment profile requires one explicitly translated municipal activity.');
        }
        $applicationLine = $lines->sole();
        $responsibilities = [];

        foreach ($historicalAssessment['schedules'] as $scheduleIndex => $schedule) {
            if (! is_array($schedule)
                || ! is_int($schedule['surcharge_amount_cents'] ?? null)
                || ! is_int($schedule['penalty_amount_cents'] ?? null)
                || $schedule['surcharge_amount_cents'] !== 0
                || $schedule['penalty_amount_cents'] !== 0
                || ! is_array($schedule['fees'] ?? null)) {
                throw new UnexpectedValueException('The registry specimen requires an uncommissioned surcharge, penalty, or malformed schedule profile.');
            }

            foreach ($schedule['fees'] as $feeIndex => $fee) {
                if (! is_array($fee)
                    || ! is_string($fee['name'] ?? null)
                    || ! is_string($fee['category'] ?? null)
                    || ! is_int($fee['amount_cents'] ?? null)
                    || $fee['amount_cents'] < 0) {
                    throw new UnexpectedValueException('The registry specimen contains an incomplete source fee component.');
                }
                $mapping = $configuration['component_mappings'][$fee['name']] ?? null;
                if (! is_array($mapping)) {
                    throw new UnexpectedValueException("The source fee component [{$fee['name']}] has no explicit laboratory office mapping.");
                }

                $componentHash = hash('sha256', implode('|', [
                    $sourceReference,
                    (string) ($schedule['section'] ?? $scheduleIndex + 1),
                    (string) $feeIndex,
                    $fee['name'],
                    $fee['category'],
                    (string) $fee['amount_cents'],
                ]));
                $responsibilities[] = [
                    'key' => 'registry-source.'.substr($componentHash, 0, 24),
                    'department' => $mapping['office'],
                    'line_of_business_code' => $applicationLine->lineOfBusiness?->code,
                    'code' => 'LAB-SOURCE-'.strtoupper(substr($componentHash, 0, 12)),
                    'label' => $fee['name'],
                    'amount_cents' => $fee['amount_cents'],
                    'inspection_required' => $mapping['inspection_required'],
                    'applicability' => 'applicable',
                    'classification' => 'provisional_uat',
                    'reason' => 'Confirm this source-backed provisional laboratory component against the lodged facts. Its legacy label and amount are observational evidence, not commissioned current fee-policy identity.',
                    'provenance' => 'Checksum-bound component replayed from the authorized immutable Ipil backup for laboratory reconciliation only.',
                    'source_component' => [
                        'source_evidence_hash' => $historicalAssessment['source_evidence_hash'],
                        'schedule_section' => $schedule['section'] ?? $scheduleIndex + 1,
                        'fee_index' => $feeIndex,
                        'legacy_name' => $fee['name'],
                        'legacy_category' => $fee['category'],
                        'amount_cents' => $fee['amount_cents'],
                    ],
                ];
            }
        }

        if ($responsibilities === []) {
            throw new UnexpectedValueException('The registry specimen contains no source fee components to review.');
        }

        return [
            'kind' => 'registry_source_replay',
            'profile_version' => $configuration['profile_version'],
            'scope' => $configuration['scope'],
            'source_reference' => $sourceReference,
            'expected_total_amount_cents' => $historicalAssessment['recorded_total_amount_cents'],
            'responsibilities' => $responsibilities,
            'statement' => 'The profile replays checksum-bound source components through the canonical Evaluation, Paperless Payment Order, and Assessment actions. It is an audit rehearsal, not an independent current-policy calculation or production liability.',
        ];
    }

    /** @return array<string, mixed> */
    private function configuration(): array
    {
        $path = config('bplo.routing_sentinel.assessment_profile_path');
        if (! is_string($path) || ! is_file($path)) {
            throw new UnexpectedValueException('The registry specimen laboratory assessment profile is unavailable.');
        }

        $configuration = Yaml::parseFile($path, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        if (! is_array($configuration)
            || ($configuration['schema_version'] ?? null) !== 1
            || ($configuration['classification'] ?? null) !== 'provisional_uat_source_replay'
            || ($configuration['production_authority'] ?? null) !== false
            || ($configuration['scope'] ?? null) !== 'single_source_application'
            || ! is_string($configuration['profile_version'] ?? null)
            || ! $this->stringList($configuration['source_business_categories'] ?? null)
            || ! $this->stringList($configuration['line_of_business_codes'] ?? null)
            || ! is_array($configuration['component_mappings'] ?? null)
            || $configuration['component_mappings'] === []) {
            throw new UnexpectedValueException('The registry specimen laboratory assessment profile contract is invalid.');
        }

        foreach ($configuration['component_mappings'] as $name => $mapping) {
            if (! is_string($name)
                || trim($name) === ''
                || ! is_array($mapping)
                || ! in_array($mapping['office'] ?? null, ['assessor', 'engineering', 'health', 'menro'], true)
                || ! is_bool($mapping['inspection_required'] ?? null)) {
                throw new UnexpectedValueException('A registry specimen laboratory component mapping is invalid.');
            }
        }

        return $configuration;
    }

    private function stringList(mixed $value): bool
    {
        return is_array($value)
            && array_is_list($value)
            && $value !== []
            && collect($value)->every(fn (mixed $item): bool => is_string($item) && trim($item) !== '');
    }
}
