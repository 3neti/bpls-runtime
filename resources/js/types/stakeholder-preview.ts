export type StakeholderPreviewPersona = {
    key:
        | 'citizen'
        | 'bplo'
        | 'assessment_officer'
        | 'treasury'
        | 'municipal_treasurer'
        | 'cashier'
        | 'management'
        | 'engineering'
        | 'mpdo'
        | 'assessor'
        | 'health'
        | 'menro'
        | 'mayor_office'
        | 'releasing';
    label: string;
    description: string;
};

export type StakeholderPreviewGuidance = {
    label: string;
    href: string;
};

export type StakeholderPreviewContext = {
    enabled: true;
    current_persona: StakeholderPreviewPersona['key'] | null;
    current_label: string | null;
    cleanroom_actor: {
        run_id: number;
        public_id: string;
        key: string;
        label: string;
    } | null;
    personas: StakeholderPreviewPersona[];
    what_to_try: StakeholderPreviewGuidance[];
    recovery_message: string;
};
