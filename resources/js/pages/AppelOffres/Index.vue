<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { computed } from 'vue';
import { Eye, Pencil, Trash2, Plus } from 'lucide-vue-next';

interface AppelOffre {
    id: number;
    reference: string;
    objet: string;
    date_lancement?: string | null;
    date_limite_soumission: string;
    statut: string;
}

interface Props {
    appelOffres: {
        data: AppelOffre[];
        links: any[];
        meta?: any;
        total?: number;
        current_page?: number;
        per_page?: number;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Appels d\'Offres',
        href: '/appel-offres',
    },
];

const currentPage = computed(() => {
    return props.appelOffres.current_page || props.appelOffres.meta?.current_page || 1;
});
const totalItems = computed(() => {
    return props.appelOffres.total || props.appelOffres.meta?.total || 0;
});
const perPage = computed(() => {
    return props.appelOffres.per_page || props.appelOffres.meta?.per_page || 10;
});

const handlePageChange = (page: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set('page', page.toString());
    url.searchParams.set('per_page', perPage.value.toString());
    router.get(url.toString(), {}, { preserveScroll: true, preserveState: true, only: ['appelOffres'] });
};

const handleItemsPerPageChange = (items: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', items.toString());
    url.searchParams.set('page', '1');
    router.visit(url.toString(), { preserveScroll: true });
};

const statusLabel = (status: string) => {
    switch (status) {
        case 'brouillon': return 'Brouillon';
        case 'publie': return 'Publié';
        case 'cloture': return 'Clôturé';
        case 'en_evaluation': return 'En évaluation';
        case 'attribue': return 'Attribué';
        default: return status;
    }
};

const statusBadge = (status: string) => {
    switch (status) {
        case 'brouillon': return 'bg-gray-100 text-gray-700';
        case 'publie': return 'bg-blue-100 text-blue-700';
        case 'cloture': return 'bg-orange-100 text-orange-700';
        case 'en_evaluation': return 'bg-purple-100 text-purple-700';
        case 'attribue': return 'bg-green-100 text-green-700';
        default: return 'bg-gray-100 text-gray-700';
    }
};

const columns: Column[] = [
    { key: 'reference', title: 'Référence' },
    { key: 'objet', title: 'Objet' },
    { key: 'date_lancement', title: 'Lancement' },
    { key: 'date_limite_soumission', title: 'Limite' },
    { key: 'statut', title: 'Statut' },
    { key: 'actions', title: 'Actions' },
];

const tableData = computed(() => {
    return props.appelOffres.data.map(tender => ({
        id: tender.id,
        reference: tender.reference,
        objet: tender.objet.length > 50 ? tender.objet.substring(0, 50) + '...' : tender.objet,
        date_lancement: tender.date_lancement ? new Date(tender.date_lancement).toLocaleDateString('fr-FR') : '-',
        date_limite_soumission: new Date(tender.date_limite_soumission).toLocaleDateString('fr-FR'),
        statut: tender.statut,
        tender,
    }));
});

const deleteTender = (id: number) => {
    if (confirm('Supprimer cet appel d\'offres ?')) {
        router.delete(`/appel-offres/${id}`);
    }
};
</script>

<template>
    <Head title="Appels d'Offres" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-gray-900">Appels d'Offres</h1>
                <Link href="/appel-offres/create">
                    <Button class="bg-purple-600 hover:bg-purple-700">
                        <Plus class="mr-2 h-4 w-4" /> Nouvel Appel
                    </Button>
                </Link>
            </div>

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
                    <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', statusBadge(item.statut)]">
                        {{ statusLabel(item.statut) }}
                    </span>
                </template>

                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <Link
                            :href="`/appel-offres/${item.id}`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Voir"
                        >
                            <Eye class="h-5 w-5" />
                        </Link>
                        <Link v-if="item.statut === 'brouillon'"
                            :href="`/appel-offres/${item.id}/edit`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Modifier"
                        >
                            <Pencil class="h-5 w-5" />
                        </Link>
                        <button v-if="item.statut === 'brouillon'"
                            @click="deleteTender(item.id)"
                            class="inline-flex items-center justify-center rounded-md p-2 text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors"
                            title="Supprimer"
                        >
                            <Trash2 class="h-5 w-5" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
