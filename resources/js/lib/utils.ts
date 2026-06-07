import { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function normalizeUrlPath(url: string): string {
    const withoutQuery = url.split('?')[0] ?? url;

    return withoutQuery.split('#')[0] ?? withoutQuery;
}

/** Score de correspondance menu ↔ URL (plus long = plus spécifique). -1 si aucune. */
export function navUrlMatchScore(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl: string,
): number {
    const path = normalizeUrlPath(currentUrl);
    const target = normalizeUrlPath(toUrl(urlToCheck));

    if (path === target) {
        return target.length;
    }

    if (target !== '/' && path.startsWith(`${target}/`)) {
        return target.length;
    }

    return -1;
}

export function urlIsActive(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl: string,
) {
    return navUrlMatchScore(urlToCheck, currentUrl) >= 0;
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

/** Affichage lisible : groupes de `groupSize` caractères séparés par un espace (espaces saisis supprimés). */
export function formatCardNumberDisplay(raw: string, groupSize = 4): string {
    const compact = raw.replace(/\s/g, '');
    if (!compact) {
        return '';
    }
    const chunks: string[] = [];
    for (let i = 0; i < compact.length; i += groupSize) {
        chunks.push(compact.slice(i, i + groupSize));
    }
    return chunks.join(' ');
}
