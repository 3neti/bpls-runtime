/**
 * Framework-agnostic active-state path comparison.
 *
 * This module is intentionally free of Vue, Inertia, and Wayfinder imports
 * so it can be exercised directly by a plain Node test runner. Wayfinder
 * route-object hrefs are converted to plain strings upstream by the
 * existing `toUrl()` conversion boundary in `@/lib/utils` before reaching
 * these functions; callers here only ever deal with strings.
 *
 * Fixed semantics implemented here:
 * - Exact matching compares normalized pathnames.
 * - Query strings and URL fragments never affect active state.
 * - A parent matches itself or a slash-delimited descendant.
 * - Prefix siblings (e.g. `/staff/reports-archive`) never match
 *   `/staff/reports`.
 * - Trailing slashes normalize consistently; root remains `/`.
 * - Root never becomes the parent of every route.
 * - Absolute URLs remain pathname-based.
 * - Malformed targets resolve to `null`/`false` rather than throwing.
 */

const PLACEHOLDER_ORIGIN = 'http://localhost';

/**
 * Normalize a pathname so trailing slashes compare consistently while
 * preserving the root path as exactly `/`.
 */
export function normalizePathname(pathname: string): string {
    if (pathname === '') {
        return '/';
    }

    if (pathname.length > 1 && pathname.endsWith('/')) {
        return pathname.slice(0, -1);
    }

    return pathname;
}

/**
 * Resolve an href (relative or absolute, with or without a query string or
 * fragment) to a normalized pathname. Returns `null` for malformed or
 * empty targets instead of throwing.
 */
export function resolvePathname(
    href: string | null | undefined,
): string | null {
    if (typeof href !== 'string' || href.length === 0) {
        return null;
    }

    try {
        const url = new URL(href, PLACEHOLDER_ORIGIN);

        return normalizePathname(url.pathname);
    } catch {
        return null;
    }
}

/**
 * Resolve the pathname used for comparison on the "current" side. The
 * current pathname is normally already clean (derived from `page.url`),
 * but a query string or fragment is stripped defensively so both sides of
 * every comparison are held to the same fixed semantics.
 */
function resolveCurrentPathname(currentPathname: string): string {
    return (
        resolvePathname(currentPathname) ?? normalizePathname(currentPathname)
    );
}

/**
 * True when the target href's normalized pathname exactly matches the
 * current pathname. Query strings and fragments never affect the result.
 */
export function isExactCurrentPath(
    href: string | null | undefined,
    currentPathname: string,
): boolean {
    const targetPathname = resolvePathname(href);

    if (targetPathname === null) {
        return false;
    }

    return targetPathname === resolveCurrentPathname(currentPathname);
}

/**
 * True when the target href's normalized pathname is the current pathname
 * itself, or a slash-delimited ancestor of it. Root (`/`) only matches
 * root; it never matches every route. Prefix siblings that merely share
 * characters (e.g. `/staff/reports` vs. `/staff/reports-archive`) never
 * match because the descendant check requires a literal `/` boundary.
 */
export function isCurrentOrParentPath(
    href: string | null | undefined,
    currentPathname: string,
): boolean {
    const targetPathname = resolvePathname(href);

    if (targetPathname === null) {
        return false;
    }

    const normalizedCurrent = resolveCurrentPathname(currentPathname);

    if (targetPathname === normalizedCurrent) {
        return true;
    }

    if (targetPathname === '/') {
        return false;
    }

    return normalizedCurrent.startsWith(`${targetPathname}/`);
}
