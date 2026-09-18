import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { compile } from '@vue/compiler-dom';
import { parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import { transpileModule } from 'typescript';
import * as Vue from 'vue';

const source = readFileSync(
    'resources/js/components/permit-applications/ExecutableApplication.vue',
    'utf8',
);
const summaryCode = source.slice(
    source.indexOf('const page2Summary = computed('),
    source.indexOf('\nwatch('),
);
const summary = new Function(
    'computed',
    'props',
    transpileModule(`${summaryCode}\nreturn page2Summary.value;`, {})
        .outputText,
);

function project(total: number | null | undefined, fallback: number | null) {
    return summary(Vue.computed, {
        document:
            total === undefined
                ? null
                : { page_2_assessment: { emerging_total_amount_cents: total } },
        application: {
            offices: [],
            financial: {
                evaluation: {
                    working_paper: { grand_total_amount_cents: fallback },
                },
            },
        },
    });
}

test('actual executable-document summary preserves canonical null over an older working-paper zero or subtotal', () => {
    assert.equal(project(null, 0).emergingTotalAmountCents, null);
    assert.equal(project(null, 10000).emergingTotalAmountCents, null);
    assert.equal(project(0, 10000).emergingTotalAmountCents, 0);
    assert.equal(project(397500, 0).emergingTotalAmountCents, 397500);
    assert.equal(project(undefined, 10000).emergingTotalAmountCents, 10000);
});

test('actual Assessment rail binding and amount markup preserve unknown, zero and a ready total', async () => {
    const railSource = readFileSync(
        'resources/js/pages/business-permit-evaluations/Show.vue',
        'utf8',
    );
    const totalCode = railSource.slice(
        railSource.indexOf('const assessmentActionTotal = computed('),
        railSource.indexOf('const assessmentAction = computed('),
    );
    const totalValue = new Function(
        'computed',
        'props',
        transpileModule(`${totalCode}\nreturn assessmentActionTotal.value;`, {})
            .outputText,
    );
    const amountMarkup = railSource.match(
        /<dd\s+class="mt-1 text-lg font-semibold tabular-nums"[\s\S]*?<\/dd>/,
    )![0];
    const render = new Function(
        'Vue',
        compile(amountMarkup, { mode: 'function', prefixIdentifiers: true })
            .code,
    )(Vue);
    for (const [amount, expected] of [
        [null, 'Not yet available'],
        [0, '0'],
        [397500, '397500'],
    ] as const) {
        const value = totalValue(Vue.computed, {
            applicationDocument: {
                page_2_assessment: { emerging_total_amount_cents: amount },
            },
        });
        assert.equal(value, amount);
        const html = await renderToString(
            Vue.createSSRApp({
                render,
                data: () => ({
                    assessmentAction: { totalAmountCents: value },
                    money: (input: number) => String(input),
                }),
            }),
        );
        assert.ok(html.includes(expected));
    }
});

test('actual navigator renders unknown distinctly from a genuine zero and a completed total', async () => {
    const { descriptor } = parse(
        readFileSync(
            'resources/js/components/permit-applications/ApplicationDocumentNavigator.vue',
            'utf8',
        ),
    );
    const render = new Function(
        'Vue',
        compile(descriptor.template!.content, {
            mode: 'function',
            prefixIdentifiers: true,
        }).code,
    )(Vue);
    const moneyStart =
        descriptor.scriptSetup!.content.indexOf('function money(');
    const money = new Function(
        transpileModule(
            `${descriptor.scriptSetup!.content.slice(moneyStart)}\nreturn money;`,
            {},
        ).outputText,
    )();
    for (const [total, expected] of [
        [null, 'Not yet available'],
        [0, '₱0.00'],
        [397500, '₱3,975.00'],
    ] as const) {
        const html = await renderToString(
            Vue.createSSRApp({
                render,
                data: () => ({
                    ...project(total, 0),
                    activePage: 'processing',
                    declarationState: 'frozen',
                    routingStatus: 'determined',
                    hasPayable: false,
                    paymentState: null,
                    emit: () => {},
                    money,
                }),
            }),
        );
        assert.ok(html.includes(expected));
        if (total === null) assert.ok(!html.includes('₱0.00'));
    }
});
