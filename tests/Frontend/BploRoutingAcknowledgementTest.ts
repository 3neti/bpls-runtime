import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(
    'resources/js/components/permit-applications/BploRoutingTaskSheet.vue',
    'utf8',
);
const submitSource = source.slice(
    source.indexOf('async function submit('),
    source.indexOf('function confirmPaymentOrder('),
);
const javascript = ts.transpileModule(submitSource, {
    compilerOptions: { target: ts.ScriptTarget.ES2022 },
}).outputText;

function harness(post: () => Promise<unknown>, errors = false) {
    const state = {
        pending: { value: false },
        routingAcknowledged: { value: false },
        routingOutcomeUnconfirmed: { value: false },
        routingMessage: { value: '' },
        selectedCount: { value: 1 },
        routingContext: { value: 'Checklist' },
        props: { task: { application: { id: 999 }, routing: null } },
        candidates: [
            {
                key: 'assessor',
                office: { code: 'assessor', label: 'Assessor' },
                line: { id: null },
            },
        ],
        drafts: { assessor: { selected: true, reason: '', requiredWork: '' } },
    };
    let posts = 0;
    let reloads = 0;
    let timerCleared = false;
    let submitted: unknown;
    const functions = new Function(
        ...Object.keys(state),
        'useHttp',
        'recordBploRouting',
        'window',
        `let routingTimer; ${javascript}; return { submit, reviewRoutingRecord };`,
    )(
        ...Object.values(state),
        (payload: unknown) => {
            submitted = payload;
            return {
                hasErrors: errors,
                errors: { selected_work: 'Select an office.' },
                post: () => {
                    posts++;
                    return post();
                },
            };
        },
        () => ({ url: '/routing' }),
        {
            setTimeout: () => 1,
            clearTimeout: () => {
                timerCleared = true;
            },
            location: {
                reload: () => {
                    reloads++;
                },
            },
        },
    );
    return {
        state,
        ...functions,
        posts: () => posts,
        reloads: () => reloads,
        cleared: () => timerCleared,
        submitted: () => submitted,
    };
}

test('actual routing form acknowledges committed identity without a redirect or duplicate submit', async () => {
    const h = harness(async () => ({
        status: 'recorded',
        permit_application_id: 999,
        routing_determination_id: 31,
    }));
    await h.submit();
    assert.equal(h.state.routingAcknowledged.value, true);
    assert.equal(h.state.routingOutcomeUnconfirmed.value, false);
    assert.match(h.state.routingMessage.value, /routing recorded/);
    assert.equal(h.cleared(), true);
    await h.submit();
    assert.equal(h.posts(), 1);
    assert.equal(h.reloads(), 0);
    assert.deepEqual(h.submitted(), {
        situational_context: 'Checklist',
        selected_work: [
            {
                office_code: 'assessor',
                office_label: 'Assessor',
                situational_reason: '',
                required_work: '',
                permit_application_line_id: null,
            },
        ],
    });
    assert.match(source, /@submit.prevent="submit"/);
    assert.match(
        source,
        /v-if="routingAcknowledged"[\s\S]*?:href="workInbox.url\(\)"/,
    );
});

test('lost, invalid or mismatched acknowledgement blocks replay and offers only a read-only reload', async () => {
    for (const result of [
        new Error('Network lost after commit'),
        null,
        {
            status: 'recorded',
            permit_application_id: 1000,
            routing_determination_id: 31,
        },
    ]) {
        const h = harness(async () => {
            if (result instanceof Error) throw result;
            return result;
        });
        await h.submit();
        assert.equal(h.state.routingOutcomeUnconfirmed.value, true);
        assert.equal(h.state.routingAcknowledged.value, false);
        assert.match(h.state.routingMessage.value, /may already be recorded/);
        assert.equal(h.state.pending.value, false);
        assert.equal(h.cleared(), true);
        await h.submit();
        h.reviewRoutingRecord();
        assert.equal(h.posts(), 1);
        assert.equal(h.reloads(), 1);
    }
    assert.match(
        source,
        /:disabled="[\s\S]*?routingAcknowledged[\s\S]*?routingOutcomeUnconfirmed/,
    );
});

test('server validation rejection permits correction and displays the actual field error', async () => {
    const h = harness(async () => undefined, true);
    await h.submit();
    assert.equal(h.state.routingMessage.value, 'Select an office.');
    assert.equal(h.state.routingOutcomeUnconfirmed.value, false);
    assert.equal(h.state.routingAcknowledged.value, false);
    await h.submit();
    assert.equal(h.posts(), 2);
});

test('pending routing submission cannot send a second request', async () => {
    let resolve!: (value: unknown) => void;
    const h = harness(
        () =>
            new Promise((r) => {
                resolve = r;
            }),
    );
    const first = h.submit();
    await h.submit();
    assert.equal(h.posts(), 1);
    resolve({
        status: 'recorded',
        permit_application_id: 999,
        routing_determination_id: 31,
    });
    await first;
    assert.equal(h.state.routingAcknowledged.value, true);
});
