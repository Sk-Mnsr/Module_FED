<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ArchivageDateClassifier, {
    type ArchiveAgent,
    type ArchiveFilters,
    type ArchiveTree,
    type SearchResultRow,
} from '@/components/ArchivageDateClassifier.vue';

defineProps<{
    tree?: ArchiveTree;
    agents?: ArchiveAgent[];
    filters?: ArchiveFilters;
    searchActive?: boolean;
    searchResults?: SearchResultRow[];
    canViewAllAgents?: boolean;
    totalClasseurs?: number;
    comptableImportApiConfigured?: boolean;
}>();

const breadcrumbs = [
    { title: 'Opérations diverses', href: '/operations-diverses/piece-comptable' },
    { title: 'Archivage', href: '/operations-diverses/archivage' },
];
</script>

<template>
    <Head title="Archivage — OD" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-[calc(100svh-4rem)] min-h-0 flex-col overflow-hidden p-3 sm:p-4">
            <ArchivageDateClassifier
                class="min-h-0 flex-1"
                :tree="tree ?? {}"
                :agents="agents ?? []"
                :filters="filters"
                :search-active="searchActive"
                :search-results="searchResults ?? []"
                :can-view-all-agents="canViewAllAgents"
                :total-classeurs="totalClasseurs"
                :comptable-import-api-configured="comptableImportApiConfigured"
            />
        </div>
    </AppLayout>
</template>
