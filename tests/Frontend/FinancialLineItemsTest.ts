import assert from 'node:assert/strict';
import { test } from 'node:test';
import type { FinancialLineItemOption } from '../../resources/js/lib/financialLineItems.ts';
import {
    financialLineItemSubtotal,
    formatMinorAsPesoInput,
    parsePesoAmount,
    removeFinancialLineItem,
    upsertFinancialLineItem,
} from '../../resources/js/lib/financialLineItems.ts';

const option: FinancialLineItemOption = {
    id: 42,
    code: 'LAB-IPIL-ASSESSOR-SERVICE-FEE',
    name: 'Assessor Service Fee',
    default_amount_cents: 10_050,
};

test('catalog defaults are displayed as peso text', () => {
    assert.equal(formatMinorAsPesoInput(0), '0.00');
    assert.equal(formatMinorAsPesoInput(1), '0.01');
    assert.equal(formatMinorAsPesoInput(10_000), '100.00');
    assert.equal(formatMinorAsPesoInput(option.default_amount_cents), '100.50');
});

test('integer, decimal, and one-cent peso entries convert exactly', () => {
    assert.deepEqual(parsePesoAmount('100'), { ok: true, amountCents: 10_000 });
    assert.deepEqual(parsePesoAmount('100.50'), {
        ok: true,
        amountCents: 10_050,
    });
    assert.deepEqual(parsePesoAmount('0.01'), { ok: true, amountCents: 1 });
});

test('negative, malformed, and excess-precision entries fail closed', () => {
    for (const value of ['-1', 'abc', '1,000', '1.001', '', '.50', '1e2']) {
        assert.equal(parsePesoAmount(value).ok, false, value);
    }
});

test('Add Item emits centavos, replaces the same fee, and totals exactly', () => {
    const first = upsertFinancialLineItem([], option, 10_000);
    assert.equal(first[0]?.amount_cents, 10_000);
    assert.equal(financialLineItemSubtotal(first), 10_000);

    const secondOption = { ...option, id: 43, code: 'SECOND' };
    const withSecond = upsertFinancialLineItem(first, secondOption, 1);
    assert.equal(financialLineItemSubtotal(withSecond), 10_001);

    const replaced = upsertFinancialLineItem(withSecond, option, 10_050);
    assert.equal(replaced.length, 2);
    assert.equal(financialLineItemSubtotal(replaced), 10_051);

    const removed = removeFinancialLineItem(replaced, option.id);
    assert.deepEqual(
        removed.map((item) => item.fee_rule_id),
        [43],
    );
    assert.equal(financialLineItemSubtotal(removed), 1);
});
