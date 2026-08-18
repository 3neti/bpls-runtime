<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    Building2,
    Calculator,
    ChartColumn,
    ClipboardCheck,
    ClipboardX,
    Coins,
    FileText,
    LayoutDashboard,
    ReceiptText,
    ShieldCheck,
    TableProperties,
    Trophy,
    Users,
    WalletCards,
} from '@lucide/vue';
import { computed } from 'vue';
import { index as citizenNotificationIndex } from '@/actions/App/Http/Controllers/Citizen/NotificationController';
import { index as citizenPermitApplicationIndex } from '@/actions/App/Http/Controllers/Citizen/PermitApplicationController';
import { index as allAbstractReportIndex } from '@/actions/App/Http/Controllers/Staff/AllAbstractReportController';
import { index as annexCDnfbpReportIndex } from '@/actions/App/Http/Controllers/Staff/AnnexCDnfbpReportController';
import { index as paymentScheduleIndex } from '@/actions/App/Http/Controllers/Staff/AssessmentPaymentScheduleController';
import { index as assessmentSummaryReportIndex } from '@/actions/App/Http/Controllers/Staff/AssessmentSummaryReportController';
import { index as billingGroupIndex } from '@/actions/App/Http/Controllers/Staff/BillingGroupController';
import { index as bspReportIndex } from '@/actions/App/Http/Controllers/Staff/BspReportController';
import { index as businessTaxByMajorTypeReportIndex } from '@/actions/App/Http/Controllers/Staff/BusinessTaxByMajorTypeReportController';
import { index as cmciLdcsReportIndex } from '@/actions/App/Http/Controllers/Staff/CmciLdcsReportController';
import { index as collectiblesReportIndex } from '@/actions/App/Http/Controllers/Staff/CollectiblesReportController';
import { index as dailyCollectionReportIndex } from '@/actions/App/Http/Controllers/Staff/DailyCollectionReportController';
import { index as feeRuleIndex } from '@/actions/App/Http/Controllers/Staff/FeeRuleController';
import { index as municipalityConfigurationIndex } from '@/actions/App/Http/Controllers/Staff/MunicipalityConfigurationController';
import { index as paidEstablishmentReportIndex } from '@/actions/App/Http/Controllers/Staff/PaidEstablishmentReportController';
import { index as paymentSummaryReportIndex } from '@/actions/App/Http/Controllers/Staff/PaymentSummaryReportController';
import { index as assessmentIndex } from '@/actions/App/Http/Controllers/Staff/PermitApplicationAssessmentController';
import { index as permitApplicationIndex } from '@/actions/App/Http/Controllers/Staff/PermitApplicationController';
import { index as pldsReportIndex } from '@/actions/App/Http/Controllers/Staff/PldsReportController';
import { index as receiptIndex } from '@/actions/App/Http/Controllers/Staff/ReceiptController';
import { index as revenueSourceReportIndex } from '@/actions/App/Http/Controllers/Staff/RevenueSourceReportController';
import { index as rolePermissionIndex } from '@/actions/App/Http/Controllers/Staff/RolePermissionController';
import { index as topEstablishmentTaxDueReportIndex } from '@/actions/App/Http/Controllers/Staff/TopEstablishmentTaxDueReportController';
import { index as totalCapitalGrossSummaryReportIndex } from '@/actions/App/Http/Controllers/Staff/TotalCapitalGrossSummaryReportController';
import { index as unpaidEstablishmentReportIndex } from '@/actions/App/Http/Controllers/Staff/UnpaidEstablishmentReportController';
import { index as userDirectoryIndex } from '@/actions/App/Http/Controllers/Staff/UserDirectoryController';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem, NavSection } from '@/types';

const page = usePage();

const overviewItem: NavItem = {
    title: 'Overview',
    href: dashboard(),
    icon: LayoutDashboard,
};

const reportItems = {
    dailyCollections: {
        title: 'Daily Collections',
        href: dailyCollectionReportIndex(),
        icon: ChartColumn,
    },
    revenueSources: {
        title: 'Revenue Sources',
        href: revenueSourceReportIndex(),
        icon: ChartColumn,
    },
    collectibles: {
        title: 'Breakdown of Collectibles',
        href: collectiblesReportIndex(),
        icon: ChartColumn,
    },
    paidEstablishments: {
        title: 'Paid Establishments',
        href: paidEstablishmentReportIndex(),
        icon: ClipboardCheck,
    },
    unpaidEstablishments: {
        title: 'Unpaid Establishments',
        href: unpaidEstablishmentReportIndex(),
        icon: ClipboardX,
    },
    assessmentSummary: {
        title: 'Assessment Summary',
        href: assessmentSummaryReportIndex(),
        icon: Calculator,
    },
    paymentSummary: {
        title: 'Payment Summary',
        href: paymentSummaryReportIndex(),
        icon: WalletCards,
    },
    businessTax: {
        title: 'Business Tax by Major Type',
        href: businessTaxByMajorTypeReportIndex(),
        icon: ChartColumn,
    },
    capitalGross: {
        title: 'Total Capital and Gross Summary',
        href: totalCapitalGrossSummaryReportIndex(),
        icon: ChartColumn,
    },
    topTaxDue: {
        title: 'Top Establishments by Tax Due',
        href: topEstablishmentTaxDueReportIndex(),
        icon: Trophy,
    },
    allAbstract: {
        title: 'All Abstract',
        href: allAbstractReportIndex(),
        icon: TableProperties,
    },
    cmci: {
        title: 'CMCI LDCS Annex B',
        href: cmciLdcsReportIndex(),
        icon: TableProperties,
    },
    plds: {
        title: 'PLDS',
        href: pldsReportIndex(),
        icon: TableProperties,
    },
    bsp: {
        title: 'BSP Non-Bank Entities',
        href: bspReportIndex(),
        icon: TableProperties,
    },
    annexC: {
        title: 'ANNEX C–DNFBP',
        href: annexCDnfbpReportIndex(),
        icon: TableProperties,
    },
} satisfies Record<string, NavItem>;

