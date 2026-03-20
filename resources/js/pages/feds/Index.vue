<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { computed } from 'vue';
import { Eye, Pencil, Trash2, Plus } from 'lucide-vue-next';

interface Fed {
    id: number;
    code: string;
    date?: string | null;
    demandeur?: string | null;
    motive?: string | null;
    status: string;
}

interface Props {
    feds: {
        data: Fed[];
        links: any[];
        meta?: any;
        total?: number;
        current_page?: number;
        per_page?: number;
        last_page?: number;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Fiches de dépense',
        href: '/feds',
    },
];

const currentPage = computed(() => {
    return props.feds.current_page || props.feds.meta?.current_page || 1;
});
const totalItems = computed(() => {
    return props.feds.total || props.feds.meta?.total || 0;
});
const perPage = computed(() => {
    return props.feds.per_page || props.feds.meta?.per_page || 10;
});

const handlePageChange = (page: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set('page', page.toString());
    url.searchParams.set('per_page', perPage.value.toString());
    router.get(url.toString(), {}, { preserveScroll: true, preserveState: true, only: ['feds'] });
};

const handleItemsPerPageChange = (items: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', items.toString());
    url.searchParams.set('page', '1');
    router.visit(url.toString(), { preserveScroll: true });
};


const statusLabel = (status: string) => {
    switch (status) {
        case 'pending_validation':
            return 'En attente de validation';
        case 'n1_needs_info':
            return 'Complément demandé';
        case 'n1_rejected':
            return 'Rejetée';
        case 'n1_approved':
            return 'Validée';
        default:
            return status;
    }
};

const statusBadge = (status: string) => {
    switch (status) {
        case 'pending_validation':
            return 'bg-blue-100 text-blue-700';
        case 'n1_needs_info':
            return 'bg-orange-100 text-orange-700';
        case 'n1_rejected':
            return 'bg-red-100 text-red-700';
        case 'n1_approved':
            return 'bg-green-100 text-green-700';
        default:
            return 'bg-gray-100 text-gray-700';
    }
};

const columns: Column[] = [
    { key: 'code', title: 'N° FED' },
    { key: 'date', title: 'Date' },
    { key: 'demandeur', title: 'Demandeur' },
    { key: 'motive', title: 'Motif' },
    { key: 'status', title: 'Statut' },
    { key: 'actions', title: 'Actions' },
];

const tableData = computed(() => {
    return props.feds.data.map(fed => ({
        id: fed.id,
        code: fed.code,
        date: fed.date ? new Date(fed.date).toLocaleDateString('fr-FR') : '-',
        demandeur: fed.demandeur || '-',
        motive: fed.motive && fed.motive.length > 50 ? fed.motive.substring(0, 50) + '...' : fed.motive || '-',
        status: fed.status,
        fed,
    }));
});

const deleteFed = (id: number) => {
    if (confirm('Supprimer cette FED ?')) {
        router.delete(`/feds/${id}`);
    }
};
</script>

<template>
    <Head title="Mes FED" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-gray-900">Mes fiches de dépense</h1>
                <Link href="/feds/create">
                    <Button class="bg-purple-600 hover:bg-purple-700">
                        <Plus class="mr-2 h-4 w-4" /> Nouvelle demande
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
                <template #item.status="{ item }">
                    <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', statusBadge(item.status)]">
                        {{ statusLabel(item.status) }}
                    </span>
                </template>

                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <Link
                            :href="`/feds/${item.id}`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Voir"
                        >
                            <Eye class="h-5 w-5" />
                        </Link>
                        <Link
                            :href="`/feds/${item.id}/edit`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Modifier"
                        >
                            <Pencil class="h-5 w-5" />
                        </Link>
                        <button
                            @click="deleteFed(item.id)"
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

