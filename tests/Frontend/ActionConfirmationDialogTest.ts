import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { parse } from '@vue/compiler-sfc';
import { transpile } from 'typescript';
import { computed, ref } from 'vue';

const source = readFileSync(
    'resources/js/components/ActionConfirmationDialog.vue',
    'utf8',
);
function dialog() {
    const script = parse(source)
        .descriptor.scriptSetup!.content.replace(
            /^import[\s\S]*?from ['"][^'"]+['"];\s*/gm,
            '',
        )
        .replace(/defineProps<[\s\S]*?>\(\);/, '');
    class Element {
        isConnected = true;
        focused = false;
        focus() {
            this.focused = true;
        }
    }
    const element = new Element();
    let cleanup = () => {};
    const state = new Function(
        'ref',
        'onBeforeUnmount',
        'defineExpose',
        'document',
        'HTMLElement',
        transpile(script) + '\nreturn { ask, answer, restoreFocus, open };',
    )(
        ref,
        (fn: () => void) => {
            cleanup = fn;
        },
        () => {},
        { activeElement: element },
        Element,
    );

    return { ...state, cleanup, element };
}

test('confirmation resolves once and refuses overlapping prompts', async () => {
    const d = dialog();
    const first = d.ask('Issue?', 'Separate release follows.', 'Issue');
    assert.equal(d.open.value, true);
    assert.equal(await d.ask('Duplicate?', '', ''), false);
    d.answer(true);
    d.answer(false);
    assert.equal(await first, true);
    assert.equal(d.open.value, false);
});

test('cancel and unmount never authorize an action', async () => {
    const d = dialog();
    const cancelled = d.ask('Prepare?', '', 'Prepare');
    d.answer(false);
    assert.equal(await cancelled, false);
    const abandoned = d.ask('Prepare?', '', 'Prepare');
    d.cleanup();
    assert.equal(await abandoned, false);
    assert.match(source, /if \(!value\) answer\(false\)/);
});

test('closing restores focus to the connected initiating control', () => {
    const d = dialog();
    d.ask('Prepare?', '', 'Prepare');
    let prevented = false;
    d.restoreFocus({
        preventDefault() {
            prevented = true;
        },
    });
    assert.equal(prevented, true);
    assert.equal(d.element.focused, true);
    d.answer(false);
});

test('ordinary Permit presentation separates authorization, issuance and release and preserves blockers', () => {
    const script = parse(
        readFileSync('resources/js/pages/ordinary-uat-permit/Show.vue', 'utf8'),
    ).descriptor.scriptSetup!.content;
    const create = new Function(
        'computed',
        'props',
        transpile(
            script.slice(
                script.indexOf('const labels'),
                script.indexOf('async function submit'),
            ),
        ) + '\nreturn { heading, status, blockers, summary, date };',
    );
    const props = {
        completion: null as any,
        readiness: {
            prerequisites: {
                canonical_collection: true,
                complete_receipt_coverage: false,
                official_receipt_totals_reconcile: true,
                all_required_post_payment_certifications: false,
            },
        },
    };
    const p = create(computed, props);
    assert.equal(p.heading.value, 'Mayoral Authorization');
    assert.equal(p.summary.value[1][1], false);
    assert.equal(p.blockers.value.length, 2);
    assert.equal(
        p.date('2026-09-20T14:36:06+00:00'),
        new Date('2026-09-20T14:36:06+00:00').toLocaleString('en-PH', {
            timeZone: 'Asia/Manila',
            dateStyle: 'medium',
            timeStyle: 'short',
        }),
    );

    for (const [completion, expected] of [
        [{ authorized_at: 'date' }, 'Authorized · Awaiting issuance'],
        [
            { authorized_at: 'date', issued_at: 'date' },
            'Issued · Awaiting BPLO release',
        ],
        [
            { authorized_at: 'date', issued_at: 'date', released_at: 'date' },
            'Released',
        ],
    ] as const) {
        assert.equal(
            create(computed, { ...props, completion }).status.value,
            expected,
        );
    }
});

test('evaluation confirmations retain stale-review and one-at-a-time safeguards', () => {
    const evaluation = readFileSync(
        'resources/js/pages/business-permit-evaluations/Show.vue',
        'utf8',
    );
    assert.doesNotMatch(evaluation, /window\.confirm/);
    assert.match(
        evaluation,
        /fingerprint !== props\.evaluation\?\.version\.fingerprint/,
    );
    assert.match(evaluation, /if \(pendingAction\.value\)\s*\{?\s*return false/);
    assert.match(
        evaluation,
        /evaluation_fingerprint: props\.evaluation!\.version\.fingerprint/,
    );
});
