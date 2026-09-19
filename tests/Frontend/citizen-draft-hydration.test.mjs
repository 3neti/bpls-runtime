import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { parse } from '@vue/compiler-sfc';
import ts from 'typescript';

const source = readFileSync(
    new URL(
        '../../resources/js/pages/permit-applications/Create.vue',
        import.meta.url,
    ),
    'utf8',
);
const script = parse(source).descriptor.scriptSetup.content;
const ast = ts.createSourceFile(
    'Create.ts',
    script,
    ts.ScriptTarget.Latest,
    true,
);
const functions = ast.statements
    .filter(
        (statement) =>
            ts.isFunctionDeclaration(statement) &&
            ['nested', 'text', 'initial'].includes(statement.name?.text),
    )
    .map((statement) => statement.getText(ast))
    .join('\n');
assert.equal(functions.match(/function /g)?.length, 3);
const javascript = ts.transpileModule(functions, {
    compilerOptions: { target: ts.ScriptTarget.ES2022 },
}).outputText;

test('actual Create page initializer reads all canonical house number paths', () => {
    const props = {
        draft: {
            declaration: {
                business_address: {
                    house_or_building_number: '12-B',
                    street: 'Market Road',
                },
                owner_address: { house_or_building_number: '34-C' },
                rental: {
                    lessor: { address: { house_or_building_number: '56-D' } },
                },
            },
        },
    };
    const initial = new Function('props', `${javascript}\nreturn initial;`)(
        props,
    );

    assert.equal(initial('business_address.house_building_number'), '12-B');
    assert.equal(initial('owner_address.house_building_number'), '34-C');
    assert.equal(
        initial('rental.lessor.address.house_building_number'),
        '56-D',
    );
    assert.equal(initial('business_address.street'), 'Market Road');
    assert.equal(
        initial('business_address.unit_number', 'fallback'),
        'fallback',
    );
    assert.equal(initial('business_address.unit_number'), null);
});
