import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { baseParse, compile } from '@vue/compiler-dom';
import { parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import * as Vue from 'vue';

const working = [
    'DailyCollections',
    'RevenueSources',
    'PaidEstablishments',
    'UnpaidEstablishments',
    'BreakdownOfCollectibles',
    'AssessmentSummary',
    'PaymentSummary',
    'BusinessTaxByMajorType',
    'TotalCapitalGrossSummary',
    'TopEstablishmentsTaxDue',
];
const blocked = [
    'AllAbstract',
    'BillingGroupAbstract',
    'CmciLdcs',
    'Plds',
    'Bsp',
    'AnnexCDnfbp',
    'TaxpayerAccountCard',
];
const source = (name: string) =>
    readFileSync(`resources/js/pages/reports/${name}.vue`, 'utf8');

test('all report templates retain a constrained shared workspace', () => {
    for (const name of [...working, ...blocked]) {
        const s = source(name);
        assert.ok(s.includes('<ReportWorkspace>'), name);
        assert.ok(s.includes('<ReportFamilyBanner'), name);
        assert.equal(parse(s).errors.length, 0, name);
    }
    const shell = readFileSync(
        'resources/js/components/reports/ReportWorkspace.vue',
        'utf8',
    );
    assert.ok(shell.includes('min-w-0'));
    assert.ok(shell.includes('max-w-full'));
    assert.ok(!shell.includes('overflow-x-hidden'));
});

test('working report filters and unchanged exports share one toolbar', () => {
    for (const name of working) {
        const s = source(name);
        const form = s.match(/<form\b[\s\S]*?<\/form>/)![0];
        assert.ok(form.includes('@submit.prevent="applyFilters"'), name);
        assert.ok(form.includes('Export CSV'), name);
        assert.ok(form.includes('@click="clearFilters"'), name);
        assert.ok(s.includes('report-totals'), name);
        assert.ok(
            s.includes(
                name === 'TotalCapitalGrossSummary'
                    ? 'summary.qualification_date_basis'
                    : 'summary.date_basis',
            ),
            name,
        );
    }
    for (const name of blocked) {
        assert.ok(source(name).includes('availability="policy_bound"'), name);
        assert.ok(!source(name).includes('Export CSV'), name);
    }
});

function headers(name: string): string[] {
    return [...source(name).matchAll(/<th\b[^>]*>([\s\S]*?)<\/th>/g)].map((m) =>
        m[1].trim().replace(/\s+/g, ' '),
    );
}

test('legacy-supported columns use familiar owner and business positions', () => {
    assert.deepEqual(headers('PaidEstablishments'), [
        'Owner',
        'Establishment',
        'Line of Business',
        'Application',
        'Representative OR',
        'Paid',
    ]);
    assert.deepEqual(headers('UnpaidEstablishments'), [
        'Owner',
        'Establishment',
        'Line of Business',
        'Application',
        'Payment',
        'Due',
        'Outstanding',
    ]);
    assert.deepEqual(headers('BusinessTaxByMajorType'), [
        'Major Type',
        'Amount',
        'Allocations',
        'Receipts',
    ]);
    assert.deepEqual(headers('TopEstablishmentsTaxDue').slice(0, 3), [
        'Rank',
        'Owner',
        'Establishment',
    ]);
    assert.deepEqual(headers('DailyCollections').slice(0, 3), [
        'Representative OR',
        'Payer',
        'Business',
    ]);
    assert.deepEqual(headers('TotalCapitalGrossSummary'), [
        'Name',
        'Business name',
        'Capital',
        'Gross',
        'Representative OR',
        'Payment date',
        'Payment amount',
        'Remaining balance',
        'Payment status',
    ]);
});

test('wide table regions are keyboard reachable and headers are scoped', () => {
    for (const name of working) {
        const s = source(name);
        assert.ok(s.includes('tabindex="0"'), name);
        const ast = baseParse(parse(s).descriptor.template!.content);
        assert.ok(ast.children.length > 0);
        for (const th of s.matchAll(/<th\b[^>]*>/g)) {
            assert.ok(th[0].includes('scope="col"'), name);
        }
    }
});

async function renderRows(
    name: string,
    row: Record<string, unknown>,
    summary: Record<string, unknown> = {},
) {
    const table = source(name).match(/<table\b[\s\S]*?<\/table>/)![0];
    const render = new Function(
        'Vue',
        compile(table, { mode: 'function', prefixIdentifiers: true }).code,
    )(Vue);
    return renderToString(
        Vue.createSSRApp({
            render,
            components: { Badge: { template: '<span><slot /></span>' } },
            data: () => ({
                rows: [row],
                summary,
                money: (n: number) => `PHP ${(n / 100).toFixed(2)}`,
                label: (v: string | null) => v?.replaceAll('_', ' ') ?? '-',
                dateTime: (v: string | null) => v ?? '-',
            }),
        }),
    );
}

test('major-type footer amounts and counts follow their reordered columns', async () => {
    const html = await renderRows(
        'BusinessTaxByMajorType',
        {},
        {
            total_amount_cents: 397500,
            allocation_count: 7,
            receipt_count: 6,
        },
    );
    const footer = html.match(/<tfoot\b[\s\S]*?<\/tfoot>/)![0];
    const cells = [...footer.matchAll(/<td\b[^>]*>([\s\S]*?)<\/td>/g)].map(
        (match) => match[1].replace(/<[^>]*>/g, '').trim(),
    );
    assert.deepEqual(cells, ['Total Amount', 'PHP 3975.00', '7', '6']);
});

test('owner/business cells move with headers; representative OR remains distinct', async () => {
    const html = await renderRows('PaidEstablishments', {
        payment_schedule_id: 1,
        owner_name: 'Test Owner',
        business_name: 'Test Business',
        trade_name: null,
        barangay: 'Test Barangay',
        application_id: 42,
        application_number: null,
        application_type: 'new',
        application_status: 'released',
        line_of_businesses: ['Fresh Fish'],
        receipt_number: 'TEST-OR',
        receipt_status: 'issued',
        paid_amount_cents: 397500,
    });
    assert.ok(html.indexOf('Test Owner') < html.indexOf('Test Business'));
    assert.ok(html.indexOf('Fresh Fish') < html.indexOf('Application #42'));
    assert.ok(html.includes('Representative OR'));
    assert.ok(html.includes('PHP 3975.00'));
});

test('payment summary retains packet totals, count, latest OR and discrepancy', async () => {
    const html = await renderRows('PaymentSummary', {
        payment_schedule_id: 1,
        application_id: 42,
        application_number: null,
        business_name: 'Packet test',
        owner_name: 'Test Owner',
        trade_name: null,
        application_type: 'new',
        application_status: 'released',
        schedule_status: 'paid',
        payment_mode: 'single',
        due_on: null,
        total_amount_cents: 397500,
        paid_amount_cents: 397500,
        outstanding_amount_cents: 0,
        collection_count: 1,
        receipted_count: 6,
        receipted_amount_cents: 397500,
        latest_receipt_number: 'TEST-LAST',
        collection_difference_cents: 100,
    });
    assert.ok(html.includes('TEST-LAST'));
    assert.match(html, /6 receipt\(s\)/);
    assert.ok(html.includes('PHP 3975.00'));
    assert.ok(html.includes('PHP 1.00'));
});
