/**
 * Municipal product vocabulary for the Business Permit Evaluator.
 *
 * This module is presentation only. It never re-derives Evaluation
 * readiness, applicability, pricing, or authority: the canonical engines
 * (`App\Evaluation\BusinessPermitEvaluationReadiness`,
 * `BusinessPermitEvaluationResolver`, `App\Assessment\*`) remain the sole
 * source of those facts, and `BusinessPermitEvaluationData` keeps carrying
 * their exact canonical values. What happens here is wording: internal
 * enum values, item keys, and readiness sentences are rendered in the
 * language a municipal user actually speaks, using only facts the typed
 * contract already supplies.
 *
 * Deliberately free of Vue, Inertia, and Wayfinder imports (and of
 * currency formatting) so it can be exercised directly by a plain Node
 * test runner, matching `currentUrlPath.ts`.
 *
 * Two invariants this module must never break:
 * - A recorded zero is a real amount. Only a *missing* pricing basis
 *   suppresses the basis amount; `₱0.00` is still shown for an accepted
 *   zero charge.
 * - Readiness copy stays 1:1 with the canonical readiness issues. No
 *   blocker is invented, merged away, or silently dropped, and no new
 *   lifecycle state is introduced.
 */

const NOT_RECORDED = 'Not recorded';

/** The minimal item shape this module needs from the typed contract. */
export type PresentableEvaluationItem = {
    key: string;
    label: string;
    responsible_party: string;
};

/** The minimal projected-charge shape this module needs. */
export type PresentableProjectedCharge = {
    code: string;
    name: string;
    basis: string;
    basis_amount_cents: number | null;
};

export type PricingBasisSummary = {
    /** Plain-language basis label; never a bare machine token. */
    label: string;
    /**
     * The basis amount to display, or `null` when the fee rule declares no
     * pricing basis at all. `null` means "do not render a basis amount" —
     * it never means the amount is unknown.
     */
    amountCents: number | null;
};

/**
 * Office and responsible-party vocabulary, aligned with the municipal
 * labels already used by `App\Enums\StakeholderPreviewPersona::label()`.
 */
export const officeLabels: Record<string, string> = {
    applicant: 'Applicant',
    citizen: 'Applicant',
    system: 'Municipal system',
    admin: 'Administrator',
    bplo: 'BPLO',
    assessment_officer: 'Assessment Officer',
    treasury: 'Treasury',
    municipal_treasurer: 'Municipal Treasurer',
    cashier: 'Cashier',
    management: 'Management',
    engineering: 'Engineering',
    health: 'Health',
    menro: 'MENRO',
    mpdo: 'MPDO / MPDC',
    assessor: 'Assessor',
    mayor_office: "Mayor's Office",
    releasing: 'Releasing Officer',
};

/** `App\Enums\BusinessPermitEvaluationSource` in municipal language. */
export const sourceLabels: Record<string, string> = {
    applicant_declaration: 'Applicant declaration',
    governed_rule: 'Municipal fee rule',
    configured_municipal_default: 'Configured municipal default',
    governed_office_procedure: 'Office procedure',
    board_operational_recollection: 'Board operational recollection',
    provisional_uat: 'Provisional UAT evidence',
    accepted_municipal_authority: 'Accepted municipal source',
};

/** `App\Enums\BusinessPermitEvaluationRevisionAction`. */
export const revisionActionLabels: Record<string, string> = {
    declaration: 'Applicant declaration',
    proposal: 'System proposal',
    confirmation: 'Office confirmation',
    correction: 'Municipal correction',
    authorized_determination: 'Authorized determination',
    supersession: 'Superseded by a later change',
};

/** `App\Enums\BusinessPermitEvaluationApplicability`. */
export const applicabilityLabels: Record<string, string> = {
    applicable: 'Applicable',
    not_applicable: 'Not applicable',
    undetermined: 'Not yet determined',
};

