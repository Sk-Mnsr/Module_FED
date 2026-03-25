<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { computed } from 'vue';
import { Eye, Edit } from 'lucide-vue-next';

interface Comite {
    id: number;
    nom: string;
    statut: string;
    appel_offre_id: number;
    appel_offre?: any;
    membres?: any[];
}

interface Props {
    comites: {
        data: Comite[];
        links: any[];
        meta?: any;
        total?: number;
        current_page?: number;
        per_page?: number;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Comités', href: '/comites' },
];

const currentPage = computed(() => {
    return props.comites.current_page || props.comites.meta?.current_page || 1;
});
const totalItems = computed(() => {
    return props.comites.total || props.comites.meta?.total || 0;
});
const perPage = computed(() => {
    return props.comites.per_page || props.comites.meta?.per_page || 10;
});

const handlePageChange = (page: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set('page', page.toString());
    url.searchParams.set('per_page', perPage.value.toString());
    router.get(url.toString(), {}, { preserveScroll: true, preserveState: true, only: ['comites'] });
};

const handleItemsPerPageChange = (items: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', items.toString());
    url.searchParams.set('page', '1');
    router.visit(url.toString(), { preserveScroll: true });
};

const columns: Column[] = [
    { key: 'nom', title: 'Nom du comité' },
    { key: 'appelOffre', title: 'Appel d\'Offres lié' },
    { key: 'membres', title: 'Membres' },
    { key: 'statut', title: 'Statut' },
    { key: 'actions', title: 'Actions' },
];

const tableData = computed(() => {
    return props.comites.data.map(comite => ({
        id: comite.id,
        nom: comite.nom,
        appelOffre: comite.appel_offre?.reference || '-',
        membres: (comite.membres?.length || 0) + ' membre(s)',
        statut: comite.statut,
        comite,
    }));
});
</script>

<template>
    <Head title="Comités d'évaluation" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 2xl:p-12">
            <h1 class="text-3xl 2xl:text-5xl font-bold text-gray-900 mb-4">Comités d'évaluation</h1>

            <DataTable
                :headers="columns"
                :items="tableData"
                :current-page="currentPage"
                :items-per-page="perPage"
                :total-items="totalItems"
                :show-select="false"
                @page-change="handlePageChange"
                @items-per-page-change="handleItemsPerPageChange"
            >
                <template #item.statut="{ item }">
                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-800">
                        {{ item.statut }}
                    </span>
                </template>
                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <Link
                            :href="`/appel-offres/${item.comite.appel_offre_id}`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Voir l'Appel d'Offres"
                        >
                            <Eye class="h-5 w-5" />
                        </Link>
                        <Link
                            :href="`/comites/${item.id}/edit`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Modifier"
                        >
                            <Edit class="h-5 w-5" />
                        </Link>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
