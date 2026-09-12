import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { computed, ref } from 'vue';

const form = readFileSync(
    new URL(
        '../../resources/js/pages/permit-applications/Create.vue',
        import.meta.url,
    ),
    'utf8',
);
const initializer = form.match(
    /const savesCitizenDraft = computed\(([\s\S]*?)\n\);/,
)?.[1];
assert.ok(initializer);

function draftIntent(
    citizen: boolean,
    editing: boolean,
    cleanroom: boolean,
    staged: boolean,
): boolean {
    return new Function(
        'computed',
        'isCitizen',
        'isEditing',
        'props',
        'usesStagedCitizenIntake',
        `return computed(${initializer}).value;`,
    )(
        computed,
        ref(citizen),
        ref(editing),
        { cleanroomIntake: cleanroom ? {} : null },
        ref(staged),
    );
}

test('ordinary create and existing Draft do not require the Oath to save', () => {
    assert.equal(draftIntent(true, false, false, false), true);
    assert.equal(draftIntent(true, true, false, false), true);
    assert.equal(draftIntent(true, false, true, true), true);
    assert.match(
        form,
        /:required="\s*!savesCitizenDraft\s*&&\s*!isCommissionedApplication\s*"/,
    );
});

test('one-action legacy cleanroom lodging and staff intent retain their existing Oath rule', () => {
    assert.equal(draftIntent(true, false, true, false), false);
    assert.equal(draftIntent(false, false, false, false), false);
});

test('Sign and Submit keeps separate fresh Oath and signature gates and document invalidation', () => {
    assert.match(
        form,
        /!props.draft\s*\|\|\s*!submissionForm.undertaking_accepted\s*\|\|\s*!submissionForm.signature_facsimile/,
    );
    assert.match(
        form,
        /submissionForm.post\(citizenSubmit.url\(props.draft.id\)/,
    );
    assert.match(form, /submissionForm.undertaking_accepted = false/);
    assert.match(form, /submissionForm.signature_facsimile = null/);
});
