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

test('ordinary draft guidance does not require a Classic invitation', () => {
    assert.doesNotMatch(
        source,
        /the guide requires 2025 New|started through a Classic invitation|keep this draft and ask BPLO/,
    );
    assert.match(
        source,
        /Review your details, upload documents, then sign and/,
    );
    assert.match(
        source,
        /<option disabled value="">\s*Select occupancy\s*<\/option>/,
    );
});

test('ordinary prefill respects occupancy and keeps rental fields consistent', () => {
    const handler = source.slice(
        source.indexOf('function fillExampleDetails('),
        source.indexOf('const walkthroughFilledCount'),
    );
    const clear = source.slice(
        source.indexOf('function clearWalkthroughExample('),
        source.indexOf('const savesCitizenDraft'),
    );
    const make = new Function(
        'HTMLInputElement',
        'HTMLSelectElement',
        'HTMLTextAreaElement',
        'fields',
        transpile(`
        const canFillExampleDetails = {value: true};
        const labIntakeFixture = {value: {fields}};
        let walkthroughFilledControls = []; const filledControls = [];
        const walkthroughFilledCount = {value: 0}; const walkthroughFillNotice = {value: ''};
        ${controls} ${handler} ${clear}
        return {fill: fillExampleDetails, clear: clearWalkthroughExample};
    `),
    );

    for (const choice of ['', 'owned', 'rented']) {
        const helper = make(Input, Select, Textarea, {
            monthly_rental_pesos: 900,
            lessor_last_name: 'Sample',
            occupancy: 'rented',
        });
        const occupancy = new Select('occupancy', choice);
        const rental = new Input('monthly_rental_pesos', '');
        const lessor = new Input('lessor_last_name', '');
        helper.fill({
            currentTarget: {
                closest: () => ({ elements: [occupancy, rental, lessor] }),
            },
        });
        assert.equal(occupancy.value, choice || 'rented');
        assert.equal(rental.value, choice === 'owned' ? '' : '900');
        assert.equal(lessor.value, choice === 'owned' ? '' : 'Sample');
        helper.clear();
        assert.equal(occupancy.value, choice);
        assert.equal(rental.value, '');
        assert.equal(lessor.value, '');
    }

    const helper = make(Input, Select, Textarea, {
        occupancy: 'owned',
        monthly_rental_pesos: 900,
        lessor_last_name: 'Sample',
    });
    const occupancy = new Select('occupancy', '');
    const rental = new Input('monthly_rental_pesos', '');
    const lessor = new Input('lessor_last_name', '');
    helper.fill({
        currentTarget: {
            closest: () => ({ elements: [occupancy, rental, lessor] }),
        },
    });
    assert.equal(occupancy.value, 'owned');
    assert.equal(rental.value, '');
    assert.equal(lessor.value, '');
});

test('ordinary example is limited to fresh preview Citizen new applications', () => {
    const guard = source.slice(
        source.indexOf('const canFillExampleDetails ='),
        source.indexOf('function fillExampleDetails('),
    );
    const enabled = new Function(
        'isCitizen',
        'isEditing',
        'props',
        'selectedType',
        'page',
        `const computed = (getter) => getter; ${guard}; return canFillExampleDetails();`,
    );
    const evaluate = (
        citizen = true,
        editing = false,
        props = {},
        type = 'new',
        preview = true,
    ) =>
        enabled(
            { value: citizen },
            { value: editing },
            props,
            { value: type },
            { props: { stakeholder_preview: { enabled: preview } } },
        );
    assert.equal(evaluate(), true);
    assert.equal(evaluate(false), false);
    assert.equal(evaluate(true, true), false);
    assert.equal(evaluate(true, false, { cleanroomIntake: {} }), false);
    assert.equal(evaluate(true, false, { walkthroughExample: {} }), false);
    assert.equal(evaluate(true, false, {}, 'renewal'), false);
    assert.equal(evaluate(true, false, {}, 'new', false), false);
});

test('ordinary example preserves entered fields and cannot populate fiscal or lodging controls', () => {
    const handler = source.slice(
        source.indexOf('function fillExampleDetails('),
        source.indexOf('const walkthroughFilledCount'),
    );
    const run = new Function(
        'HTMLInputElement',
        'HTMLSelectElement',
        'HTMLTextAreaElement',
        'allowed',
        transpile(`
        const canFillExampleDetails = { value: allowed };
        const labIntakeFixture = { value: { fields: { business_name: 'Selected specimen', business_street: '117 Sample Market Road' }, lines: [{ line_of_business_id: 999 }] } };
        const walkthroughFilledControls = []; const filledControls = [];
        const walkthroughFilledCount = { value: 0 }; const walkthroughFillNotice = { value: '' };
        ${controls} ${handler}
        return fillExampleDetails;
    `),
    );
    const business = new Input('business_name', 'My real entry');
    const street = new Input('business_street', '');
    const protectedNames = [
        'application_year',
        'type',
        'lifecycle_cleanroom_run_id',
        'undertaking_accepted',
        'signature',
        'mayors_permit_fee',
    ];
    const protectedControls = protectedNames.map((name) => new Input(name, ''));
    const form = { elements: [business, street, ...protectedControls] };
    const event = { currentTarget: { closest: () => form } };
    run(Input, Select, Textarea, false)(event);
    assert.equal(street.value, '');
    run(Input, Select, Textarea, true)(event);
    assert.equal(business.value, 'My real entry');
    assert.equal(street.value, '117 Sample Market Road');

    for (const control of protectedControls) {
        assert.equal(control.value, '');
    }

    assert.doesNotMatch(handler, /\.post\(|\.submit\(|fetch\(|replaceControl/);
    assert.match(source, /id="ordinary-specimen"/);
    assert.match(handler, /labIntakeFixture.value.fields/);
    assert.doesNotMatch(
        handler,
        /fixture\.lines|activities\.value|loadedLabFixtureId/,
    );
    assert.doesNotMatch(source, /Start 2025 Classic walkthrough/);
});

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
