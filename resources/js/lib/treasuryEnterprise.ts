import type { FinancialLineItem } from './financialLineItems';

export type EnterpriseSchedule = {
    id: string;
    version: string;
    fingerprint: string;
    bands: Record<string, number>;
};

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
