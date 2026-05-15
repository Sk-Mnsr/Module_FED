<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const tabs = [
    { href: '/ecritures-comptables', label: 'Écritures comptables', activePrefix: '/ecritures-comptables' },
    { href: '/operations-diverses/piece-comptable', label: 'Opérations diverses (OD)', activePrefix: '/operations-diverses' },
] as const;

function isTabActive(tab: (typeof tabs)[number]): boolean {
    const path = page.url.split('?')[0] ?? '';
    const p = tab.activePrefix;
    if (path === tab.href || path === p) {
        return true;
    }
    return path.startsWith(`${p}/`);
}
</script>

<template>
    <nav
        class="flex flex-wrap gap-1 border-b border-gray-200 dark:border-neutral-700"
        aria-label="Modules comptabilité"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.href"
            :href="tab.href"
            preserve-scroll
            class="border-b-2 px-4 py-2.5 text-sm font-medium transition-colors"
            :class="
                isTabActive(tab)
                    ? '-mb-px border-blue-600 text-blue-600 dark:border-sky-500 dark:text-sky-400'
                    : '-mb-px border-transparent text-gray-500 hover:text-gray-800 dark:text-neutral-400 dark:hover:text-neutral-200'
            "
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
