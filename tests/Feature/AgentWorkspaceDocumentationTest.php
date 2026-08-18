<?php

$agentDocuments = [
    'docs/agents/README.md',
    'docs/agents/PROGRAM_CONTEXT.md',
    'docs/agents/WORKSPACE_PROTOCOL.md',
    'docs/agents/MIGRATION_ENGINEER_HANDOFF.md',
    'docs/agents/UI_UX_ENGINEER_HANDOFF.md',
];

test('canonical multi-agent induction documents exist and are linked from root instructions', function () use ($agentDocuments) {
    foreach ($agentDocuments as $document) {
        expect(base_path($document))->toBeFile();
    }

    expect(file_get_contents(base_path('AGENTS.md')))
        ->toContain('docs/agents/README.md')
        ->toContain('Chief Architect and Integrator');
});

test('program context preserves the critical architecture authority and migration boundaries', function () {
    $context = file_get_contents(base_path('docs/agents/PROGRAM_CONTEXT.md'));

    expect($context)
        ->toContain('One Laravel 13 monolith with Vue/Inertia')
        ->toContain('One authoritative assessment calculation path')
        ->toContain('Permit artifact')
        ->toContain('Configured official')
        ->toContain('observed -> inferred -> proposed -> accepted -> rehearsed -> production-applied')
        ->toContain('407 applications')
        ->toContain('816 V1 histories remain outside exact mapping')
        ->toContain('736 require human identity reconciliation')
        ->toContain('Production migration execution and cutover are not authorized')
        ->toContain('Production tells us what happened')
        ->toContain('does not automatically establish what is legally, fiscally, or operationally authorized');
});

test('role handoffs keep specialist ownership separate from business and integration authority', function () {
    $migration = file_get_contents(base_path('docs/agents/MIGRATION_ENGINEER_HANDOFF.md'));
    $ui = file_get_contents(base_path('docs/agents/UI_UX_ENGINEER_HANDOFF.md'));
    $protocol = file_get_contents(base_path('docs/agents/WORKSPACE_PROTOCOL.md'));

    expect($migration)
        ->toContain('Similarity has not established or merged any identity')
        ->toContain('This packet is characterization and proposal preparation')
        ->toContain('It is not mapping acceptance or execution')
        ->and($ui)
        ->toContain('The UI presents the domain. It does not define the domain')
        ->toContain('Tracking reference is never represented as an official application number')
        ->toContain('Artifact verification never implies release or legal effect')
        ->and($protocol)
        ->toContain('only agent that integrates to `main`')
        ->toContain('Never run two agents in the same worktree')
        ->toContain('No credentials in source');
});

test('agent packages reference canonical evidence and contain no known local walkthrough credential', function () use ($agentDocuments) {
    $contents = collect($agentDocuments)
        ->map(fn (string $document): string => file_get_contents(base_path($document)))
        ->implode("\n");

    expect($contents)
        ->toContain('docs/implementation/PARITY_LEDGER.md')
        ->toContain('docs/sources/PRODUCTION_SAFETY.md')
        ->not->toContain('NELSON_WALKTHROUGH_PASSWORD=')
        ->not->toMatch('/password\s*:\s*\S+/i');
});

test('every canonical role reference resolves to a repository file', function () {
    $references = [
        '.ai/rules/actions-console-commands.md',
        '.ai/rules/actions-console-commands-models.md',
        '.ai/rules/commands-models.md',
        '.ai/rules/commands-models-enums.md',
        '.ai/rules/actions.md',
        '.ai/rules/lifecycle-scenarios.md',
        '.ai/rules/pages-reports.md',
        'docs/discovery/SURFACE_INVENTORY.md',
        'docs/discovery/BUSINESS_LIFECYCLE_MAP.md',
        'docs/implementation/HISTORICAL_FINANCIAL_PRESERVATION_BOUNDARY.md',
        'docs/implementation/NEXT_SCALE_HISTORICAL_PRESERVATION_REHEARSAL_AUTHORIZATION_PACKET.md',
        'docs/implementation/PARITY_LEDGER.md',
        'docs/implementation/MILESTONE_SCENARIOS.md',
        'docs/reports/engineering-program-review/2026-08-17-epr-008-production-ground-zero.md',
        'docs/reports/engineering-program-review/2026-08-17-epr-009-production-financial-reconciliation.md',
        'docs/sources/PRODUCTION_SAFETY.md',
        'storyboards/NELSON_WALKTHROUGH.storyboard',
    ];

    foreach ($references as $reference) {
        expect(base_path($reference))->toBeFile();
    }
});
