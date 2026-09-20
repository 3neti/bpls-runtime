import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { compile } from '@vue/compiler-dom';
import { parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import { transpile } from 'typescript';
import * as Vue from 'vue';
import {
    financialLineItemSubtotal,
    financialLineItemsResolved,
} from '../../resources/js/lib/financialLineItems.ts';
import {
    applyEnterpriseClassification,
    applyManualTreasuryDetermination,
    manualTreasuryDetermination,
    treasuryConfirmationReason,
} from '../../resources/js/lib/treasuryEnterprise.ts';

test('confirmation guidance distinguishes LOB, admitted classification and unresolved policy', () => {
    assert.equal(
        treasuryConfirmationReason([], false),
        'Select an official Line of Business.',
    );
    assert.equal(
        treasuryConfirmationReason([], true),
        'Treasury confirmation is being submitted.',
    );
    assert.match(
        treasuryConfirmationReason(
            [
                {
                    items: [],
                    requiresEnterpriseClassification: true,
                    enterpriseClassification: '',
                },
            ],
            false,
        ),
        /Choose Enterprise Classification/,
    );
    assert.match(
        treasuryConfirmationReason(
            [
                {
                    items: [
                        {
                            fee_rule_id: 1,
                            code: 'mayor',
                            name: 'Mayor’s Permit Fee',
                            amount_cents: 0,
                            resolution_status: 'unresolved',
                        },
                    ],
                    requiresEnterpriseClassification: false,
                },
            ],
            false,
        ),
        /Pricing determination required: Mayor’s Permit Fee/,
    );
    assert.equal(
        treasuryConfirmationReason([{ items: [] }], false),
        'Add the required payment items.',
    );
    assert.equal(
        treasuryConfirmationReason(
            [
                {
                    items: [
                        {
                            fee_rule_id: 1,
                            code: 'known',
                            name: 'Known fee',
                            amount_cents: 0,
                            resolution_status: 'resolved',
                        },
                    ],
                },
            ],
            false,
        ),
        '',
    );
});

const schedule = {
    id: 'uat',
    version: 'v1',
    fingerprint: 'test',
    bands: {
        Micro: 20000,
        Cottage: 50000,
        Small: 100000,
        Medium: 150000,
        Large: 200000,
    },
};
const initial = [
    {
        fee_rule_id: 1,
        code: 'mayor',
        name: 'Mayor’s Permit Fee',
        amount_cents: 0,
        resolution_status: 'unresolved' as const,
    },
    { fee_rule_id: 2, code: 'id', name: 'Laminated ID', amount_cents: 2500 },
    {
        fee_rule_id: 3,
        code: 'occupation',
        name: 'Occupation Fee',
        amount_cents: 10000,
    },
];
test('manual test amount requires exact positive centavos and a basis; editing returns the item to TBD', () => {
    for (const value of [
        '',
        '0',
        '-1',
        '1.001',
        '1e3',
        'Infinity',
        '10000000.01',
    ]) {
        assert.equal(
            manualTreasuryDetermination(value, 'Test authority'),
            null,
        );
    }

    assert.equal(manualTreasuryDetermination('1000', ' '), null);
    const determination = manualTreasuryDetermination(
        '1000.00',
        ' Owner-authorized test ',
    );
    assert.deepEqual(determination, {
        amount_cents: 100000,
        basis: 'Owner-authorized test',
    });
    const selected = applyManualTreasuryDetermination(
        initial,
        1,
        determination,
    );
    assert.equal(financialLineItemSubtotal(selected), 112500);
    assert.equal(financialLineItemsResolved(selected), true);
    assert.equal(selected[0].amount_locked, true);
    assert.equal(selected[1], initial[1]);
    assert.equal(
        treasuryConfirmationReason(
            [
                {
                    items: selected,
                    requiresEnterpriseClassification: true,
                    manualDetermination: true,
                },
            ],
            false,
        ),
        '',
    );
    assert.equal(
        financialLineItemsResolved(
            applyManualTreasuryDetermination(selected, 1, null),
        ),
        false,
    );
});
test('mixed LOB guidance retains a separate policy stop and distinct same-name rule identities', () => {
    const reason = treasuryConfirmationReason(
        [
            {
                items: initial,
                requiresEnterpriseClassification: true,
                enterpriseClassification: '',
                enterpriseFeeId: 1,
            },
            {
                items: [
                    {
                        ...initial[0],
                        code: 'policy-a',
                        resolution_message: 'No admitted rule',
                    },
                    {
                        ...initial[0],
                        code: 'policy-b',
                        resolution_message: 'Authority missing',
                    },
                ],
            },
            { items: [initial[1]] },
        ],
        false,
    );
    assert.match(reason, /Choose Enterprise Classification/);
    assert.match(reason, /policy-a.*No admitted rule/);
    assert.match(reason, /policy-b.*Authority missing/);
    assert.match(reason, /No authorized pricing determination/);
    assert.match(reason, /authorized municipal official/);
    assert.match(reason, /Required fees cannot be deleted to bypass pricing/);
    assert.match(reason, /not a target amount/);
    assert.match(reason, /do not enter, remove or override/);
    const selected = treasuryConfirmationReason(
        [
            {
                items: applyEnterpriseClassification(
                    initial,
                    1,
                    schedule,
                    'Small',
                ),
                requiresEnterpriseClassification: true,
                enterpriseClassification: 'Small',
                enterpriseFeeId: 1,
            },
            { items: [{ ...initial[0], code: 'unrelated' }] },
        ],
        false,
    );
    assert.doesNotMatch(selected, /Choose Enterprise Classification/);
    assert.match(selected, /unrelated/);
    assert.match(selected, /Stop for municipal policy\/configuration review/);
});

test('selector displays supplied band amounts without claiming municipal policy', async () => {
    const selector = readFileSync(
        new URL(
            '../../resources/js/components/permit-applications/EnterpriseClassificationSelector.vue',
            import.meta.url,
        ),
        'utf8',
    );
    assert.match(
        selector,
        /v-for="\(amount, classification\) in schedule.bands"/,
    );
    assert.match(
        selector,
        /\{\{ classification \}\} — \{\{ money\(amount\) \}\}/,
    );
    assert.match(selector, /PROVISIONAL UAT SCHEDULE — NOT MUNICIPAL POLICY/);
    assert.match(selector, /schedule.id/);
    assert.match(selector, /schedule.version/);
    assert.match(selector, /stop for municipal\s+confirmation/);
    assert.doesNotMatch(selector, /PROVISIONAL MUNICIPAL POLICY/);
    const { descriptor } = parse(selector);
    const render = new Function(
        'Vue',
        transpile(
            compile(descriptor.template!.content, {
                mode: 'function',
                prefixIdentifiers: true,
                expressionPlugins: ['typescript'],
            }).code,
        ),
    )(Vue);
    const html = await renderToString(
        Vue.createSSRApp({
            render,
            data: () => ({
                schedule,
                modelValue: '',
                emit: () => {},
                chooseClassification: () => {},
                money: (amount: number) =>
                    new Intl.NumberFormat('en-PH', {
                        style: 'currency',
                        currency: 'PHP',
                    }).format(amount / 100),
            }),
        }),
    );

    for (const [classification, amount] of Object.entries(schedule.bands)) {
        assert.ok(
            html.includes(
                `${classification} — ₱${(amount / 100).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
            ),
        );
    }

    assert.match(html, /NOT MUNICIPAL POLICY/);
    assert.match(html, /<option value(?:="")?>Choose classification<\/option>/);
});

test('unselected means TBD and partial 125; each explicit band controls immutable preview amount', () => {
    assert.equal(financialLineItemsResolved(initial), false);
    assert.equal(financialLineItemSubtotal(initial), 12500);

    for (const [classification, amount] of Object.entries(schedule.bands)) {
        const selected = applyEnterpriseClassification(
            initial,
            1,
            schedule,
            classification,
        );
        assert.equal(financialLineItemsResolved(selected), true);
        assert.equal(financialLineItemSubtotal(selected), amount + 12500);
        assert.equal(selected[0].amount_locked, true);
        assert.equal(initial[0].amount_cents, 0);
        const cleared = applyEnterpriseClassification(
            selected,
            1,
            schedule,
            '',
        );
        assert.equal(financialLineItemsResolved(cleared), false);
        assert.equal(financialLineItemSubtotal(cleared), 12500);
    }

    assert.equal(
        financialLineItemsResolved(
            applyEnterpriseClassification(initial, 1, schedule, 'Unknown'),
        ),
        false,
    );
});
test('actual Treasury form binds officer selection and schedule fingerprint, with locked fee outside editor options', () => {
    const component = readFileSync(
        new URL(
            '../../resources/js/components/permit-applications/BploRoutingTaskSheet.vue',
            import.meta.url,
        ),
        'utf8',
    );
    assert.equal(
        (component.match(/<EnterpriseClassificationSelector/g) ?? []).length,
        2,
    );
    assert.match(
        component,
        /@update:model-value="[\s\S]*?determineEnterprise\(selection, \$event\)/,
    );
    assert.match(
        component,
        /selection\.enterprise_classification = classification/,
    );
    assert.match(
        component,
        /selection\.enterprise_schedule_fingerprint =[\s\S]*?fee\.enterprise_schedule\.fingerprint/,
    );
    assert.match(
        component,
        /useForm\(\{ selections: treasurySelections\.value \}\)\.post/,
    );
    assert.match(component, /filter\(\(item\) => !item\.enterprise_schedule\)/);
    assert.match(component, /:disabled="!treasurySelectionsReady"/);
    assert.equal(
        (component.match(/\{\{ treasuryConfirmReason \}\}/g) ?? []).length,
        2,
    );
    assert.equal(
        (
            component.match(
                /data-testid="treasury-confirm-reason"\s+role="status"/g,
            ) ?? []
        ).length,
        2,
    );
});
