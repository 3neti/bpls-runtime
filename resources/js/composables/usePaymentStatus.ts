import { computed, onBeforeUnmount, onMounted, reactive, watch } from 'vue';
import {
    createPaymentStatusMonitor,
    type PaymentCheckState,
    type PaymentStatusResult,
} from '@/lib/payment-status-monitor';

export function usePaymentStatus(options: {
    enabled: () => boolean;
    request: () => Promise<PaymentStatusResult>;
    paid: () => void;
}) {
    const state = reactive<PaymentCheckState>({
        checking: false,
        message: null,
        lastCheckedAt: null,
    });
    const monitor = createPaymentStatusMonitor({
        ...options,
        changed: (next) => Object.assign(state, next),
    });
    let timer: ReturnType<typeof setInterval> | null = null;

    function refresh(): void {
        if (!document.hidden && monitor.canAutomaticallyCheck())
            void monitor.check();
    }

    onMounted(() => {
        refresh();
        timer = setInterval(refresh, 4000);
        window.addEventListener('focus', refresh);
        document.addEventListener('visibilitychange', refresh);
    });
    watch(options.enabled, (enabled) => {
        if (enabled) refresh();
    });
    onBeforeUnmount(() => {
        monitor.dispose();
        if (timer !== null) clearInterval(timer);
        window.removeEventListener('focus', refresh);
        document.removeEventListener('visibilitychange', refresh);
    });

    return {
        check: monitor.check,
        checking: computed(() => state.checking),
        message: computed(() => state.message),
        lastChecked: computed(() =>
            state.lastCheckedAt === null
                ? 'Not checked on this screen'
                : new Intl.DateTimeFormat('en-PH', {
                      hour: 'numeric',
                      minute: '2-digit',
                      second: '2-digit',
                  }).format(new Date(state.lastCheckedAt)),
        ),
    };
}