/** `App\Enums\BusinessPermitEvaluationItemType`. */
export const itemTypeLabels: Record<string, string> = {
    fact: 'Recorded fact',
    determination: 'Municipal determination',
    charge: 'Charge',
};

/** Resolver-computed item resolution states. */
export const resolutionLabels: Record<string, string> = {
    resolved: 'Complete',
    unresolved: 'Awaiting action',
    awaiting_responsible_confirmation: 'Awaiting office confirmation',
    superseded: 'Superseded',
};

export const inspectionModeLabels: Record<string, string> = {
    physical: 'Physical inspection',
    virtual: 'Virtual inspection',
    document_review: 'Document review',
};

/** `App\Enums\PermitApplicationType`. */
export const applicationTypeLabels: Record<string, string> = {
    new: 'New',
    renewal: 'Renewal',
    additional: 'Additional',
    amendment: 'Amendment',
    transfer: 'Transfer',
    retirement: 'Retirement',
};

/**
 * `FeeRule.basis` in municipal language. `none` is a real, legitimate
 * configuration (a flat municipal amount), not an unknown value, so it
 * reads as an absent basis rather than as a zero-peso basis.
 */
export const pricingBasisLabels: Record<string, string> = {
    none: 'No configured pricing basis',
    declared_gross_sales: 'Declared gross sales',
    capital_investment: 'Capital investment',
    resolved_evaluation_contribution: 'Resolved municipal evaluation amount',
    manual_office_assessment: 'Office-assessed amount',
};

/**
 * Last-resort humanizer for an identifier that no dictionary covers.
 * Municipal codes (e.g. `EVAL-UAT-BASE-1`) are preserved verbatim;
 * snake_case/dotted internal identifiers become spaced words so a future
 * unmapped value can never surface as a raw machine token.
 */
export function humanizeIdentifier(value: string): string {
    if (!/[._]/.test(value)) {
        return value;
    }

    const words = value.split(/[._]+/).filter((word) => word.length > 0);

    if (words.length === 0) {
        return value;
    }

    return words
        .map((word, index) =>
            index === 0 ? word.charAt(0).toUpperCase() + word.slice(1) : word,
        )
        .join(' ');
}

function labelFrom(
    labels: Record<string, string>,
    value: string | null | undefined,
    fallback: string = NOT_RECORDED,
): string {
    if (typeof value !== 'string' || value === '') {
        return fallback;
    }

    return labels[value] ?? humanizeIdentifier(value);
}

export function officeLabel(value: string | null | undefined): string {
    return labelFrom(officeLabels, value, 'Concerned office');
}

export function sourceLabel(value: string | null | undefined): string {
    return labelFrom(sourceLabels, value);
}

export function revisionActionLabel(value: string | null | undefined): string {
    return labelFrom(revisionActionLabels, value);
}

export function applicabilityLabel(value: string | null | undefined): string {
    return labelFrom(applicabilityLabels, value);
}

export function itemTypeLabel(value: string | null | undefined): string {
    return labelFrom(itemTypeLabels, value);
}

export function resolutionLabel(value: string | null | undefined): string {
    return labelFrom(resolutionLabels, value);
}

export function inspectionModeLabel(value: string | null | undefined): string {
    return labelFrom(inspectionModeLabels, value);
}

export function applicationTypeLabel(value: string | null | undefined): string {
    return labelFrom(applicationTypeLabels, value);
}

export function pricingBasisLabel(value: string | null | undefined): string {
    return labelFrom(pricingBasisLabels, value, 'No configured pricing basis');
}

/**
 * Decide how one governed system proposal's pricing basis should read.
 *
 * A fee rule with `basis = 'none'` has no basis to compute from, so its
 * structural `basis_amount_cents` of `0` carries no municipal meaning and
 * must not be rendered as `₱0.00` beside the word "none". The charge's own
 * resolved amount is unaffected and is always rendered by the caller.
 */
