<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    BookOpenText,
    Building2,
    Calculator,
    ChartColumn,
    Coins,
    FileText,
    LayoutDashboard,
    ReceiptText,
    ShieldCheck,
    TableProperties,
    Users,
    WalletCards,
} from '@lucide/vue';
import { computed } from 'vue';
import { index as citizenNotificationIndex } from '@/actions/App/Http/Controllers/Citizen/NotificationController';
import { index as citizenPermitApplicationIndex } from '@/actions/App/Http/Controllers/Citizen/PermitApplicationController';
import CitizenProfileController from '@/actions/App/Http/Controllers/Citizen/ProfileController';
import { index as paymentScheduleIndex } from '@/actions/App/Http/Controllers/Staff/AssessmentPaymentScheduleController';
import { index as billingGroupIndex } from '@/actions/App/Http/Controllers/Staff/BillingGroupController';
import { index as feeRuleIndex } from '@/actions/App/Http/Controllers/Staff/FeeRuleController';
import { index as municipalityConfigurationIndex } from '@/actions/App/Http/Controllers/Staff/MunicipalityConfigurationController';
import { index as assessmentIndex } from '@/actions/App/Http/Controllers/Staff/PermitApplicationAssessmentController';
import { index as permitApplicationIndex } from '@/actions/App/Http/Controllers/Staff/PermitApplicationController';
import { index as receiptIndex } from '@/actions/App/Http/Controllers/Staff/ReceiptController';
import { index as reportCatalogIndex } from '@/actions/App/Http/Controllers/Staff/ReportCatalogController';
import { index as rolePermissionIndex } from '@/actions/App/Http/Controllers/Staff/RolePermissionController';
import { index as userDirectoryIndex } from '@/actions/App/Http/Controllers/Staff/UserDirectoryController';
import { index as previewWorkflowIndex } from '@/actions/App/Http/Controllers/StakeholderPreviewWorkflowController';
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
import { reportCatalog } from '@/lib/reportCatalog';
import { dashboard } from '@/routes';
import { index as publicServiceCatalogIndex } from '@/routes/services-and-fees';
import { index as staffServiceCatalogIndex } from '@/routes/staff/services-and-fees';
import type { NavItem, NavSection } from '@/types';

const page = usePage();

const overviewItem: NavItem = {
    title: 'Overview',
    href: dashboard(),
    icon: LayoutDashboard,
};

const publicServicesAndFeesItem: NavItem = {
    title: 'Services & Fees',
    href: publicServiceCatalogIndex(),
    icon: BookOpenText,
};

const staffServicesAndFeesItem: NavItem = {
    title: 'Services & Fees',
    href: staffServiceCatalogIndex(),
    icon: BookOpenText,
};

const reportItems = Object.fromEntries(
    reportCatalog
        .filter((report) => report.navigation)
        .map((report) => [
            report.key,
            {
                title: report.navigationTitle,
                href: report.href,
                icon:
                    report.family === 'authority_pending'
                        ? TableProperties
                        : ChartColumn,
            },
        ]),
) as Record<string, NavItem>;

const staffSections = computed<NavSection[]>(() => {
    const sections: NavSection[] = [
        {
            title: 'Overview',
            items: [overviewItem, staffServicesAndFeesItem],
        },
    ];

    if (
        page.props.stakeholder_preview?.current_persona &&
        [
            'engineering',
            'mpdo',
            'assessor',
            'health',
            'menro',
            'mayor_office',
            'releasing',
        ].includes(page.props.stakeholder_preview.current_persona)
    ) {
        sections.push({
            title: 'My Work',
            items: [
                {
                    title:
                        page.props.stakeholder_preview.current_label ??
                        'Office Workspace',
                    href: previewWorkflowIndex(),
                    icon: Building2,
                },
            ],
        });
    }

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

    if (page.props.auth.can_view_reports) {
        treasuryItems.push(
            reportItems['daily-collections'],
            reportItems['revenue-sources'],
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
                    {
                        title: 'Report Catalog',
                        href: reportCatalogIndex(),
                        icon: ChartColumn,
                    },
                    reportItems['daily-collections'],
                    reportItems['revenue-sources'],
                    reportItems.collectibles,
                    reportItems['paid-establishments'],
                    reportItems['unpaid-establishments'],
                ],
            },
            {
                title: 'Reports · Management',
                collapsible: true,
                items: [
                    reportItems['assessment-summary'],
                    reportItems['payment-summary'],
                    reportItems['business-tax-by-major-type'],
                    reportItems['total-capital-gross-summary'],
                    reportItems['top-establishments-tax-due'],
                ],
            },
            {
                title: 'Reports · Authority Pending',
                collapsible: true,
                items: [
                    reportItems['all-abstract'],
                    reportItems['cmci-ldcs'],
                    reportItems.plds,
                    reportItems.bsp,
                    reportItems['annex-c-dnfbp'],
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

    if (page.props.auth.can_view_billing_groups) {
        administrationItems.push({
            title: 'Billing Groups — Policy Pending',
            href: billingGroupIndex(),
            icon: WalletCards,
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
    {
        title: 'Overview',
        items: [overviewItem, publicServicesAndFeesItem],
    },
    {
        title: 'Permit Services',
        items: [
            {
                title: 'My Businesses',
                href: CitizenProfileController(),
                icon: Building2,
            },
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
    {
        title: 'Overview',
        items: [overviewItem, publicServicesAndFeesItem],
    },
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
