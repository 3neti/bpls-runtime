export type MunicipalFeeScheduleRow = {
    id: string;
    fee_rule_id: number | null;
    service: string;
    basis: string;
    code: string;
    revenue_code?: string | null;
    determination_channel?: string | null;
    responsible_office_codes?: string[];
    line_of_business_ids?: number[];
    catalogue_version?: string | null;
    rule_version?: string | null;
    amount_minor: number | null;
    rate_basis_points: string | number | null;
    is_ceiling: boolean;
    status: 'available' | 'for_confirmation' | 'needs_determination';
    effective_from: string | null;
    effective_until: string | null;
    revision_eligible: boolean;
    management_url: string | null;
    governance_url: string | null;
    application_state?: 'eligible' | 'selected' | 'assessed';
    source_label?: string | null;
};

export type MunicipalFeeScheduleCategory = {
    key: string;
    label: string;
    rows: MunicipalFeeScheduleRow[];
};

export type MunicipalScheduleOfFees = {
    schema_version: 'bpls.municipal-schedule-of-fees.v1' | string;
    title: string;
    scope: string;
    as_of_date: string;
    application_year: number;
    currency: 'PHP' | string;
    categories: MunicipalFeeScheduleCategory[];
    context?: {
        kind?: 'application' | string;
        state?:
            | 'awaiting_context'
            | 'application_options'
            | 'selected_charges'
            | 'assessed_snapshot'
            | string;
        item_count?: number;
        selected_count?: number;
        total_amount_minor?: number;
    };
};
