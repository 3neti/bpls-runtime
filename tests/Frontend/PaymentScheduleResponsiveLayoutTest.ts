import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { baseParse, compile } from '@vue/compiler-dom';
import { parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import * as Vue from 'vue';

const source = readFileSync(
    'resources/js/pages/payment-schedules/Show.vue',
    'utf8',
);
const { descriptor } = parse(source);
const ast = baseParse(descriptor.template!.content);
type Element = {
    tag: string;
    props: Array<{ name: string; value?: { content: string } }>;
    children: Element[];
    type: number;
};
const elements: Array<{ node: Element; parents: Element[] }> = [];
function visit(node: Element, parents: Element[] = []) {
    if (node.type === 1) {
        elements.push({ node, parents });
    }

    for (const child of node.children ?? []) {
        visit(child, node.type === 1 ? [...parents, node] : parents);
    }
}
visit(ast as unknown as Element);
const attr = (node: Element, name: string) =>
    node.props.find((prop) => prop.name === name)?.value?.content ?? '';
const has = (node: Element, token: string) =>
    attr(node, 'class').split(/\s+/).includes(token);

test('mobile schedule tracks shrink without hiding receipt overflow at the page boundary', () => {
    const main = elements.find(({ node }) => node.tag === 'main')!.node;
    assert.ok(has(main, 'min-w-0'));
    assert.ok(!has(main, 'overflow-x-hidden'));

    for (const marker of [
        'xl:grid-cols-[minmax(0,1fr)_22rem]',
        'order-2',
        'xl:sticky',
    ]) {
        const grid = elements.find(({ node }) => has(node, marker))!.node;
        assert.ok(has(grid, 'grid-cols-1'));
        assert.ok(has(grid, 'min-w-0'));
    }

    const details = elements.find(
        ({ node }) => attr(node, 'data-testid') === 'payment-details',
    )!.node;
    assert.ok(has(details, 'min-w-0'));
    assert.ok(has(details, 'max-w-full'));
    const table = elements.find(
        ({ node }) => node.tag === 'table' && has(node, 'min-w-[560px]'),
    )!;
    const scroller = table.parents.at(-1)!;

    for (const token of [
        'w-full',
        'min-w-0',
        'max-w-full',
        'overflow-x-auto',
    ]) {
        assert.ok(has(scroller, token));
    }
});

test('receipt rows retain reachable wrapping links inside constrained containers', async () => {
    const markup = source.match(
        /<section\s+v-if="paymentSchedule.collections.length > 0"[\s\S]*?<\/section>/,
    )![0];
    const render = new Function(
        'Vue',
        compile(markup, { mode: 'function', prefixIdentifiers: true }).code,
    )(Vue);
    const html = await renderToString(
        Vue.createSSRApp({
            render,
            components: {
                Link: {
                    props: ['href'],
                    template: '<a :href="href"><slot /></a>',
                },
                Badge: { template: '<span><slot /></span>' },
                ReceiptText: { template: '<span />' },
                Label: { template: '<label><slot /></label>' },
                Input: { template: '<input />' },
                Button: { template: '<button><slot /></button>' },
                InputError: { template: '<span />' },
                Form: { template: '<form><slot /></form>' },
            },
            data: () => ({
                can: { view_receipts: true, issue_receipts: false },
                paymentSchedule: {
                    collections: [
                        {
                            id: 1,
                            amount_cents: 397500,
                            method: 'qr_ph',
                            received_at: '2026-09-19',
                            status: 'receipted',
                            receipts: [
                                {
                                    id: 11,
                                    receipt_number: '9029401',
                                    receipt_group_label:
                                        'Municipal Engineering Certificate Fee',
                                    amount_cents: 15000,
                                },
                                {
                                    id: 12,
                                    receipt_number: '9029402',
                                    receipt_group_label:
                                        'Treasury Line of Business',
                                    amount_cents: 100000,
                                },
                            ],
                        },
                    ],
                },
                receiptShow: (id: number) => `/staff/receipts/${id}`,
                money: (cents: number) => String(cents / 100),
                label: (value: string) => value.replaceAll('_', ' '),
            }),
        }),
    );
    assert.ok(html.includes('OR 9029401'));
    assert.ok(html.includes('OR 9029402'));
    const anchors = [...html.matchAll(/<a\b[^>]+>/g)].map((match) => match[0]);
    assert.equal(anchors.length, 2);

    for (const anchor of anchors) {
        assert.ok(anchor.includes('min-w-0'));
        assert.ok(anchor.includes('max-w-full'));
        assert.ok(anchor.includes('break-all'));
    }

    assert.ok(!html.includes('overflow-hidden'));
    assert.ok(html.includes('grid-cols-1'));
});
