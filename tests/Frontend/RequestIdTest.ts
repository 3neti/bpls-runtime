import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createRequestId } from '../../resources/js/lib/requestId.ts';

test('request identity is UUID v4 compatible', () => {
    assert.match(
        createRequestId(),
        /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/,
    );
});

test('assessment page uses shared request identity helper and clears preparation failures', async () => {
    const source = await import('node:fs').then(({ readFileSync }) =>
        readFileSync(
            'resources/js/pages/business-permit-evaluations/Show.vue',
            'utf8',
        ),
    );
    assert.equal(source.includes('crypto.randomUUID()'), false);
    assert.equal((source.match(/createRequestId\(\)/g) ?? []).length, 4);
    assert.match(source, /actionError\.value\s*=\s*error instanceof Error/);
});
