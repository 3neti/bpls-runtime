export type PriceTraceability = {
    fee_rule_id: number;
    rule_code: string;
    scope: string;
    line_of_business_id: number | null;
    application_type: string;
    application_year: number;
    effective_from: string;
    effective_until: string | null;
    legal_basis: string;
    legal_source_id: string;
    source_classification: string;
    reconciliation_id: number;
    reconciliation_version: number;
    reconciliation_effective_from: string;
    reconciliation_effective_until: string | null;
    legal_authority: string;
    evidence_reference: string;
    execution_status: string;
};

export type ConfirmedCharge = {
    kind: 'fixed';
    label: string;
    amount_cents: number;
    cadence: 'year';
    traceability: PriceTraceability;
};

export type FeeRuleRangePreview = {
    min_basis_cents: number;
    max_basis_cents: number | null;
    amount_cents: number;
    rate_basis_points: number | null;
};

export type LineOfBusinessReference = {
    id: number;
    code: string | null;
    name: string;
};

export type InternalFeeRule = {
    id: number;
    code: string;
    name: string;
    category: string;
    scope: string;
    line_of_business: LineOfBusinessReference | null;
    calculation_type: string;
    basis: string;
    recorded_amount_cents: number | null;
    rate_basis_points: number | null;
    range_count: number;
    ranges: FeeRuleRangePreview[];
    policy_note: string | null;
    effective_from: string;
    effective_until: string | null;
    legal_basis: string | null;
    legal_source_id: string | null;
    source_classification: string;
    publication_status: 'confirmed_exact' | 'not_published_exact';
    selected_by_assessment: boolean;
    automatic_assessment_status:
        'used_by_assessment' | 'not_available_for_automatic_assessment';
    automatic_assessment_label: string;
    application_year: number;
    overlap_ambiguous: boolean;
    reconciliation: {
        id: number;
        version: number;
        legal_authority: string;
        evidence_reference: string;
        decision_authority: string | null;
        decision_reference: string | null;
        effective_from: string;
        effective_until: string | null;
        execution_status: 'executable' | 'blocked';
        execution_reason: string;
    } | null;
    plain_language_status: string;
};

export type MunicipalServiceOffering = {
    code:
        | 'new_business_permit'
        | 'renewal'
        | 'amendment'
        | 'transfer'
        | 'retirement_closure';
    name: string;
    application_type: string;
    description: string;
    availability: 'available_online' | 'staff_assisted_being_completed';
    availability_label: string;
    can_start_online: boolean;
    start_url: string | null;
    pricing: {
        status:
            | 'confirmed_exact_with_other_possible_charges'
            | 'municipal_confirmation_required';
        confirmed_charges: ConfirmedCharge[];
        other_charges_heading: string;
        other_charges_message: string;
        office_determined_message: string;
        confirmation_message: string;
    };
    internal?: {
        selected_rule_count: number;
        ambiguous_rule_keys: string[];
        rules: InternalFeeRule[];
        line_of_business_pricing: InternalFeeRule[];
        office_determined: {
            status: 'office_determined';
            display: string;
            system_computed: false;
            official_price_recorded: false;
        };
    };
};

export type MunicipalPriceList = {
    catalog: {
        title: string;
        scope: string;
        as_of_date: string;
        application_year: number;
        read_only: true;
        audience: 'public' | 'internal';
        service_count: number;
        confirmed_exact_charge_count: number;
    };
    services: MunicipalServiceOffering[];
};
