<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Calculator,
    ChartColumn,
    ChartNoAxesColumnIncreasing,
    ClipboardCheck,
    ClipboardX,
    Coins,
    FileText,
    Film,
    FolderGit2,
    LayoutGrid,
    ReceiptText,
    Trophy,
    TableProperties,
    WalletCards,
} from '@lucide/vue';
import { computed } from 'vue';
import { index as citizenPermitApplicationIndex } from '@/actions/App/Http/Controllers/Citizen/PermitApplicationController';
import { index as paymentScheduleIndex } from '@/actions/App/Http/Controllers/Staff/AssessmentPaymentScheduleController';
import { index as assessmentSummaryReportIndex } from '@/actions/App/Http/Controllers/Staff/AssessmentSummaryReportController';
import { index as businessTaxByMajorTypeReportIndex } from '@/actions/App/Http/Controllers/Staff/BusinessTaxByMajorTypeReportController';
import { index as cmciLdcsReportIndex } from '@/actions/App/Http/Controllers/Staff/CmciLdcsReportController';
import { index as collectiblesReportIndex } from '@/actions/App/Http/Controllers/Staff/CollectiblesReportController';
import { index as dailyCollectionReportIndex } from '@/actions/App/Http/Controllers/Staff/DailyCollectionReportController';
import { index as feeRuleIndex } from '@/actions/App/Http/Controllers/Staff/FeeRuleController';
import { index as paidEstablishmentReportIndex } from '@/actions/App/Http/Controllers/Staff/PaidEstablishmentReportController';
import { index as paymentSummaryReportIndex } from '@/actions/App/Http/Controllers/Staff/PaymentSummaryReportController';
import { index as assessmentIndex } from '@/actions/App/Http/Controllers/Staff/PermitApplicationAssessmentController';
import { index as permitApplicationIndex } from '@/actions/App/Http/Controllers/Staff/PermitApplicationController';
import { index as pldsReportIndex } from '@/actions/App/Http/Controllers/Staff/PldsReportController';
import { index as receiptIndex } from '@/actions/App/Http/Controllers/Staff/ReceiptController';
import { index as revenueSourceReportIndex } from '@/actions/App/Http/Controllers/Staff/RevenueSourceReportController';
import { index as storyboardIndex } from '@/actions/App/Http/Controllers/Staff/StoryboardController';
import { index as topEstablishmentTaxDueReportIndex } from '@/actions/App/Http/Controllers/Staff/TopEstablishmentTaxDueReportController';
import { index as totalCapitalGrossSummaryReportIndex } from '@/actions/App/Http/Controllers/Staff/TotalCapitalGrossSummaryReportController';
import { index as unpaidEstablishmentReportIndex } from '@/actions/App/Http/Controllers/Staff/UnpaidEstablishmentReportController';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
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
import type { NavItem } from '@/types';

const page = usePage();

const staffNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Permit Applications',
        href: permitApplicationIndex(),
        icon: FileText,
    },
    {
        title: 'Permit Assessments',
        href: assessmentIndex(),
        icon: Calculator,
    },
    {
        title: 'Payment Schedules',
        href: paymentScheduleIndex(),
        icon: WalletCards,
    },
    {
        title: 'Receipts',
        href: receiptIndex(),
        icon: ReceiptText,
    },
    {
        title: 'Taxes and Fees',
        href: feeRuleIndex(),
        icon: Coins,
    },
    {
        title: 'Daily Collections',
        href: dailyCollectionReportIndex(),
        icon: ChartColumn,
    },
    {
        title: 'Assessment Summary',
        href: assessmentSummaryReportIndex(),
        icon: ChartNoAxesColumnIncreasing,
    },
    {
        title: 'Payment Summary',
        href: paymentSummaryReportIndex(),
        icon: WalletCards,
    },
    {
        title: 'Collectibles',
        href: collectiblesReportIndex(),
        icon: ChartColumn,
    },
    {
        title: 'Tax by Major Type',
        href: businessTaxByMajorTypeReportIndex(),
        icon: ChartNoAxesColumnIncreasing,
    },
    {
        title: 'Capital / Gross',
        href: totalCapitalGrossSummaryReportIndex(),
        icon: ChartColumn,
    },
    {
        title: 'CMCI LDCS',
        href: cmciLdcsReportIndex(),
        icon: TableProperties,
    },
    {
        title: 'PLDS',
        href: pldsReportIndex(),
        icon: TableProperties,
    },
    {
        title: 'Revenue Sources',
        href: revenueSourceReportIndex(),
        icon: ChartNoAxesColumnIncreasing,
    },
    {
        title: 'Paid Establishments',
        href: paidEstablishmentReportIndex(),
        icon: ClipboardCheck,
    },
    {
        title: 'Unpaid Establishments',
        href: unpaidEstablishmentReportIndex(),
        icon: ClipboardX,
    },
    {
        title: 'Top Tax Due',
        href: topEstablishmentTaxDueReportIndex(),
        icon: Trophy,
    },
    {
        title: 'Storyboards',
        href: storyboardIndex(),
        icon: Film,
    },
];

const citizenNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'My Permit Applications',
        href: citizenPermitApplicationIndex(),
        icon: FileText,
    },
];

const authenticatedNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const staffFooterNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];

const mainNavItems = computed(() => {
    if (page.props.auth.can_access_staff) {
        return staffNavItems;
    }

    if (page.props.auth.can_access_citizen) {
        return citizenNavItems;
    }

    return authenticatedNavItems;
});
const footerNavItems = computed(() =>
    page.props.auth.can_access_staff ? staffFooterNavItems : [],
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
