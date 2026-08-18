import type { InertiaLinkProps } from '@inertiajs/vue3';
import { index as allAbstractReportIndex } from '@/actions/App/Http/Controllers/Staff/AllAbstractReportController';
import { index as annexCDnfbpReportIndex } from '@/actions/App/Http/Controllers/Staff/AnnexCDnfbpReportController';
import { index as assessmentSummaryReportIndex } from '@/actions/App/Http/Controllers/Staff/AssessmentSummaryReportController';
import { index as billingGroupIndex } from '@/actions/App/Http/Controllers/Staff/BillingGroupController';
import { index as bspReportIndex } from '@/actions/App/Http/Controllers/Staff/BspReportController';
import { index as businessTaxByMajorTypeReportIndex } from '@/actions/App/Http/Controllers/Staff/BusinessTaxByMajorTypeReportController';
import { index as cmciLdcsReportIndex } from '@/actions/App/Http/Controllers/Staff/CmciLdcsReportController';
import { index as collectiblesReportIndex } from '@/actions/App/Http/Controllers/Staff/CollectiblesReportController';
import { index as dailyCollectionReportIndex } from '@/actions/App/Http/Controllers/Staff/DailyCollectionReportController';
import { index as paidEstablishmentReportIndex } from '@/actions/App/Http/Controllers/Staff/PaidEstablishmentReportController';
import { index as paymentSummaryReportIndex } from '@/actions/App/Http/Controllers/Staff/PaymentSummaryReportController';
import { index as pldsReportIndex } from '@/actions/App/Http/Controllers/Staff/PldsReportController';
import { index as revenueSourceReportIndex } from '@/actions/App/Http/Controllers/Staff/RevenueSourceReportController';
import { index as topEstablishmentTaxDueReportIndex } from '@/actions/App/Http/Controllers/Staff/TopEstablishmentTaxDueReportController';
import { index as totalCapitalGrossSummaryReportIndex } from '@/actions/App/Http/Controllers/Staff/TotalCapitalGrossSummaryReportController';
import { index as unpaidEstablishmentReportIndex } from '@/actions/App/Http/Controllers/Staff/UnpaidEstablishmentReportController';

export type ReportFamily = 'operational' | 'management' | 'authority_pending';
export type ReportAvailability = 'working' | 'policy_bound';

export type ReportCatalogItem = {
    key: string;
    title: string;
    navigationTitle: string;
    description: string;
    family: ReportFamily;
    availability: ReportAvailability;
    href: NonNullable<InertiaLinkProps['href']>;
    navigation: boolean;
};

export const reportFamilyDetails: Record<
    ReportFamily,
    { title: string; description: string }
> = {
    operational: {
        title: 'Operational reports',
        description:
            'Day-to-day permit, collection, receipt, and outstanding-balance evidence within the implemented scope.',
    },
    management: {
        title: 'Management reports',
        description:
            'Persisted assessment, payment, declaration, and tax evidence organized for review and monitoring.',
    },
    authority_pending: {
        title: 'Authority-pending reports',
        description:
            'Visible report contracts that refuse official rows or exports until the required municipal, Treasury, classification, or permit authority is accepted.',
    },
};

