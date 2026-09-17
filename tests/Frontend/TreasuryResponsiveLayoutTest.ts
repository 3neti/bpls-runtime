import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const component = (name: string) =>
    readFileSync(
        new URL(
            `../../resources/js/components/permit-applications/${name}.vue`,
            import.meta.url,
        ),
        'utf8',
    );
const workspace = component('BploRoutingTaskSheet')
    .split('v-if="isTreasuryActor && task.application.commissioned_path"')[1]
    .split('Confirm Treasury Classification')[0];
const editor = component('FinancialLineItemEditor').split('<template>')[1];

test('Treasury nested grid tracks can shrink inside the 390px viewport instead of relying on outer clipping', () => {
    assert.match(workspace, /grid w-full min-w-0 grid-cols-1/);
    assert.match(workspace, /grid min-w-0 grid-cols-1 gap-3 rounded-lg/);
    assert.match(workspace, /h-11 w-full min-w-0 flex-1/);
    assert.match(workspace, /h-auto min-h-11 w-full min-w-0 whitespace-normal/);
    assert.match(workspace, /max-w-full min-w-0/);
    assert.match(workspace, /<strong class="min-w-0 break-words"/);
});

test('shared resolved and unresolved fee editors constrain native controls and wrap amount rows', () => {
    assert.match(editor, /grid w-full min-w-0 grid-cols-1/);
    assert.match(
        editor,
        /grid min-w-0 grid-cols-1 gap-2 sm:grid-cols-\[minmax\(0,1fr\)_10rem_auto\]/,
    );
    assert.match(editor, /h-9 w-full max-w-full min-w-0/);
    assert.match(editor, /flex min-w-0 flex-wrap items-center justify-between/);
    assert.match(editor, /min-w-0 flex-1 basis-40 break-words/);
    assert.match(editor, /min-w-0 text-right font-black break-words/);
    assert.match(editor, /Known items \(partial\)/);
    assert.match(editor, /resolved \? money\(subtotal\) : 'TBD — incomplete'/);
});
