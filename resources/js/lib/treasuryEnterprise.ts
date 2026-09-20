import type { FinancialLineItem } from './financialLineItems';

export type EnterpriseSchedule = {
    id: string;
    version: string;
    fingerprint: string;
    bands: Record<string, number>;
    manual_determination_available?: boolean;
};

export type ManualTreasuryDetermination = {
    amount_cents: number;
    basis: string;
};

export function manualTreasuryDetermination(
    amount: string,
    basis: string,
): ManualTreasuryDetermination | null {
    if (
        !/^\d+(\.\d{1,2})?$/.test(amount.trim()) ||
        !basis.trim() ||
        basis.length > 1000
    ) {
        return null;
    }

    const [whole, fraction = ''] = amount.trim().split('.');
    const cents = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));

    return Number.isSafeInteger(cents) && cents > 0 && cents <= 1000000000
        ? { amount_cents: cents, basis: basis.trim() }
        : null;
}

export function applyManualTreasuryDetermination(
    items: FinancialLineItem[],
    feeId: number,
    determination: ManualTreasuryDetermination | null,
): FinancialLineItem[] {
    return items.map((item) =>
        item.fee_rule_id !== feeId
            ? item
            : {
                  ...item,
                  amount_locked: true,
                  amount_cents: determination?.amount_cents ?? 0,
                  resolution_status: determination ? 'resolved' : 'unresolved',
                  resolution_message: determination
                      ? 'Manual test amount — saved with Confirm Treasury'
                      : 'TBD — Treasury determination required',
              },
    );
}

export function treasuryConfirmationReason(
    selections: {
        items: FinancialLineItem[];
        requiresEnterpriseClassification?: boolean;
        enterpriseClassification?: string;
        enterpriseFeeId?: number;
        manualDetermination?: boolean;
    }[],
    pending: boolean,
): string {
    if (pending) {
        return 'Treasury confirmation is being submitted.';
    }

    if (selections.length === 0) {
        return 'Select an official Line of Business.';
    }

    const reasons: string[] = [];

    if (
        selections.some(
            (selection) =>
                selection.requiresEnterpriseClassification &&
                !selection.manualDetermination &&
                !selection.enterpriseClassification,
        )
    ) {
        reasons.push(
            'Choose Enterprise Classification using an established test basis—not a target amount—or use Determine Mayor’s Permit Fee where local/UAT manual determination is offered. Otherwise ask the authorized municipal official to confirm the classification. The required fee cannot be removed.',
        );
    }

    const unresolved = selections.flatMap((selection) =>
        selection.items.filter(
            (item) =>
                item.resolution_status === 'unresolved' &&
                !(
                    selection.requiresEnterpriseClassification &&
                    !selection.enterpriseClassification &&
                    item.fee_rule_id === selection.enterpriseFeeId
                ),
        ),
    );

    if (unresolved.length > 0) {
        reasons.push(
            `Pricing determination required: ${unresolved.map((item) => `${item.name} (${item.code})${item.resolution_message ? ' — ' + item.resolution_message : ''}`).join('; ')}. No authorized pricing determination is available here. Ask the authorized municipal official to confirm the applicable fee basis, then have the administrator verify its configuration. Required fees cannot be deleted to bypass pricing. Stop for municipal policy/configuration review; do not enter, remove or override an amount.`,
        );
    }

    if (reasons.length > 0) {
        return reasons.join(' ');
    }

    if (!selections.some((selection) => selection.items.length > 0)) {
        return 'Add the required payment items.';
    }

    return '';
}

export function applyEnterpriseClassification(
    items: FinancialLineItem[],
    feeId: number,
    schedule: EnterpriseSchedule,
    classification: string,
): FinancialLineItem[] {
    const amount = Object.hasOwn(schedule.bands, classification)
        ? schedule.bands[classification]
        : undefined;

    return items.map((item) =>
        item.fee_rule_id !== feeId
            ? item
            : {
                  ...item,
                  amount_locked: true,
                  amount_cents: amount ?? 0,
                  resolution_status:
                      amount === undefined ? 'unresolved' : 'resolved',
                  resolution_message:
                      amount === undefined
                          ? 'TBD — enterprise classification required'
                          : 'Provisional UAT schedule — pending Ipil Officer confirmation',
              },
    );
}
