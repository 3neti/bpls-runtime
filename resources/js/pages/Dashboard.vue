<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    Calculator,
    ChevronRight,
    ClipboardList,
    FilePlus2,
    Landmark,
    Settings2,
} from '@lucide/vue';
import { computed } from 'vue';
import { index as citizenNotificationIndex } from '@/actions/App/Http/Controllers/Citizen/NotificationController';
import { index as citizenPermitApplicationIndex } from '@/actions/App/Http/Controllers/Citizen/PermitApplicationController';
import { index as paymentScheduleIndex } from '@/actions/App/Http/Controllers/Staff/AssessmentPaymentScheduleController';
import { index as billingGroupIndex } from '@/actions/App/Http/Controllers/Staff/BillingGroupController';
import { index as dailyCollectionReportIndex } from '@/actions/App/Http/Controllers/Staff/DailyCollectionReportController';
import { index as feeRuleIndex } from '@/actions/App/Http/Controllers/Staff/FeeRuleController';
import { index as municipalityConfigurationIndex } from '@/actions/App/Http/Controllers/Staff/MunicipalityConfigurationController';
import { index as assessmentIndex } from '@/actions/App/Http/Controllers/Staff/PermitApplicationAssessmentController';
import { index as permitApplicationIndex } from '@/actions/App/Http/Controllers/Staff/PermitApplicationController';
import { index as receiptIndex } from '@/actions/App/Http/Controllers/Staff/ReceiptController';
import { index as rolePermissionIndex } from '@/actions/App/Http/Controllers/Staff/RolePermissionController';
import { index as userDirectoryIndex } from '@/actions/App/Http/Controllers/Staff/UserDirectoryController';
import PageHeader from '@/components/PageHeader.vue';
import ScopeBoundaryNotice from '@/components/ScopeBoundaryNotice.vue';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Overview',
                href: dashboard(),
            },
        ],
    },
});

const page = usePage();

type DashboardAction = NavItem & {
    description: string;
};

const staffActions = computed<DashboardAction[]>(() => {
    const actions: DashboardAction[] = [];

    if (page.props.auth.can_view_permit_applications) {
        actions.push(
            {
                title: 'Applications',
                description: 'Review the municipal application work queue.',
                href: permitApplicationIndex(),
                icon: ClipboardList,
            },
            {
                title: 'Assessment Work',
                description:
                    'Open applications that are ready for assessment review.',
                href: assessmentIndex(),
                icon: Calculator,
            },
        );
    }

    const treasuryHref = page.props.auth.can_view_payment_schedules
        ? paymentScheduleIndex()
        : page.props.auth.can_view_receipts
          ? receiptIndex()
          : page.props.auth.can_view_billing_groups
            ? billingGroupIndex()
            : page.props.auth.can_view_reports
              ? dailyCollectionReportIndex()
              : null;

    if (treasuryHref !== null) {
        actions.push({
            title: 'Treasury Work',
            description:
                'Open the first Treasury or collection area available to your account.',
            href: treasuryHref,
            icon: Landmark,
        });
    }

    if (page.props.auth.can_view_reports) {
        actions.push({
            title: 'Reports',
            description: 'Open an available operational report.',
            href: dailyCollectionReportIndex(),
            icon: ClipboardList,
        });
    }

    const administrationHref = page.props.auth.can_view_fee_rules
        ? feeRuleIndex()
        : page.props.auth.can_view_users
          ? userDirectoryIndex()
          : page.props.auth.can_view_roles
            ? rolePermissionIndex()
            : page.props.auth.can_view_municipality_configuration
              ? municipalityConfigurationIndex()
              : null;

    if (administrationHref !== null) {
        actions.push({
            title: 'Administration',
            description:
                'Open the first administration area available to your account.',
            href: administrationHref,
            icon: Settings2,
        });
    }

    return actions;
});

