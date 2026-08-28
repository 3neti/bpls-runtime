<?php

namespace App\Enums;

use App\Models\FeeRule;

enum FeeRulePublicationSource: string
{
    case AcceptedMunicipalAuthority = 'accepted_municipal_authority';
    case MunicipalConfirmationRequired = 'municipal_confirmation_required';
    case Synthetic = 'synthetic';
    case ProvisionalUat = 'provisional_uat';
    case Historical = 'historical';
    case Mock = 'mock';
    case LegacyEvidenceOnly = 'legacy_evidence_only';
    case LifecycleTest = 'lifecycle_test';
    case Unclassified = 'unclassified';

    public static function forRule(FeeRule $feeRule): self
    {
        $semanticClassification = data_get($feeRule->metadata, 'semantic_classification');

        if ($semanticClassification !== null) {
            if (! is_string($semanticClassification)) {
                return self::Unclassified;
            }

            return match ($semanticClassification) {
                'synthetic', 'synthetic_only' => self::Synthetic,
                'provisional', 'provisional_uat', 'uat' => self::ProvisionalUat,
                'historical', 'historical_evidence' => self::Historical,
                'mock' => self::Mock,
                'evidence_only', 'legacy_evidence_only' => self::LegacyEvidenceOnly,
                'lifecycle', 'lifecycle_test', 'scenario', 'scenario_only', 'test' => self::LifecycleTest,
                default => self::Unclassified,
            };
        }

        $classifiedSource = data_get($feeRule->metadata, 'price_list_source_classification');

        if (! is_string($classifiedSource)) {
            return self::Unclassified;
        }

        return self::tryFrom($classifiedSource) ?? self::Unclassified;
    }

    public function mayPublishExactAmount(): bool
    {
        return $this === self::AcceptedMunicipalAuthority;
    }
}
