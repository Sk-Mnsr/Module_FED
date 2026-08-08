<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import FlashErrorDialog from '@/components/FlashErrorDialog.vue';
import type { BreadcrumbItemType } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

type CoficarteAlert = { niveau: string; message: string };

const page = usePage();
const coficarteAlerts = computed(() => (page.props.coficarteAlerts as CoficarteAlert[] | undefined) ?? []);

/** Violet pour Monétique, OD & FED ; rouge Cofina ailleurs */
const useVioletAccent = computed(() => {
    const path = (page.url || '').split('?')[0];
    return (
        path.startsWith('/monetique') ||
        path.startsWith('/operations-diverses') ||
        path.startsWith('/feds') ||
        path.startsWith('/achats') ||
        path.startsWith('/appel-offres') ||
        path.startsWith('/bons-de-commande') ||
        path.startsWith('/comites') ||
        path.startsWith('/fournisseurs')
    );
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent
            variant="sidebar"
            :class="[
                'flex min-h-0 flex-1 flex-col overflow-x-hidden md:!m-0 md:!ml-0 md:!rounded-none md:!shadow-none',
                useVioletAccent ? 'theme-module-violet' : '',
            ]"
        >
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div v-if="coficarteAlerts.length" class="shrink-0 px-4 pt-3 space-y-2">
                <div
                    v-for="(alerte, i) in coficarteAlerts"
                    :key="i"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"
                    role="status"
                >
                    {{ alerte.message }}
                </div>
            </div>
            <div class="flex min-h-0 flex-1 flex-col">
                <slot />
            </div>
        </AppContent>
        <FlashErrorDialog />
    </AppShell>
</template>
