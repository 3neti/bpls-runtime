import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { compile } from '@vue/compiler-dom';
import { parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import * as Vue from 'vue';

const source = (path: string) =>
    readFileSync(
        new URL(`../../resources/js/${path}`, import.meta.url),
        'utf8',
    );

async function renderBanner(showEngineeringControls: boolean): Promise<string> {
    const { descriptor } = parse(
        source('components/StakeholderPreviewBanner.vue'),
    );
    const { code } = compile(descriptor.template!.content, {
        mode: 'function',
        prefixIdentifiers: true,
    });
    const render = new Function('Vue', code)(Vue);
    const app = Vue.createSSRApp({
        render,
        components: {
            ShieldAlert: { render: () => null },
            ArrowRight: { render: () => null },
        },
        data: () => ({
            preview: {
                enabled: true,
                show_engineering_controls: showEngineeringControls,
                authorized_legacy_review: false,
                current_label: null,
                current_persona: null,
                cleanroom_actor: {
                    label: 'BPLO Intake',
                    laboratory_url: '/laboratory',
                },
                personas: [],
            },
            switchingTo: null,
            returnToLaboratory: () => {},
        }),
    });

    return renderToString(app);
}

test('ordinary walkthrough actors render no preview or laboratory banner even if stale context remains', async () => {
    const html = await renderBanner(false);
    assert.doesNotMatch(
        html,
        /Preview Environment|Cleanroom|Continue in Laboratory/,
    );
    assert.doesNotMatch(html, /stakeholder-preview-banner/);
});

test('approved preview operators retain the banner and laboratory return control', async () => {
    const html = await renderBanner(true);
    assert.match(html, /Preview Environment/);
    assert.match(html, /Continue in Laboratory/);
});

test('ordinary intake and application workspace engineering sections use the same presentation gate', () => {
    const intake = source('pages/permit-applications/Create.vue');
    const workspace = source(
        'pages/stakeholder-preview/LifecycleApplication.vue',
    );
    assert.match(
        intake,
        /v-if="[\s\S]*?show_engineering_controls[\s\S]*?"\s+data-testid="lifecycle-cleanroom-intake"/,
    );
    assert.match(
        intake,
        /v-if="[\s\S]*?show_engineering_controls[\s\S]*?"\s+data-testid="permit-application-lab-helper"/,
    );
    assert.match(
        workspace,
        /v-if="page.props.stakeholder_preview\?\.show_engineering_controls"/,
    );
    assert.match(intake, /name="lifecycle_cleanroom_run_id"/);
});

test('Citizen invitation keeps its canonical input with ordinary applicant-facing wording', () => {
    const registration = source('pages/auth/Register.vue');
    assert.match(registration, /name="classic_cleanroom_invitation"/);
    assert.match(
        registration,
        /Create the Citizen account that will submit your Application/,
    );
    assert.doesNotMatch(
        registration,
        /Classic Lifecycle Laboratory|this cleanroom/,
    );
});
