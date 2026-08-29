import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type { ConcernedOfficeCode } from '@/lib/price-book';

export type PriceBookLens = 'service' | 'fee' | 'lineOfBusiness' | 'office';

export type PriceBookAudience = {
    key: string;
    title: string;
    message: string;
    lenses: PriceBookLens[];
    defaultLens: PriceBookLens;
    /** Hides secondary evidence fields (evidence version, source classification) by default. */
    concise: boolean;
    /** The concerned office to pin first, when known. Advisory only — never used for authorization. */
    emphasizedOfficeCode: ConcernedOfficeCode | null;
};

const CONCERNED_OFFICE_PERSONAS: ConcernedOfficeCode[] = [
    'engineering',
    'mpdo',
    'assessor',
    'health',
    'menro',
];

/**
 * Derives a role-aware Price Book composition profile.
 *
 * Capability flags (`page.props.auth.can_*`) are real, permission-backed
 * authorization facts and drive every decision that matters for what
 * evidence is safe to show. The stakeholder-preview persona hint is used
 * only as a non-authoritative default (which lens opens first, which
 * concerned office is pinned to the top) — exactly like its existing use
 * elsewhere in the app (sidebar "My Work" section, audience guidance
 * copy). It never grants or hides a capability.
 *
 * Today's permission model cannot distinguish BPLO from a concerned
 * office, Mayor's Office, or the Releasing Officer in production — all
 * four currently share only `staff.access` + `permit_applications.view`.
 * Those roles fall back to one shared, safe "generic staff" profile
 * unless the (UAT-only) preview persona hint is present to refine it.
 */
export function usePriceBookAudience(): ComputedRef<PriceBookAudience> {
    const page = usePage();

    return computed<PriceBookAudience>(() => {
        const auth = page.props.auth;
        const persona = page.props.stakeholder_preview?.current_persona ?? null;

        const isManagement =
            auth.can_view_users ||
            auth.can_view_roles ||
            auth.can_view_municipality_configuration;
        const isTreasury = auth.can_counter_check_business_permit_evaluations;
        const isMunicipalTreasurer = auth.can_approve_assessments;
        const isAssessmentOfficer = auth.can_assess_permit_applications;
        const isCashier =
            !isTreasury &&
            (auth.can_record_collections || auth.can_issue_receipts);
        const isBplo =
            !isManagement &&
            !isTreasury &&
            !isMunicipalTreasurer &&
            !isAssessmentOfficer &&
            !isCashier &&
            auth.can_view_fee_rules &&
            auth.can_view_payment_schedules;

        if (isManagement) {
            return {
                key: 'management',
                title: 'Management view',
                message:
                    'The broadest Price Book view: what BPLS tells the public, what is in force, what is recorded but unconfirmed, and where pricing configuration is still incomplete.',
                lenses: ['fee', 'service', 'lineOfBusiness', 'office'],
                defaultLens: 'fee',
                concise: false,
                emphasizedOfficeCode: null,
            };
        }

        if (isTreasury) {
            return {
                key: 'treasury',
                title: 'Treasury view',
                message:
                    'Fiscal control view: what is currently in force, what is recorded and awaiting confirmation, and what may not yet enter Assessment.',
                lenses: ['fee', 'lineOfBusiness', 'service'],
                defaultLens: 'fee',
                concise: false,
                emphasizedOfficeCode: null,
            };
        }

        if (isMunicipalTreasurer) {
            return {
                key: 'municipal_treasurer',
                title: 'Municipal Treasurer view',
                message:
                    'Approval context for the exact immutable Assessment: confirmed prices and blocked pricing remain visible, without turning the Price Book into an approval or Evaluation surface.',
                lenses: ['fee', 'lineOfBusiness', 'service'],
                defaultLens: 'fee',
                concise: false,
                emphasizedOfficeCode: null,
            };
        }

        if (isAssessmentOfficer) {
            return {
                key: 'assessment_officer',
                title: 'Assessment Officer view',
                message:
                    'What Assessment can actually use today, and what pricing knowledge exists but is not yet safe to execute.',
                lenses: ['fee', 'lineOfBusiness', 'service'],
                defaultLens: 'fee',
                concise: false,
                emphasizedOfficeCode: null,
            };
        }

        if (isCashier) {
            return {
                key: 'cashier',
                title: 'Cashier view',
                message:
                    'Recognizable charge names and their current accepted prices — the detail behind a collection, without reconciliation machinery.',
                lenses: ['fee'],
                defaultLens: 'fee',
                concise: true,
                emphasizedOfficeCode: null,
            };
        }

        if (persona === 'mayor_office') {
            return {
                key: 'mayor_office',
                title: "Mayor's Office view",
                message:
                    'What the Municipality is currently representing to citizens, and where pricing still needs executive or municipal attention.',
                lenses: ['service', 'fee'],
                defaultLens: 'service',
                concise: true,
                emphasizedOfficeCode: null,
            };
        }

        if (persona === 'releasing') {
            return {
                key: 'releasing',
                title: 'Releasing Officer view',
                message:
                    'A concise summary of what an applicant was charged for, without Revenue Code reconciliation detail.',
                lenses: ['service'],
                defaultLens: 'service',
                concise: true,
                emphasizedOfficeCode: null,
            };
        }

        if (
            persona !== null &&
            (CONCERNED_OFFICE_PERSONAS as string[]).includes(persona)
        ) {
            return {
                key: 'concerned_office',
                title: 'Concerned-office view',
                message:
                    'Your office is shown first. Office-determined charges are a category only — provisional walkthrough amounts are never municipal prices and never appear here.',
                lenses: ['office', 'service'],
                defaultLens: 'office',
                concise: true,
                emphasizedOfficeCode: persona as ConcernedOfficeCode,
            };
        }

        if (isBplo) {
            return {
                key: 'bplo',
                title: 'BPLO view',
                message:
                    'What each service publishes, what assessment selects, and where unresolved pricing may affect processing.',
                lenses: ['service', 'lineOfBusiness'],
                defaultLens: 'service',
                concise: false,
                emphasizedOfficeCode: null,
            };
        }

        return {
            key: 'staff',
            title: 'Staff view',
            message:
                'Compare the public statement with the exact rules selected by assessment. Blocked or ambiguous rules remain visible without being published as confirmed prices.',
            lenses: ['service', 'fee'],
            defaultLens: 'service',
            concise: false,
            emphasizedOfficeCode: null,
        };
    });
}