export function pricingBasisSummary(
    charge: PresentableProjectedCharge,
): PricingBasisSummary {
    const hasConfiguredBasis = charge.basis !== '' && charge.basis !== 'none';

    return {
        label: pricingBasisLabel(charge.basis),
        amountCents: hasConfiguredBasis ? charge.basis_amount_cents : null,
    };
}

/** All dictionaries consulted when de-jargoning a canonical sentence. */
const sentenceTokenLabels: Record<string, string> = {
    ...pricingBasisLabels,
    ...sourceLabels,
    ...resolutionLabels,
    ...applicabilityLabels,
};

function itemFor(
    key: string,
    items: readonly PresentableEvaluationItem[],
): PresentableEvaluationItem | undefined {
    return items.find((item) => item.key === key);
}

function itemLabelFor(
    key: string,
    items: readonly PresentableEvaluationItem[],
): string {
    return itemFor(key, items)?.label ?? humanizeIdentifier(key);
}

function itemOfficeLabel(
    key: string,
    items: readonly PresentableEvaluationItem[],
): string {
    return officeLabel(itemFor(key, items)?.responsible_party);
}

function chargeLabelFor(
    code: string,
    charges: readonly PresentableProjectedCharge[],
): string {
    return charges.find((charge) => charge.code === code)?.name ?? code;
}

/**
 * Strip internal notation out of any canonical sentence: bracketed item
 * keys become the item's own product label, bracketed tokens become their
 * municipal label, and any remaining snake_case token is spaced out. This
 * is the safety net behind the specific readiness rewrites below, so an
 * unrecognized future issue still cannot leak `[health.determination]` or
 * `awaiting_responsible_confirmation` to a stakeholder.
 */
export function plainSentence(
    sentence: string,
    items: readonly PresentableEvaluationItem[] = [],
): string {
    return sentence
        .replace(/\[([^\]]+)\]/g, (_match, content: string) => {
            const item = itemFor(content, items);

            if (item !== undefined) {
                return item.label;
            }

            return sentenceTokenLabels[content] ?? humanizeIdentifier(content);
        })
        .replace(
            /[a-z][a-z0-9]*(?:_[a-z0-9]+)+/g,
            (token) => sentenceTokenLabels[token] ?? token.replace(/_/g, ' '),
        );
}

type ReadinessRewrite = {
    pattern: RegExp;
    render: (
        groups: string[],
        items: readonly PresentableEvaluationItem[],
        charges: readonly PresentableProjectedCharge[],
    ) => string;
};

/**
 * One rewrite per canonical readiness issue emitted by
 * `BusinessPermitEvaluationReadiness::forAssessment()`. Order matters only
 * in that the first match wins; every branch keeps the canonical meaning
 * and adds no new state.
 */
