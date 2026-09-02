<?php

namespace App\Assessment;

use App\Enums\AssessmentComponentScope;
use App\Enums\AssessmentComponentType;
use InvalidArgumentException;

final readonly class AssessmentComponent
{
    /**
     * @param  list<string>  $percentageBaseKeys
     * @param  array<string, mixed>  $explanationSnapshot
     */
    public function __construct(
        public string $key,
        public AssessmentComponentType $type,
        public AssessmentComponentScope $scope,
        public ?int $permitApplicationLineId,
        public ?int $lineOfBusinessId,
        public string $sourceType,
        public string $sourceId,
        public string $exactOnceKey,
        public ?string $responsibleOffice,
        public string $policyVersion,
        public int $amountCents,
        public int $orderingPhase,
        public array $percentageBaseKeys,
        public string $roundingInstruction,
        public array $explanationSnapshot,
        public ?int $recordedById = null,
        public ?string $recordedAt = null,
    ) {
        foreach (['key', 'sourceType', 'sourceId', 'exactOnceKey', 'policyVersion', 'roundingInstruction'] as $property) {
            if (trim($this->{$property}) === '') {
                throw new InvalidArgumentException("Assessment component {$property} must not be empty.");
            }
        }

        if ($this->exactOnceKey !== "{$this->sourceType}:{$this->sourceId}") {
            throw new InvalidArgumentException('Assessment component exact-once key must be the stable source type and source identity.');
        }

        if ($this->scope === AssessmentComponentScope::Application
            && ($this->permitApplicationLineId !== null || $this->lineOfBusinessId !== null)) {
            throw new InvalidArgumentException('An application-scoped component cannot identify a Line of Business.');
        }

        if ($this->scope === AssessmentComponentScope::LineOfBusiness
            && ($this->permitApplicationLineId === null || $this->lineOfBusinessId === null)) {
            throw new InvalidArgumentException('A Line-of-Business component requires the exact application line and Line of Business.');
        }

        if ($this->type === AssessmentComponentType::PaperlessPaymentOrder && blank($this->responsibleOffice)) {
            throw new InvalidArgumentException('A Paperless Payment Order component requires its responsible office.');
        }

        if ($this->orderingPhase < 0) {
            throw new InvalidArgumentException('Assessment component ordering phase must be non-negative.');
        }

        if ($this->explanationSnapshot === []) {
            throw new InvalidArgumentException('Assessment component explanation snapshot must not be empty.');
        }
    }

    /** @return array<string, mixed> */
    public function immutableProjection(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type->value,
            'currency' => 'PHP',
            'scope' => $this->scope->value,
            'permit_application_line_id' => $this->permitApplicationLineId,
            'line_of_business_id' => $this->lineOfBusinessId,
            'source' => [
                'type' => $this->sourceType,
                'id' => $this->sourceId,
                'exact_once_key' => $this->exactOnceKey,
            ],
            'responsible_office' => $this->responsibleOffice,
            'policy_version' => $this->policyVersion,
            'signed_amount_cents' => $this->amountCents,
            'ordering_phase' => $this->orderingPhase,
            'percentage_base_keys' => $this->percentageBaseKeys,
            'rounding_instruction' => $this->roundingInstruction,
            'recorded_by_id' => $this->recordedById,
            'recorded_at' => $this->recordedAt,
            'explanation_snapshot' => $this->explanationSnapshot,
        ];
    }
}
