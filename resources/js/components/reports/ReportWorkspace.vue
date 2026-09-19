<template>
    <main
        class="report-workspace flex h-full max-w-full min-w-0 flex-1 flex-col gap-4 p-4"
    >
        <slot />
    </main>
</template>

<style scoped>
.report-workspace > :deep(*) {
    min-width: 0;
    max-width: 100%;
}
.report-workspace :deep(.report-totals) {
    gap: 0;
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    overflow: hidden;
}
.report-workspace :deep(.report-totals > div) {
    border: 0;
    border-radius: 0;
    padding: 0.75rem 1rem;
}
.report-workspace :deep(table) {
    font-variant-numeric: tabular-nums;
}
.report-workspace :deep(thead) {
    background: color-mix(in srgb, var(--primary) 9%, var(--background));
    color: var(--foreground);
}
.report-workspace :deep(th) {
    text-align: left;
    vertical-align: middle;
}
.report-workspace :deep(th.text-right) {
    text-align: right;
}
.report-workspace :deep(th),
.report-workspace :deep(td) {
    padding: 0.625rem 0.75rem;
    border-bottom: 1px solid var(--border);
}
.report-workspace :deep(tbody tr:nth-child(even)) {
    background: color-mix(in srgb, var(--muted) 30%, var(--background));
}
.report-workspace :deep(tbody tr:hover) {
    background: var(--muted);
}
</style>
