export type FinancialLineItemOption = {
    id: number;
    code: string;
    name: string;
    default_amount_cents: number;
    account_code?: string | null;
    exact_once_key?: string | null;
    calculation?: {
        explanation?: string | null;
        rule_signature?: string;
    };
};

export type FinancialLineItem = {
    fee_rule_id: number;
    code: string;
    name: string;
    amount_cents: number;
    exact_once_key?: string | null;
    calculation?: {
        explanation?: string | null;
        rule_signature?: string;
    };
};

export type PesoAmountParseResult =
    { ok: true; amountCents: number } | { ok: false; error: string };

export function formatMinorAsPesoInput(amountCents: number): string {
    if (!Number.isSafeInteger(amountCents) || amountCents < 0) {
        throw new RangeError('Amount must be a non-negative integer.');
    }

    const pesos = Math.floor(amountCents / 100);
    const centavos = String(amountCents % 100).padStart(2, '0');

    return `${pesos}.${centavos}`;
}

export function parsePesoAmount(value: string): PesoAmountParseResult {
    const normalized = value.trim();

    if (!/^\d+(?:\.\d{1,2})?$/.test(normalized)) {
        return {
            ok: false,
            error: 'Enter a valid peso amount with up to two decimal places.',
        };
    }

    const [pesoPart, centavoPart = ''] = normalized.split('.');
    const amountCents =
        BigInt(pesoPart) * 100n + BigInt(centavoPart.padEnd(2, '0'));

    if (amountCents > BigInt(Number.MAX_SAFE_INTEGER)) {
        return { ok: false, error: 'Peso amount is too large.' };
    }

    return { ok: true, amountCents: Number(amountCents) };
}

export function upsertFinancialLineItem(
    items: FinancialLineItem[],
    option: FinancialLineItemOption,
    amountCents: number,
): FinancialLineItem[] {
    return [
        ...items.filter((item) => item.fee_rule_id !== option.id),
        {
            fee_rule_id: option.id,
            code: option.code,
            name: option.name,
            amount_cents: amountCents,
            exact_once_key: option.exact_once_key,
            calculation: option.calculation,
        },
    ];
}

export function removeFinancialLineItem(
    items: FinancialLineItem[],
    feeRuleId: number,
): FinancialLineItem[] {
    return items.filter((item) => item.fee_rule_id !== feeRuleId);
}

export function financialLineItemSubtotal(items: FinancialLineItem[]): number {
    return items.reduce((sum, item) => sum + item.amount_cents, 0);
}
