export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    role: string | null;
    can_access_staff: boolean;
    can_access_citizen: boolean;
    can_view_permit_applications: boolean;
    can_assess_permit_applications: boolean;
    can_approve_assessments: boolean;
    can_view_payment_schedules: boolean;
    can_prepare_payment_schedules: boolean;
    can_view_collections: boolean;
    can_record_collections: boolean;
    can_view_receipts: boolean;
    can_issue_receipts: boolean;
    can_view_billing_groups: boolean;
    can_view_reports: boolean;
    can_view_fee_rules: boolean;
    can_view_users: boolean;
    can_view_roles: boolean;
    can_view_municipality_configuration: boolean;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