const citizenActions: DashboardAction[] = [
    {
        title: 'Start/Continue Application',
        description: 'Begin a new draft or return to your permit applications.',
        href: citizenPermitApplicationIndex(),
        icon: FilePlus2,
    },
    {
        title: 'Track Municipal Processing',
        description:
            'Follow the status and recorded progress of your applications.',
        href: citizenPermitApplicationIndex(),
        icon: ClipboardList,
    },
    {
        title: 'Notices',
        description: 'Read notices recorded for your account.',
        href: citizenNotificationIndex(),
        icon: Bell,
    },
];

const isStaff = computed(() => page.props.auth.can_access_staff);
const isCitizen = computed(() => page.props.auth.can_access_citizen);
const dashboardActions = computed(() =>
    isStaff.value ? staffActions.value : isCitizen.value ? citizenActions : [],
);
const noActionsMessage = computed(() =>
    isStaff.value
        ? 'No staff work areas are currently available to this account.'
        : 'This account does not currently have access to a BPLS work area.',
);
const previewGuidance = computed(
    () => page.props.stakeholder_preview?.what_to_try ?? [],
);
</script>

<template>
    <Head title="Overview" />

    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <PageHeader
            eyebrow="Municipality of Ipil"
            :title="isStaff ? 'BPLS staff overview' : 'Your BPLS overview'"
            :description="
                isStaff
                    ? 'Continue the permit application, assessment, Treasury, reporting, and administration work available to your account.'
                    : 'Apply for a business permit, follow municipal processing, and read notices in one place.'
            "
        />

        <section aria-labelledby="available-work-heading" class="space-y-3">
            <div>
                <h2 id="available-work-heading" class="text-lg font-semibold">
                    {{ isStaff ? 'Available work' : 'Permit services' }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    Only services available to your account are shown.
                </p>
            </div>

            <div
                v-if="dashboardActions.length > 0"
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
            >
                <Link
                    v-for="action in dashboardActions"
                    :key="action.title"
                    :href="action.href"
                    class="group flex min-h-36 flex-col justify-between gap-4 rounded-xl border bg-card p-5 text-card-foreground shadow-xs transition outline-none hover:border-foreground/20 hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <div class="flex items-start justify-between gap-3">
                        <component
                            :is="action.icon"
                            class="size-5 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <ChevronRight
                            class="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                            aria-hidden="true"
                        />
                    </div>
                    <div class="space-y-1">
                        <h3 class="font-semibold">{{ action.title }}</h3>
                        <p class="text-sm leading-5 text-muted-foreground">
                            {{ action.description }}
                        </p>
                    </div>
                </Link>
            </div>

            <p
                v-else
                class="rounded-xl border bg-card p-5 text-sm text-muted-foreground"
            >
                {{ noActionsMessage }}
            </p>
        </section>

        <section
            v-if="page.props.stakeholder_preview?.current_persona"
            aria-labelledby="preview-guidance-heading"
            class="space-y-3 rounded-xl border border-amber-300 bg-amber-50 p-5 text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-50"
        >
            <div>
                <p class="text-xs font-semibold tracking-wide uppercase">
                    {{ page.props.stakeholder_preview.current_label }} preview
                </p>
                <h2
                    id="preview-guidance-heading"
                    class="mt-1 text-lg font-semibold"
                >
                    What to try
                </h2>
            </div>
            <ul class="grid gap-2 sm:grid-cols-2">
                <li v-for="item in previewGuidance" :key="item.href">
                    <Link
                        :href="item.href"
                        class="flex items-center justify-between gap-3 rounded-lg border border-amber-300 bg-white/70 px-3 py-2.5 text-sm font-semibold outline-none hover:bg-white focus-visible:ring-2 focus-visible:ring-amber-700 dark:border-amber-800 dark:bg-amber-950/70 dark:hover:bg-amber-950"
                    >
                        {{ item.label }}
                        <ChevronRight class="size-4" aria-hidden="true" />
                    </Link>
                </li>
            </ul>
            <p class="text-xs text-amber-800 dark:text-amber-200">
                {{ page.props.stakeholder_preview.recovery_message }}
            </p>
        </section>

        <ScopeBoundaryNotice />
    </main>
</template>
