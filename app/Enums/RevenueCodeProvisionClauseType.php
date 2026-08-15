<?php

namespace App\Enums;

enum RevenueCodeProvisionClauseType: string
{
    case DependentRate = 'dependent_rate';
    case Eligibility = 'eligibility';
    case TaxBase = 'tax_base';
    case RateBand = 'rate_band';
    case AuthorityBoundary = 'authority_boundary';
    case TaxableReceiptCatalog = 'taxable_receipt_catalog';
    case DocumentaryRequirement = 'documentary_requirement';
    case AmountCeiling = 'amount_ceiling';
    case Exemption = 'exemption';
    case PaymentTiming = 'payment_timing';
    case TaxScopeBoundary = 'tax_scope_boundary';
    case MinimumTax = 'minimum_tax';
    case InitialTaxBasis = 'initial_tax_basis';
    case InstallmentSchedule = 'installment_schedule';
    case CompletionRecomputation = 'completion_recomputation';
    case SeparateEstablishment = 'separate_establishment';
    case CombinedTaxBase = 'combined_tax_base';
    case BestAvailableEvidence = 'best_available_evidence';
    case DeficiencyTax = 'deficiency_tax';
    case SurchargeInterest = 'surcharge_interest';
    case PresumptiveIncomeThreshold = 'presumptive_income_threshold';
    case ValidationFallback = 'validation_fallback';
    case PermitRequirement = 'permit_requirement';
    case ReceiptRequirement = 'receipt_requirement';
    case RecordRetention = 'record_retention';
    case ReceiptCertification = 'receipt_certification';
    case LocationTransfer = 'location_transfer';
    case RetirementRequirement = 'retirement_requirement';
    case TaxSettlement = 'tax_settlement';
    case PermitCancellation = 'permit_cancellation';
    case EstateContinuation = 'estate_continuation';
    case TaxMapping = 'tax_mapping';
}
