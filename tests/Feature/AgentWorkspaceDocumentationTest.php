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

test('Nelson operational feedback remains additive and authority safe', function () {
    $intake = file_get_contents(base_path('docs/agents/NELSON_FEEDBACK_INTAKE.md'));
    $reconciliation = file_get_contents(base_path('docs/implementation/NELSON_OPERATIONAL_FEEDBACK_RECONCILIATION_2026-08-19.md'));
    $approvalPacket = file_get_contents(base_path('docs/implementation/APPROVAL_STAGE_DECISION_PACKET_2026-08-19.md'));
    $parity = file_get_contents(base_path('docs/implementation/PARITY_LEDGER.md'));

    expect($intake)
        ->toContain('NFI-2026-001')
        ->toContain('NFI-2026-007')
        ->toContain('NFI-2026-008')
        ->toContain('Municipal Treasurer')
        ->toContain('Assessment/amount')
        ->toContain('Yes one step, but it needs to be approved by the Municipal Treasurer. Assessment Officer is different from the Treasurer.')
        ->toContain('Returned for correction')
        ->toContain('Does approval clear the applicant to proceed to payment? `Yes`')
        ->toContain('as blanket authority to change semantics')
        ->and($reconciliation)
        ->toContain('EXPOSES MISSING DOMAIN BEHAVIOR')
        ->toContain('Mayor = permit signatory')
        ->toContain('BPLO personnel = operational release actor')
        ->toContain('New Evidence Cycle Completed')
        ->and($approvalPacket)
        ->toContain('RESOLVED FOR IMPLEMENTATION')
        ->toContain('What exactly is approved?')
        ->toContain('How often is approval required?')
        ->and($parity)
        ->toContain('| CAP-018 | Application approval/evaluation queue | BROWSER VERIFIED |');
});

test('Nelson workflow source artifact is immutable registered and authority safe', function () {
    $artifactPath = base_path('docs/sources/operational/NELSON-BUSINESS-PERMIT-WORKFLOW-2026-08-19.JPEG');
    $transcription = file_get_contents(base_path('docs/sources/operational/NELSON_BUSINESS_PERMIT_WORKFLOW_2026-08-19_TRANSCRIPTION.md'));
    $sourceRegister = file_get_contents(base_path('docs/sources/SOURCE_REGISTER.md'));
    $checksums = file_get_contents(base_path('docs/sources/CHECKSUMS.sha256'));
    $reconciliation = file_get_contents(base_path('docs/implementation/NELSON_OPERATIONAL_WORKFLOW_ARTIFACT_RECONCILIATION_2026-08-20.md'));
    $previewPackage = file_get_contents(base_path('docs/implementation/STAKEHOLDER_PREVIEW_READY_PACKAGE.md'));

    expect($artifactPath)
        ->toBeFile()
        ->and(filesize($artifactPath))->toBe(137775)
        ->and(hash_file('sha256', $artifactPath))->toBe('8ccc1209d54cbec32b5d07f492837bc45d2a19ab19bec67cbd7caa734f4c9566')
        ->and(getimagesize($artifactPath))->toMatchArray([1650, 1275])
        ->and($sourceRegister)->toContain('OPERATIONAL-NELSON-001', '8ccc1209d54cbec32b5d07f492837bc45d2a19ab19bec67cbd7caa734f4c9566')
        ->and($checksums)->toContain('8ccc1209d54cbec32b5d07f492837bc45d2a19ab19bec67cbd7caa734f4c9566  docs/sources/operational/NELSON-BUSINESS-PERMIT-WORKFLOW-2026-08-19.JPEG')
        ->and($transcription)
        ->toContain('Paperless Payment Orders from the MPDO, Engineering Office, and Municipal Assessor’s Office.')
        ->toContain('|  | ONE-TIME PAYMENT OF ALL ASSESSED FEES |')
        ->toContain('approved and pushed to the Business Permit Portal for release')
        ->and($reconciliation)
        ->toContain('These sources are consistent, not contradictory.')
        ->toContain('Revenue Code Section 2E.03')
        ->toContain('Warp Product/UI Critic review remains paused')
        ->and($previewPackage)
        ->toContain('STAKEHOLDER PREVIEW SEMANTIC FREEZE PAUSED');

    foreach ([
        'docs/implementation/PRE_ASSESSMENT_PAYMENT_ORDER_DECISION_PACKET_2026-08-20.md',
        'docs/implementation/DOCUMENTARY_AND_CLEARANCE_APPLICABILITY_DECISION_PACKET_2026-08-20.md',
        'docs/implementation/ONE_TIME_PAYMENT_FISCAL_RECONCILIATION_2026-08-20.md',
        'docs/implementation/POST_CLEARANCE_APPROVAL_RELEASE_DECISION_PACKET_2026-08-20.md',
    ] as $decisionPacket) {
        expect(base_path($decisionPacket))->toBeFile();
    }
});