const readinessRewrites: ReadinessRewrite[] = [
    {
        pattern: /^Evaluation has no current version\.$/,
        render: () => 'This Evaluation does not have a current version yet.',
    },
    {
        pattern: /^Required applicant facts are not lodged because /,
        render: () =>
            'The permit application has not been submitted, so the applicant facts are not yet on record.',
    },
    {
        pattern: /^At least one valid resolved Line of Business is required\.$/,
        render: () =>
            'At least one municipal business activity must be resolved for this application.',
    },
    {
        pattern: /^Required applicant financial declarations are /,
        render: () =>
            'The applicant declaration is incomplete. Declared gross sales and capital investment are both required.',
    },
    {
        pattern: /^Required applicability is undetermined for \[([^\]]+)\]\.$/,
        render: ([key], items) =>
            `${itemOfficeLabel(key, items)} has not yet decided whether ${itemLabelFor(key, items)} applies.`,
    },
    {
        pattern: /^Required item \[([^\]]+)\] is ([a-z_]+)\.$/,
        render: ([key, resolution], items) => {
            const office = itemOfficeLabel(key, items);
            const label = itemLabelFor(key, items);

            if (resolution === 'awaiting_responsible_confirmation') {
                return `Awaiting ${office} confirmation: ${label}.`;
            }

            if (resolution === 'superseded') {
                return `${label} must be recorded again by ${office} because the Evaluation changed.`;
            }

            return `Awaiting ${office}: ${label}.`;
        },
    },
    {
        pattern:
            /^Required inspection\/review is incomplete for \[([^\]]+)\]\.$/,
        render: ([key], items) =>
            `${itemOfficeLabel(key, items)} has not completed the inspection or review for ${itemLabelFor(key, items)}.`,
    },
    {
        pattern: /^Targeted return remains unresolved for \[([^\]]+)\]\.$/,
        render: ([key], items) =>
            `A returned item is still open with ${itemOfficeLabel(key, items)}: ${itemLabelFor(key, items)}.`,
    },
    {
        pattern:
            /^Applicable charge \[([^\]]+)\] has no resolved non-negative amount/,
        render: ([key], items) =>
            `${itemOfficeLabel(key, items)} has not recorded an amount for ${itemLabelFor(key, items)}. An unrecorded amount is not ₱0.00.`,
    },
    {
        pattern:
            /^Charge \[([^\]]+)\] duplicates the canonical FeeRule path\.$/,
        render: ([key], items) =>
            `${itemLabelFor(key, items)} repeats a charge that the municipal fee rules already price.`,
    },
    {
        pattern:
            /^Applicable charge \[([^\]]+)\] has no accepted commissioned source or procedure\.$/,
        render: ([key], items) =>
            `${itemLabelFor(key, items)} has no accepted municipal source or procedure, so it cannot carry a real amount due.`,
    },
    {
        pattern:
            /^Applicable charge \[([^\]]+)\] is provisional_uat and cannot establish production liability\.$/,
        render: ([key], items) =>
            `${itemLabelFor(key, items)} rests on provisional UAT evidence, which cannot create a real amount due.`,
    },
    {
        pattern:
            /^Projected charge \[([^\]]+)\] is provisional_uat and cannot establish production liability\.$/,
        render: ([code], _items, charges) =>
            `${chargeLabelFor(code, charges)} rests on provisional UAT evidence, which cannot create a real amount due.`,
    },
    {
        pattern: /^Selected pricing rule is blocked or ambiguous: (.+)$/,
        render: ([detail], items) =>
            `Municipal pricing needs review: ${plainSentence(detail, items)}`,
    },
    {
        pattern: /^Evaluation fingerprint is stale and must be refreshed /,
        render: () =>
            'The Evaluation dependencies changed. Refresh the Evaluation before preparing an Assessment.',
    },
    {
        pattern: /^Required Treasury counter-check is not complete /,
        render: () =>
            'Treasury has not completed the counter-check for this Evaluation version.',
    },
];

/**
 * Render one canonical readiness issue as municipal product language.
 * Unrecognized sentences fall through to `plainSentence()` rather than
 * being dropped, so the stakeholder still sees the blocker.
 */
export function readinessBlocker(
    issue: string,
    items: readonly PresentableEvaluationItem[] = [],
    charges: readonly PresentableProjectedCharge[] = [],
): string {
    for (const rewrite of readinessRewrites) {
        const match = rewrite.pattern.exec(issue);

        if (match !== null) {
            return rewrite.render(match.slice(1), items, charges);
        }
    }

    return plainSentence(issue, items);
}

/**
 * Render the canonical readiness issues as municipal product language,
 * 1:1 and in canonical order. Nothing is added, reordered, or removed:
 * the canonical list stays the only readiness truth.
 */
export function readinessBlockers(
    issues: readonly string[],
    items: readonly PresentableEvaluationItem[] = [],
    charges: readonly PresentableProjectedCharge[] = [],
): string[] {
    return issues.map((issue) => readinessBlocker(issue, items, charges));
}
