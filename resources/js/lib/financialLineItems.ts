export type FinancialLineItemOption = {
    resolution_status?: 'resolved' | 'unresolved';
    resolution_message?: string | null;
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
    amount_locked?: boolean;
    resolution_status?: 'resolved' | 'unresolved';
    resolution_message?: string | null;
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

export function financialFeeOptionLabel(
    option: FinancialLineItemOption,
    options: FinancialLineItemOption[],
): string {
    const sameName = options.filter(
        (candidate) => candidate.name === option.name,
    );

    if (sameName.length < 2) {
        return option.name;
    }

    const basis = option.calculation?.explanation;
    const distinctBasis =
        basis &&
        sameName.filter(
            (candidate) => candidate.calculation?.explanation === basis,
        ).length === 1;

    const identity =
        sameName.filter((candidate) => candidate.code === option.code).length >
        1
            ? `${option.code} · Fee #${option.id}`
            : option.code;

    return `${option.name} — ${distinctBasis ? basis : identity}`;
}

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
            resolution_status: option.resolution_status,
            resolution_message: option.resolution_message,
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
    return items.reduce(
        (sum, item) =>
            sum +
            (item.resolution_status === 'unresolved' ? 0 : item.amount_cents),
        0,
    );
}

export function financialLineItemsResolved(
    items: FinancialLineItem[],
): boolean {
    return items.every((item) => item.resolution_status !== 'unresolved');
}
