import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(
    new URL(
        '../../resources/js/lib/payment-status-monitor.ts',
        import.meta.url,
    ),
    'utf8',
);
const js = ts.transpileModule(source, {
    compilerOptions: {
        target: ts.ScriptTarget.ES2022,
        module: ts.ModuleKind.ES2022,
    },
}).outputText;
const { createPaymentStatusMonitor } = await import(
    `data:text/javascript;base64,${Buffer.from(js).toString('base64')}`
);

test('reopening an existing request checks immediately, independent of QR expiry', async () => {
    let calls = 0;
    let paid = 0;
    const monitor = createPaymentStatusMonitor({
        enabled: () => true,
        request: async () => {
            calls++;
            return { paid: true, status: 'paid' };
        },
        changed: () => {},
        paid: () => paid++,
    });
    await monitor.check();
    await monitor.check();
    assert.equal(calls, 1);
    assert.equal(paid, 1);
});

test('manual and automatic checks cannot overlap', async () => {
    let resolve;
    let calls = 0;
    const monitor = createPaymentStatusMonitor({
        enabled: () => true,
        request: () => {
            calls++;
            return new Promise((r) => (resolve = r));
        },
        changed: () => {},
        paid: () => {},
    });
    const first = monitor.check();
    await monitor.check();
    assert.equal(calls, 1);
    resolve({ paid: false, status: 'awaiting_payment' });
    await first;
});

test('provider failure stays unavailable, then a later check can recover', async () => {
    const states = [];
    let calls = 0;
    const monitor = createPaymentStatusMonitor({
        enabled: () => true,
        request: async () => {
            if (++calls === 1) throw new Error('offline');
            return {
                paid: true,
                status: 'paid',
                last_checked_at: '2026-09-22T00:00:00Z',
            };
        },
        changed: (s) => states.push({ ...s }),
        paid: () => {},
    });
    await monitor.check();
    assert.match(states.at(-1).message, /unavailable/i);
    assert.equal(states.at(-1).lastCheckedAt, null);
    await monitor.check();
    assert.equal(states.at(-1).message, 'Paid. Refreshing payment details…');
    assert.equal(states.at(-1).lastCheckedAt, '2026-09-22T00:00:00Z');
});

test('expired QR does not claim unpaid and review pauses automatic but allows manual checks', async () => {
    let status = 'expired';
    let last;
    const monitor = createPaymentStatusMonitor({
        enabled: () => true,
        request: async () => ({ paid: false, status }),
        changed: (s) => (last = { ...s }),
        paid: () => {},
    });
    await monitor.check();
    assert.match(last.message, /not yet confirmed/);
    status = 'needs_review';
    await monitor.check();
    assert.equal(monitor.canAutomaticallyCheck(), false);
    status = 'awaiting_payment';
    await monitor.check();
    assert.equal(monitor.canAutomaticallyCheck(), true);
});

test('unmount or permission loss ignores in-flight responses', async () => {
    let resolve;
    let paid = 0;
    const monitor = createPaymentStatusMonitor({
        enabled: () => true,
        request: () => new Promise((r) => (resolve = r)),
        changed: () => {},
        paid: () => paid++,
    });
    const first = monitor.check();
    monitor.dispose();
    resolve({ paid: true, status: 'paid' });
    await first;
    assert.equal(paid, 0);
});

test('a successful HTTP response reporting a provider error does not claim pending payment', async () => {
    let last;
    const monitor = createPaymentStatusMonitor({
        enabled: () => true,
        request: async () => ({ paid: false, status: 'error', reconciliation_state: 'error' }),
        changed: (state) => (last = state),
        paid: () => assert.fail('must not settle'),
    });
    await monitor.check();
    assert.match(last.message, /unavailable/);
    assert.match(last.message, /do not pay again/);
    assert.equal(monitor.canAutomaticallyCheck(), true);
});
