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
const header = source.match(/<header[\s\S]*?<\/header>/)![0];
const packet = source.match(
    /<section\s+v-if="receiptReconciliation"[\s\S]*?<\/section>/,
)![0];
const assessment = source.match(
    /<div>\s*<dt[^>]*>\s*Assessment\s*<\/dt>[\s\S]*?<\/div>/,
)![0];
const render = new Function(
    'Vue',
    compile(`<div>${header}${packet}${assessment}</div>`, {
        mode: 'function',
        prefixIdentifiers: true,
    }).code,
)(Vue);
const Link = { props: ['href'], template: '<a :href="href"><slot /></a>' };
const Wrapper = { template: '<div><slot /></div>' };

test('actual packet markup is absent when receipt evidence is not authorized', async () => {
    const packetRender = new Function(
        'Vue',
        compile(packet, { mode: 'function', prefixIdentifiers: true }).code,
    )(Vue);
    const html = await renderToString(
        Vue.createSSRApp({
            render: packetRender,
            data: () => ({ receiptReconciliation: null }),
        }),
    );
    assert.ok(!html.includes('Official Receipt coverage'));
    assert.ok(!html.includes('fully reconciled'));
});

for (const permitted of [false, true]) {
    for (const complete of [false, true]) {
        test(`actual schedule links and packet reflect permission=${permitted}, complete=${complete}`, async () => {
            const html = await renderToString(
                Vue.createSSRApp({
                    render,
                    components: {
                        Link,
                        Button: Wrapper,
                        Badge: Wrapper,
                        ArrowLeft: Wrapper,
                    },
                    data: () => ({
                        can: { view_permit_application: permitted },
                        paymentSchedule: {
                            sequence: 1,
                            assessment: { id: 9, sequence: 1 },
                            permit_application: {
                                id: 294,
                                application_number: null,
                                business_name: 'Synthetic',
                                owner_name: 'Citizen',
                            },
                        },
                        receiptReconciliation: {
                            issued_receipt_group_count: complete ? 6 : 1,
                            required_receipt_group_count: 6,
                            total_receipted_cents: complete ? 397500 : 15000,
                            unreceipted_amount_cents: complete ? 0 : 382500,
                            status: complete
                                ? 'fully_reconciled'
                                : 'pending_receipts',
                        },
                        workspaceState: 'Paid',
                        assessmentShow: () => '/staff/assessments/9',
                        permitApplicationShow: () =>
                            '/staff/permit-applications/294',
                        paymentScheduleIndex: () => '/staff/payment-schedules',
                        money: (cents: number) =>
                            new Intl.NumberFormat('en-PH', {
                                style: 'currency',
                                currency: 'PHP',
                            }).format(cents / 100),
                        label: (value: string) => value.replaceAll('_', ' '),
                    }),
                }),
            );
            const text = html.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ');
            assert.equal(
                html.includes('href="/staff/permit-applications/294"'),
                permitted,
            );
            assert.equal(
                (html.match(/href="\/staff\/assessments\/9"/g) ?? []).length,
                permitted ? 2 : 0,
            );
            assert.equal(
                html.includes('href="/staff/payment-schedules"'),
                !permitted,
            );
            assert.ok(text.includes('Application #294'));
            assert.ok(text.includes(complete ? '6 / 6' : '1 / 6'));
            assert.ok(text.includes(complete ? '₱3,975.00' : '₱150.00'));
            assert.ok(text.includes(complete ? '₱0.00' : '₱3,825.00'));
            assert.ok(
                text.includes(
                    complete ? 'fully reconciled' : 'pending receipts',
                ),
            );
        });
    }
}
