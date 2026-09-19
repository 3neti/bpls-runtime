import type { FinancialLineItem } from './financialLineItems';

export type EnterpriseSchedule = {
    id: string;
    version: string;
    fingerprint: string;
    bands: Record<string, number>;
};

export function treasuryConfirmationReason(
    selections: {
        items: FinancialLineItem[];
        requiresEnterpriseClassification?: boolean;
        enterpriseClassification?: string;
        enterpriseFeeId?: number;
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
                !selection.enterpriseClassification,
        )
    ) {
        reasons.push(
            'Choose Enterprise Classification only for each selected LOB that shows a provisional UAT schedule and has an established test basis. This is separate from the official Line of Business and the applicant’s activity description.',
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
            `Pricing determination required: ${unresolved.map((item) => `${item.name} (${item.code})${item.resolution_message ? ' — ' + item.resolution_message : ''}`).join('; ')}. No authorized pricing determination is available for these unresolved items in this screen. Stop for municipal policy/configuration review; do not enter, remove or override an amount.`,
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
