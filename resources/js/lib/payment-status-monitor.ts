export type PaymentStatusResult = {
    paid: boolean;
    status: string;
    last_checked_at?: string | null;
    reconciliation_state?: string;
};

export type PaymentCheckState = {
    checking: boolean;
    message: string | null;
    lastCheckedAt: string | null;
};

export function createPaymentStatusMonitor(options: {
    enabled: () => boolean;
    request: () => Promise<PaymentStatusResult>;
    changed: (state: PaymentCheckState) => void;
    paid: () => void;
}) {
    const state: PaymentCheckState = {
        checking: false,
        message: null,
        lastCheckedAt: null,
    };
    let disposed = false;
    let settled = false;
    let review = false;

    async function check(): Promise<void> {
        if (disposed || settled || state.checking || !options.enabled()) {
            return;
        }

        state.checking = true;
        options.changed({ ...state });

        try {
            const result = await options.request();

            if (disposed || !options.enabled()) {
                return;
            }

            state.lastCheckedAt =
                result.last_checked_at ?? new Date().toISOString();
            review =
                result.status === 'needs_review' ||
                result.reconciliation_state === 'needs_review';

            if (result.paid && !review) {
                settled = true;
                state.message = 'Paid. Refreshing payment details…';
                options.paid();
            } else if (review) {
                state.message =
                    'Needs review. Ask Treasury to check the payment evidence; do not pay again.';
            } else if (result.status === 'expired') {
                state.message =
                    'QR expired; payment is not yet confirmed. Check before paying again.';
            } else if (result.status === 'temporarily_unavailable') {
                state.message =
                    'Payment checking is temporarily unavailable. Please check again.';
            } else {
                state.message = 'Awaiting payment confirmation.';
            }
        } catch {
            if (!disposed && options.enabled()) {
                state.message =
                    'Payment checking is temporarily unavailable. Please check again; do not pay again solely because confirmation is delayed.';
            }
        } finally {
            state.checking = false;

            if (!disposed) {
                options.changed({ ...state });
            }
        }
    }

    return {
        check,
        canAutomaticallyCheck: () =>
            !disposed && !settled && !review && options.enabled(),
        dispose: () => {
            disposed = true;
        },
    };
}
