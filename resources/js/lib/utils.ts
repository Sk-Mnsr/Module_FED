import { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function urlIsActive(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl: string,
) {
    return toUrl(urlToCheck) === currentUrl;
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
