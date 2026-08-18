import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    isCurrentOrParentPath,
    isExactCurrentPath,
    normalizePathname,
    resolvePathname,
} from '../../resources/js/lib/currentUrlPath.ts';

test('exact match compares normalized pathnames', () => {
    assert.equal(isExactCurrentPath('/staff/reports', '/staff/reports'), true);
    assert.equal(isExactCurrentPath('/staff/reports', '/staff/other'), false);
});

test('a parent matches a slash-delimited descendant', () => {
    assert.equal(
        isCurrentOrParentPath('/staff/reports', '/staff/reports/123'),
        true,
    );
    assert.equal(
        isCurrentOrParentPath('/staff/reports', '/staff/reports/123/edit'),
        true,
    );
    assert.equal(
        isCurrentOrParentPath('/staff/reports', '/staff/reports'),
        true,
    );
});

test('prefix siblings must not match', () => {
    assert.equal(
        isCurrentOrParentPath('/staff/reports', '/staff/reports-archive'),
        false,
    );
    assert.equal(
        isExactCurrentPath('/staff/reports', '/staff/reports-archive'),
        false,
    );
});

test('query strings do not affect active state', () => {
    assert.equal(
        isExactCurrentPath('/staff/reports?page=2', '/staff/reports'),
        true,
    );
    assert.equal(
        isExactCurrentPath('/staff/reports', '/staff/reports?page=2'),
        true,
    );
    assert.equal(
        isCurrentOrParentPath('/staff/reports?page=2', '/staff/reports/123'),
        true,
    );
});

test('URL fragments do not affect active state', () => {
    assert.equal(
        isExactCurrentPath('/staff/reports#section', '/staff/reports'),
        true,
    );
    assert.equal(
        isExactCurrentPath('/staff/reports', '/staff/reports#section'),
        true,
    );
});

test('trailing slashes normalize consistently', () => {
    assert.equal(normalizePathname('/staff/reports/'), '/staff/reports');
    assert.equal(normalizePathname('/staff/reports'), '/staff/reports');
    assert.equal(isExactCurrentPath('/staff/reports/', '/staff/reports'), true);
    assert.equal(isExactCurrentPath('/staff/reports', '/staff/reports/'), true);
});

test('root remains / and is not the parent of every route', () => {
    assert.equal(normalizePathname(''), '/');
    assert.equal(normalizePathname('/'), '/');
    assert.equal(isExactCurrentPath('/', '/'), true);
    assert.equal(isCurrentOrParentPath('/', '/'), true);
    assert.equal(isCurrentOrParentPath('/', '/dashboard'), false);
    assert.equal(isCurrentOrParentPath('/', '/staff/reports'), false);
});

test('absolute URLs remain pathname-based', () => {
    assert.equal(
        isExactCurrentPath(
            'https://example.test/staff/reports?x=1#y',
            '/staff/reports',
        ),
        true,
    );
    assert.equal(
        isCurrentOrParentPath(
            'https://example.test/staff/reports',
            '/staff/reports/123',
        ),
        true,
    );
    assert.equal(
        isExactCurrentPath(
            'https://example.test/staff/reports-archive',
            '/staff/reports',
        ),
        false,
    );
});

test('malformed targets return false instead of throwing', () => {
    assert.equal(resolvePathname(undefined), null);
    assert.equal(resolvePathname(null), null);
    assert.equal(resolvePathname(''), null);
    assert.equal(resolvePathname('http://'), null);
    assert.equal(isExactCurrentPath(undefined, '/dashboard'), false);
    assert.equal(isExactCurrentPath(null, '/dashboard'), false);
    assert.equal(isExactCurrentPath('', '/dashboard'), false);
    assert.equal(isCurrentOrParentPath('http://', '/dashboard'), false);
});
