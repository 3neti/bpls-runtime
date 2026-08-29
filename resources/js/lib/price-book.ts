import { ruleStatus } from '@/lib/pricing-status';
import type {
    InternalFeeRule,
    LineOfBusinessReference,
    MunicipalPriceList,
} from '@/types';

/**
 * These are stable organizational names, not pricing data. No amount for a
 * concerned office ever comes from this list — every amount still comes
 * from the one governed Price Book projection (or is explicitly absent).
 */
export type ConcernedOfficeCode =
    'engineering' | 'mpdo' | 'assessor' | 'health' | 'menro';

export const CONCERNED_OFFICES: { code: ConcernedOfficeCode; label: string }[] =
    [
        { code: 'engineering', label: 'Engineering' },
        { code: 'mpdo', label: 'MPDO / MPDC' },
        { code: 'assessor', label: 'Assessor' },
        { code: 'health', label: 'Health' },
        { code: 'menro', label: 'MENRO' },
    ];

export type FeeMenuEntry = InternalFeeRule & {
    appliesToServices: string[];
};

/**
 * Flattens the per-service recorded rules and Line-of-Business schedules
 * into one deduplicated "By Fee" list. This is pure presentation
 * aggregation over data the backend already resolved and classified — it
 * does not recompute, reclassify, or invent any pricing fact.
 */
export function collectFeeMenuEntries(
    priceList: MunicipalPriceList,
): FeeMenuEntry[] {
    const byId = new Map<number, FeeMenuEntry>();

    for (const service of priceList.services) {
        const entries = [
            ...(service.internal?.rules ?? []),
            ...(service.internal?.line_of_business_pricing ?? []),
        ];

        for (const rule of entries) {
            const existing = byId.get(rule.id);

            if (existing) {
                if (!existing.appliesToServices.includes(service.name)) {
                    existing.appliesToServices.push(service.name);
                }

                continue;
            }

            byId.set(rule.id, { ...rule, appliesToServices: [service.name] });
        }
    }

    const statusPriority: Record<string, number> = {
        in_force: 0,
        calculated_during_assessment: 1,
        recorded_confirmation_required: 2,
        not_commissioned: 3,
        test_data_hidden: 4,
    };

    return Array.from(byId.values()).sort((a, b) => {
        const priorityDiff =
            (statusPriority[ruleStatus(a).key] ?? 5) -
            (statusPriority[ruleStatus(b).key] ?? 5);

        return priorityDiff !== 0 ? priorityDiff : a.name.localeCompare(b.name);
    });
}

/**
 * Flattens Line-of-Business schedules across every service into one
 * deduplicated "By Line of Business" list.
 */
export function collectLineOfBusinessEntries(
    priceList: MunicipalPriceList,
): FeeMenuEntry[] {
    return collectFeeMenuEntries(priceList).filter(
        (
            entry,
        ): entry is FeeMenuEntry & {
            line_of_business: LineOfBusinessReference;
        } => entry.line_of_business !== null,
    );
}
