<?php

namespace App\Enums;

enum MunicipalServiceOfferingCode: string
{
    case NewBusinessPermit = 'new_business_permit';
    case Renewal = 'renewal';
    case Amendment = 'amendment';
    case Transfer = 'transfer';
    case RetirementClosure = 'retirement_closure';

    public function title(): string
    {
        return match ($this) {
            self::NewBusinessPermit => 'New Business Permit',
            self::Renewal => 'Renewal',
            self::Amendment => 'Amendment',
            self::Transfer => 'Transfer',
            self::RetirementClosure => 'Retirement / Closure',
        };
    }

    public function availability(): string
    {
        return match ($this) {
            self::NewBusinessPermit => 'available_online',
            default => 'staff_assisted_being_completed',
        };
    }

    public function availabilityLabel(): string
    {
        return match ($this) {
            self::NewBusinessPermit => 'Available online',
            default => 'Staff-assisted / being completed',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NewBusinessPermit => 'Apply for a permit for a business that is starting operations in the municipality.',
            self::Renewal => 'Continue an existing business permit for a new application year with municipal staff assistance.',
            self::Amendment => 'Request a change to information recorded for an existing business permit with municipal staff assistance.',
            self::Transfer => 'Request a business ownership or location transfer with municipal staff assistance.',
            self::RetirementClosure => 'Request the retirement or closure of a business record with municipal staff assistance.',
        };
    }

    public function applicationType(): PermitApplicationType
    {
        return match ($this) {
            self::NewBusinessPermit => PermitApplicationType::New,
            self::Renewal => PermitApplicationType::Renewal,
            self::Amendment => PermitApplicationType::Amendment,
            self::Transfer => PermitApplicationType::Transfer,
            self::RetirementClosure => PermitApplicationType::Retirement,
        };
    }

    public function startRouteName(): ?string
    {
        return $this === self::NewBusinessPermit
            ? 'citizen.permit-applications.create'
            : null;
    }

    public function publishesConfirmedAssessmentRules(): bool
    {
        return $this === self::NewBusinessPermit;
    }

    public function publishesRuleCode(string $ruleCode): bool
    {
        return match ($this) {
            self::NewBusinessPermit => $ruleCode === 'MRC-3A-04-BUSINESS-INSPECTION',
            default => false,
        };
    }
}