export const reportCatalog: ReportCatalogItem[] = [
    {
        key: 'daily-collections',
        title: 'Daily Collections',
        navigationTitle: 'Daily Collections',
        description:
            'Receipted permit collections by collection date, payer, cashier, payment method, and persisted receipt evidence.',
        family: 'operational',
        availability: 'working',
        href: dailyCollectionReportIndex(),
        navigation: true,
    },
    {
        key: 'revenue-sources',
        title: 'Revenue Sources',
        navigationTitle: 'Revenue Sources',
        description:
            'Receipted permit allocations grouped by persisted fee, tax, or revenue-source line.',
        family: 'operational',
        availability: 'working',
        href: revenueSourceReportIndex(),
        navigation: true,
    },
    {
        key: 'collectibles',
        title: 'Breakdown of Collectibles',
        navigationTitle: 'Breakdown of Collectibles',
        description:
            'Outstanding permit schedules grouped by persisted due-date quarters, with unscheduled balances kept visible.',
        family: 'operational',
        availability: 'working',
        href: collectiblesReportIndex(),
        navigation: true,
    },
    {
        key: 'paid-establishments',
        title: 'Paid Establishments',
        navigationTitle: 'Paid Establishments',
        description:
            'Paid permit-schedule establishments without implying permit issuance, release, or current legal validity.',
        family: 'operational',
        availability: 'working',
        href: paidEstablishmentReportIndex(),
        navigation: true,
    },
    {
        key: 'unpaid-establishments',
        title: 'Unpaid Establishments',
        navigationTitle: 'Unpaid Establishments',
        description:
            'Pending and partially paid permit schedules without declaring delinquency, penalties, or enforceability.',
        family: 'operational',
        availability: 'working',
        href: unpaidEstablishmentReportIndex(),
        navigation: true,
    },
    {
        key: 'assessment-summary',
        title: 'Assessment Summary',
        navigationTitle: 'Assessment Summary',
        description:
            'Current computed, non-superseded assessment snapshots and category totals; liability is never recalculated here.',
        family: 'management',
        availability: 'working',
        href: assessmentSummaryReportIndex(),
        navigation: true,
    },
    {
        key: 'payment-summary',
        title: 'Payment Summary',
        navigationTitle: 'Payment Summary',
        description:
            'Schedule-level assessment, collection, receipt, paid, and outstanding evidence.',
        family: 'management',
        availability: 'working',
        href: paymentSummaryReportIndex(),
        navigation: true,
    },
    {
        key: 'business-tax-by-major-type',
        title: 'Business Tax by Major Type',
        navigationTitle: 'Business Tax by Major Type',
        description:
            'Receipted Tax allocations grouped by the first declared business-activity major category.',
        family: 'management',
        availability: 'working',
        href: businessTaxByMajorTypeReportIndex(),
        navigation: true,
    },
    {
        key: 'total-capital-gross-summary',
        title: 'Total Capital and Gross Summary',
        navigationTitle: 'Total Capital and Gross Summary',
        description:
            'Persisted declaration totals, lifetime receipted payments, balances, and latest receipt evidence.',
        family: 'management',
        availability: 'working',
        href: totalCapitalGrossSummaryReportIndex(),
        navigation: true,
    },
    {
        key: 'top-establishments-tax-due',
        title: 'Top Establishments by Tax Due',
        navigationTitle: 'Top Establishments by Tax Due',
        description:
            'Persisted assessment tax-line totals, not legal delinquency, penalties, or a final taxpayer ranking.',
        family: 'management',
        availability: 'working',
        href: topEstablishmentTaxDueReportIndex(),
        navigation: true,
    },
    {
        key: 'all-abstract',
        title: 'All Abstract of Collection',
        navigationTitle: 'All Abstract',
        description:
            'Refuses partial output until complete Treasury domains, mappings, controls, and reconciliation are accepted.',
        family: 'authority_pending',
        availability: 'policy_bound',
        href: allAbstractReportIndex(),
        navigation: true,
    },
    {
        key: 'billing-group-abstract',
        title: 'Billing Group Abstract',
        navigationTitle: 'Billing Group Abstract',
        description:
            'Choose a provisional billing group to inspect its group-specific official-report refusal boundary.',
        family: 'authority_pending',
        availability: 'policy_bound',
        href: billingGroupIndex(),
        navigation: false,
    },
    {
        key: 'cmci-ldcs',
        title: 'CMCI LDCS Annex B',
        navigationTitle: 'CMCI LDCS Annex B',
        description:
            'Refuses official rows until permit issuance, numbering, signatory, classification, and LGU metadata authority exist.',
        family: 'authority_pending',
        availability: 'policy_bound',
        href: cmciLdcsReportIndex(),
        navigation: true,
    },
    {
        key: 'plds',
        title: 'PLDS',
        navigationTitle: 'PLDS',
        description:
            'Refuses partial official rows while permit authority, issue date, classifications, and missing fields remain unresolved.',
        family: 'authority_pending',
        availability: 'policy_bound',
        href: pldsReportIndex(),
        navigation: true,
    },
    {
        key: 'bsp',
        title: 'BSP Non-Bank Entities',
        navigationTitle: 'BSP Non-Bank Entities',
        description:
            'Refuses rows that would assert unsupported permit authority or regulated non-bank classification.',
        family: 'authority_pending',
        availability: 'policy_bound',
        href: bspReportIndex(),
        navigation: true,
    },
    {
        key: 'annex-c-dnfbp',
        title: 'ANNEX C – DNFBP',
        navigationTitle: 'ANNEX C – DNFBP',
        description:
            'Refuses official output until permit authority, DNFBP classification, reporting scope, and municipal acceptance exist.',
        family: 'authority_pending',
        availability: 'policy_bound',
        href: annexCDnfbpReportIndex(),
        navigation: true,
    },
];

export function reportsForFamily(family: ReportFamily): ReportCatalogItem[] {
    return reportCatalog.filter((report) => report.family === family);
}
