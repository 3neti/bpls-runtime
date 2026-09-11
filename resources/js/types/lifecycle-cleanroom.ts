export type LifecycleCleanroomEvidence = {
    public_id: string;
    ceremony: string;
    ceremony_label: string;
    status: 'Active' | 'Completed' | 'Retained';
    progress: {
        completed_steps: number;
        total_steps: number;
        percent: number;
        complete: boolean;
        blocked: boolean;
        next_task: string | null;
        next_actor: string | null;
    };
    application: {
        id: number;
        tracking_reference: string | null;
        status: string;
    } | null;
    timestamps: {
        created_at: string | null;
        completed_at: string | null;
        retained_at: string | null;
    };
    disposition: string;
    event_count: number;
    steps: Array<{
        key: string;
        label: string;
        status: 'completed' | 'current' | 'pending';
    }>;
};
