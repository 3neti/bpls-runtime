import type { InternalFeeRule } from '@/types';

/**
 * Municipal price book status taxonomy. This maps already-computed backend
 * facts (source classification, publication status, automatic assessment
 * status, calculation type) onto the plain-language categories municipal
 * staff and citizens need. It never invents or reclassifies pricing evidence
 * — it only chooses how to label facts the backend already established.
 */
export type PricingStatusKey =
    | 'in_force'
    | 'recorded_confirmation_required'
    | 'calculated_during_assessment'
    | 'determined_by_office'
    | 'not_commissioned'
    | 'test_data_hidden';

export type PricingStatusTone = 'green' | 'amber' | 'blue' | 'slate' | 'red';

export type PricingStatusInfo = {
    key: PricingStatusKey;
    label: string;
    tone: PricingStatusTone;
};

const NON_MUNICIPAL_SOURCES = new Set([
    'synthetic',
    'provisional_uat',
    'historical',
    'mock',
    'legacy_evidence_only',
    'lifecycle_test',
    'unclassified',
]);

const RECORDED_SOURCES = new Set([
    'municipal_confirmation_required',
    'accepted_municipal_authority',
]);

export const OFFICE_DETERMINED_STATUS: PricingStatusInfo = {
    key: 'determined_by_office',
    label: 'Determined by Concerned Office',
    tone: 'blue',
};

export const NOT_COMMISSIONED_STATUS: PricingStatusInfo = {
    key: 'not_commissioned',
    label: 'Not Commissioned',
    tone: 'slate',
};

type StatusInput = Pick<
    InternalFeeRule,
    | 'publication_status'
    | 'automatic_assessment_status'
    | 'source_classification'
    | 'calculation_type'
>;

/**
 * Derives the plain-language Price Book status for a single recorded rule.
 */
export function ruleStatus(rule: StatusInput): PricingStatusInfo {
    const usedByAssessment =
        rule.automatic_assessment_status === 'used_by_assessment';

    if (rule.publication_status === 'confirmed_exact' && usedByAssessment) {
        return { key: 'in_force', label: 'In Force', tone: 'green' };
    }

    if (usedByAssessment && rule.calculation_type !== 'fixed') {
        return {
            key: 'calculated_during_assessment',
            label: 'Calculated During Assessment',
            tone: 'blue',
        };
    }

    if (NON_MUNICIPAL_SOURCES.has(rule.source_classification)) {
        return {
            key: 'test_data_hidden',
            label: 'Test data — hidden from citizens',
            tone: 'red',
        };
    }

    if (RECORDED_SOURCES.has(rule.source_classification)) {
        return {
            key: 'recorded_confirmation_required',
            label: 'Recorded — Municipal confirmation required',
            tone: 'amber',
        };
    }

    return NOT_COMMISSIONED_STATUS;
}

export function toneClasses(tone: PricingStatusTone): string {
    switch (tone) {
        case 'green':
            return 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100';
        case 'amber':
            return 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100';
        case 'blue':
            return 'border-sky-300 bg-sky-50 text-sky-900 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100';
        case 'red':
            return 'border-rose-300 bg-rose-50 text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100';
        case 'slate':
        default:
            return 'border-slate-300 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-300';
    }
}

const SOURCE_LABELS: Record<string, string> = {
    accepted_municipal_authority: 'Accepted municipal source',
    municipal_confirmation_required: 'Municipal confirmation required',
    synthetic: 'Synthetic — not publishable',
    provisional_uat: 'Provisional UAT — not publishable',
    historical: 'Historical — not publishable',
    mock: 'Mock — not publishable',
    legacy_evidence_only: 'Legacy evidence only — not publishable',
    lifecycle_test: 'Lifecycle test — not publishable',
    unclassified: 'Unclassified — not publishable',
};

export function sourceClassificationLabel(source: string): string {
    return SOURCE_LABELS[source] ?? 'Unclassified — not publishable';
}

export function money(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
    }).format(amountCents / 100);
}

/**
 * A short, scannable representation of what a rule is currently worth,
 * whichever calculation shape it uses. Never fabricates an amount that
 * is not already present in the recorded evidence.
 */
export function recordedValueLabel(rule: InternalFeeRule): string {
    if (rule.recorded_amount_cents !== null) {
        return money(rule.recorded_amount_cents);
    }

    if (rule.range_count > 0) {
        return `${rule.range_count}-bracket schedule`;
    }

    if (rule.rate_basis_points !== null) {
        return `${rule.rate_basis_points / 100}% of ${rule.basis.replaceAll('_', ' ')}`;
    }

    return 'No publishable amount';
}
