import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { ref } from 'vue';

const form = readFileSync(
    new URL(
        '../../resources/js/pages/permit-applications/Create.vue',
        import.meta.url,
    ),
    'utf8',
);
const initializer = form.match(/const occupancy = ref\(([\s\S]*?)\n\);/)?.[1];
assert.ok(initializer);

function occupancyState(
    declared: boolean | undefined,
    registry?: string,
    cleanroomValue?: string,
) {
    const props = { draft: { occupancy: registry } };
    const state = new Function(
        'ref',
        'nested',
        'props',
        'cleanroom',
        `return ref(${initializer});`,
    )(
        ref,
        () => declared,
        props,
        () => cleanroomValue,
    );

    return { state, props };
}

test('occupancy uses saved declaration before registry or cleanroom defaults', () => {
    assert.equal(occupancyState(true, 'owned').state.value, 'rented');
    assert.equal(
        occupancyState(false, 'rented', 'rented').state.value,
        'owned',
    );
    assert.equal(occupancyState(undefined, 'rented').state.value, 'rented');
    assert.equal(
        occupancyState(undefined, undefined, 'rented').state.value,
        'rented',
    );
    assert.equal(occupancyState(undefined).state.value, 'owned');
});

test('explicit occupancy selection survives subsequent prop refreshes', () => {
    const { state, props } = occupancyState(undefined, 'owned');
    state.value = 'rented';
    props.draft.occupancy = 'owned';
    assert.equal(state.value, 'rented');
    assert.match(form, /v-model="occupancy"/);
    assert.doesNotMatch(form, /watch\(occupancy/);
});

test('ordinary form has one labelled canonical occupancy select and visible error slot', () => {
    assert.equal((form.match(/name="occupancy"/g) ?? []).length, 1);
    assert.match(
        form,
        /<label[\s\S]*?for="occupancy"[\s\S]*?>Occupancy<\/label>|for="occupancy"[\s\S]*?Occupancy/,
    );
    assert.match(form, /<option value="owned">Owned<\/option>/);
    assert.match(form, /<option value="rented">Rented<\/option>/);
    assert.match(form, /:message="errors.occupancy"/);
});
