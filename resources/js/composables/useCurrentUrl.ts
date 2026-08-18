import type { InertiaLinkProps } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import type { ComputedRef, DeepReadonly } from 'vue';
import { computed, readonly } from 'vue';
import {
    isCurrentOrParentPath,
    isExactCurrentPath,
    normalizePathname,
} from '@/lib/currentUrlPath';
import { toUrl } from '@/lib/utils';

export type UseCurrentUrlReturn = {
    currentUrl: DeepReadonly<ComputedRef<string>>;
    isCurrentUrl: (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
        startsWith?: boolean,
    ) => boolean;
    isCurrentOrParentUrl: (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
    ) => boolean;
    whenCurrentUrl: <T, F = null>(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        ifTrue: T,
        ifFalse?: F,
    ) => T | F;
};

const page = usePage();
const currentUrlReactive = computed(() =>
    normalizePathname(
        new URL(
            page.url,
            typeof window !== 'undefined'
                ? window.location.origin
                : 'http://localhost',
        ).pathname,
    ),
);

export function useCurrentUrl(): UseCurrentUrlReturn {
    function isCurrentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
        startsWith: boolean = false,
    ) {
        const urlToCompare = currentUrl ?? currentUrlReactive.value;
        const hrefString = toUrl(urlToCheck);

        return startsWith
            ? isCurrentOrParentPath(hrefString, urlToCompare)
            : isExactCurrentPath(hrefString, urlToCompare);
    }

    function isCurrentOrParentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
    ) {
        return isCurrentUrl(urlToCheck, currentUrl, true);
    }

    function whenCurrentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        ifTrue: any,
        ifFalse: any = null,
    ) {
        return isCurrentUrl(urlToCheck) ? ifTrue : ifFalse;
    }

    return {
        currentUrl: readonly(currentUrlReactive),
        isCurrentUrl,
        isCurrentOrParentUrl,
        whenCurrentUrl,
    };
}
