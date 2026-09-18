type PermitDetailFacts = {
    verification_boundary: { released: boolean; status: string };
    latest_payment_schedule: {
        total_amount_cents: number;
        paid_amount_cents: number;
    } | null;
    has_business_permit_evaluation: boolean;
    can_continue: boolean;
};

export function permitDetailPresentation(application: PermitDetailFacts) {
    const released = application.verification_boundary.released;
    const issued =
        application.verification_boundary.status ===
        'issued_synthetic_identity';
    const schedule = application.latest_payment_schedule;
    const unpaid =
        schedule !== null &&
        schedule.paid_amount_cents < schedule.total_amount_cents;

    return {
        nextAction: released
            ? 'View released Permit / verify'
            : issued
              ? 'BPLO release'
              : !application.can_continue
                ? 'No further processing'
                : unpaid
                  ? 'Receive payment'
                  : schedule !== null
                    ? 'Continue post-payment review'
                    : application.has_business_permit_evaluation
                      ? 'Continue municipal review'
                      : 'Start Evaluation',
        authorityTitle: released
            ? 'Synthetic Permit released'
            : issued
              ? 'Synthetic Permit issued — BPLO release pending'
              : 'Municipal review — Permit not issued',
        authorityStatus: released
            ? 'released_synthetic_identity'
            : issued
              ? 'issued_synthetic_identity'
              : null,
    };
}
