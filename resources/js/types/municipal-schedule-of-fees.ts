export type MunicipalFeeScheduleRow = {
    id: string;
    fee_rule_id: number | null;
    service: string;
    basis: string;
    code: string;
    amount_minor: number | null;
    rate_basis_points: string | number | null;
    is_ceiling: boolean;
    status: 'available' | 'for_confirmation' | 'needs_determination';
    effective_from: string | null;
    effective_until: string | null;
    revision_eligible: boolean;
    management_url: string | null;
    governance_url: string | null;
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
};