const staffSections = computed<NavSection[]>(() => {
    const sections: NavSection[] = [
        { title: 'Overview', items: [overviewItem] },
    ];

    if (page.props.auth.can_view_permit_applications) {
        sections.push({
            title: 'Applications',
            collapsible: true,
            items: [
                {
                    title: 'All Applications',
                    href: permitApplicationIndex(),
                    icon: FileText,
                },
                {
                    title: 'Assessments',
                    href: assessmentIndex(),
                    icon: Calculator,
                },
            ],
        });
    }

    const treasuryItems: NavItem[] = [];

    if (page.props.auth.can_view_payment_schedules) {
        treasuryItems.push({
            title: 'Payment Schedules',
            href: paymentScheduleIndex(),
            icon: WalletCards,
        });
    }

    if (page.props.auth.can_view_receipts) {
        treasuryItems.push({
            title: 'Receipts',
            href: receiptIndex(),
            icon: ReceiptText,
        });
    }

    if (page.props.auth.can_view_billing_groups) {
        treasuryItems.push({
            title: 'Billing Groups — Policy Pending',
            href: billingGroupIndex(),
            icon: WalletCards,
        });
    }

    if (page.props.auth.can_view_reports) {
        treasuryItems.push(
            reportItems.dailyCollections,
            reportItems.revenueSources,
        );
    }

    if (treasuryItems.length > 0) {
        sections.push({
            title: 'Treasury',
            collapsible: true,
            items: treasuryItems,
        });
    }

    if (page.props.auth.can_view_reports) {
        sections.push(
            {
                title: 'Reports · Operational',
                collapsible: true,
                items: [
                    reportItems.dailyCollections,
                    reportItems.revenueSources,
                    reportItems.collectibles,
                    reportItems.paidEstablishments,
                    reportItems.unpaidEstablishments,
                ],
            },
            {
                title: 'Reports · Management',
                collapsible: true,
                items: [
                    reportItems.assessmentSummary,
                    reportItems.paymentSummary,
                    reportItems.businessTax,
                    reportItems.capitalGross,
                    reportItems.topTaxDue,
                ],
            },
            {
                title: 'Reports · Authority Pending',
                collapsible: true,
                items: [
                    reportItems.allAbstract,
                    reportItems.cmci,
                    reportItems.plds,
                    reportItems.bsp,
                    reportItems.annexC,
                ],
            },
        );
    }

    const administrationItems: NavItem[] = [];

    if (page.props.auth.can_view_fee_rules) {
        administrationItems.push({
            title: 'Taxes & Fees',
            href: feeRuleIndex(),
            icon: Coins,
        });
    }

    if (page.props.auth.can_view_users) {
        administrationItems.push({
            title: 'Users',
            href: userDirectoryIndex(),
            icon: Users,
        });
    }

    if (page.props.auth.can_view_roles) {
        administrationItems.push({
            title: 'Roles & Permissions',
            href: rolePermissionIndex(),
            icon: ShieldCheck,
        });
    }

    if (page.props.auth.can_view_municipality_configuration) {
        administrationItems.push({
            title: 'Municipality & Officials',
            href: municipalityConfigurationIndex(),
            icon: Building2,
        });
    }

    if (administrationItems.length > 0) {
        sections.push({
            title: 'Administration',
            collapsible: true,
            items: administrationItems,
        });
    }

    return sections;
});

const citizenSections: NavSection[] = [
    { title: 'Overview', items: [overviewItem] },
    {
        title: 'Permit Services',
        items: [
            {
                title: 'My Permit Applications',
                href: citizenPermitApplicationIndex(),
                icon: FileText,
            },
            {
                title: 'Notices',
                href: citizenNotificationIndex(),
                icon: Bell,
            },
        ],
    },
];

const authenticatedSections: NavSection[] = [
    { title: 'Overview', items: [overviewItem] },
];

const mainNavSections = computed(() => {
    if (page.props.auth.can_access_staff) {
        return staffSections.value;
    }

    if (page.props.auth.can_access_citizen) {
        return citizenSections;
    }

    return authenticatedSections;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        tooltip="Municipality of Ipil BPLS"
                    >
                        <Link
                            :href="dashboard()"
                            aria-label="Go to BPLS overview"
                        >
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="overflow-y-auto">
            <NavMain :sections="mainNavSections" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
