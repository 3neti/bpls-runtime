import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { compile } from '@vue/compiler-dom';
import { renderToString } from '@vue/server-renderer';
import * as Vue from 'vue';

const source = readFileSync(
    'resources/js/pages/payment-schedules/Show.vue',
    'utf8',
);
const historyMarkup = source.match(
    /<details\s+v-if="classicPaymentHandoff\?\.history.length"[\s\S]*?<\/details>/,
)![0];
const stateMarkup = source.match(
    /<p\s+v-if="[\s\S]*?data-testid="qr-handoff-state"[\s\S]*?<\/p>/,
)![0];
const stateStart = stateMarkup.lastIndexOf('<p');
const render = new Function(
    'Vue',
    compile(`<div>${stateMarkup.slice(stateStart)}${historyMarkup}</div>`, {
        mode: 'function',
        prefixIdentifiers: true,
    }).code,
)(Vue);

for (const settled of [true, false]) {
    test(`QR history keeps recorded status distinct from Collection status (settled=${settled})`, async () => {
        const handoff = {
            is_current: false,
            is_settled: settled,
            resolution: 'expired',
            history: [
                {
                    id: 1,
                    reference: 'older-request',
                    status: 'awaiting_payment',
                    expired: true,
                },
                {
                    id: 2,
                    reference: 'latest-request',
                    status: 'awaiting_payment',
                    expired: false,
                },
            ],
        };
        const before = JSON.stringify(handoff);
        const html = await renderToString(
            Vue.createSSRApp({
                render,
                data: () => ({ classicPaymentHandoff: handoff }),
            }),
        );
        const text = html.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ');

        assert.ok(
            text.includes(
                'Historical request status, not the current Collection status.',
            ),
        );
        assert.ok(
            text.includes(
                'older-request — Recorded QR request status: awaiting payment',
            ),
        );
        assert.ok(
            text.includes(
                'latest-request — Recorded QR request status: awaiting payment',
            ),
        );
        assert.equal((text.match(/QR validity: Expired/g) ?? []).length, 1);
        assert.equal(text.includes('Payment completed.'), settled);
        assert.equal(text.includes('No active QR request.'), !settled);
        assert.equal(JSON.stringify(handoff), before);
        assert.ok(!html.includes('<button'));
        assert.ok(!html.includes('<form'));
    });
}
