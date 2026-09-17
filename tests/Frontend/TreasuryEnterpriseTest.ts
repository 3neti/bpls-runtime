import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import {
    financialLineItemSubtotal,
    financialLineItemsResolved,
} from '../../resources/js/lib/financialLineItems.ts';
import { applyEnterpriseClassification } from '../../resources/js/lib/treasuryEnterprise.ts';

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
});
