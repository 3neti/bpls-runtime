import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { transpile } from 'typescript';

const source = readFileSync(
    'resources/js/pages/permit-applications/Create.vue',
    'utf8',
);
const controls = source.slice(
    source.indexOf('function namedControls('),
    source.indexOf('function replaceControl('),
);
class Input {
    checked = false;
    events: string[] = [];
    name: string;
    value: string;
    type: string;
    constructor(name: string, value: string, type = 'text') {
        this.name = name;
        this.value = value;
        this.type = type;
    }
    dispatchEvent(event: Event) {
        this.events.push(event.type);
    }
}
class Select extends Input {}
class Textarea extends Input {}

test('walkthrough filling retains nonblank entries and zero, ignores hidden fields, and fills blanks', () => {
    const fill = new Function(
        'HTMLInputElement',
        'HTMLSelectElement',
        'HTMLTextAreaElement',
        transpile(
            `const filledControls = []; ${controls}; return fillEmptyControl;`,
        ),
    )(Input, Select, Textarea);
    const name = new Input('business_name', 'My entered business');
    const area = new Input('business_area_square_meters', '0', 'number');
    const street = new Input('business_street', '');
    const year = new Input('application_year', '', 'hidden');
    const form = { elements: [name, area, street, year] };
    fill(form, 'business_name', 'Example');
    fill(form, 'business_area_square_meters', 12);
    fill(form, 'business_street', 'Synthetic address');
    fill(form, 'application_year', 2025);
    assert.equal(name.value, 'My entered business');
    assert.equal(area.value, '0');
    assert.equal(street.value, 'Synthetic address');
    assert.deepEqual(street.events, ['input', 'change']);
    assert.equal(year.value, '');
});

test('walkthrough fill handler is create-only, server-payload-only and does not submit', () => {
    const handler = source.slice(
        source.indexOf('function fillWalkthroughExample('),
        source.indexOf('const savesCitizenDraft'),
    );
    assert.match(handler, /!props.walkthroughExample/);
    assert.match(handler, /isEditing.value/);
    assert.match(handler, /!isCitizen.value/);
    assert.match(handler, /Object.entries\(props.walkthroughExample\)/);
    assert.match(
        handler,
        /fillEmptyControl\(form, name, value, walkthroughFilledControls\)/,
    );
    assert.doesNotMatch(
        handler,
        /replaceControl|\.post\(|\.put\(|\.submit\(|fetch\(/,
    );
    assert.match(source, /Fill walkthrough example/);
});

test('clear restores only unchanged compact-helper assignments, retaining user edits', () => {
    const clear = source.slice(
        source.indexOf('function clearWalkthroughExample('),
        source.indexOf('const savesCitizenDraft'),
    );
    const make = new Function(
        'HTMLInputElement',
        'HTMLSelectElement',
        'HTMLTextAreaElement',
        transpile(
            `const filledControls = []; let walkthroughFilledControls = [];
        const walkthroughFilledCount = {value: 0}; const walkthroughFillNotice = {value: ''};
        ${controls} ${clear}
        return {fill: (form, name, value) => fillEmptyControl(form, name, value, walkthroughFilledControls), clear: clearWalkthroughExample};`,
        ),
    );
    const helper = make(Input, Select, Textarea);
    const business = new Input('business_street', '');
    const owner = new Input('owner_street', '');
    const form = { elements: [business, owner] };
    helper.fill(form, 'business_street', 'Helper business address');
    helper.fill(form, 'owner_street', 'Helper owner address');
    owner.value = 'My correction';
    helper.clear();
    assert.equal(business.value, '');
    assert.equal(owner.value, 'My correction');
});
