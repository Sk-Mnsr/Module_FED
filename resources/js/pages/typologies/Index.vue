<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { computed } from 'vue';
import { Pencil, Trash2, Plus } from 'lucide-vue-next';

interface Typology {
    id: number;
    type: string;
    libelle: string;
    description?: string | null;
    created_at: string;
}

interface Props {
    typologies: {
        data: Typology[];
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
    { title: 'Typologies de dépenses', href: '/typologies' },
];

const currentPage = computed(() => props.typologies.current_page || props.typologies.meta?.current_page || 1);
const totalItems = computed(() => props.typologies.total || props.typologies.meta?.total || 0);
const perPage = computed(() => props.typologies.per_page || props.typologies.meta?.per_page || 10);

const handlePageChange = (page: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set('page', page.toString());
    url.searchParams.set('per_page', perPage.value.toString());
    router.get(url.toString(), {}, { preserveScroll: true, preserveState: true, only: ['typologies'] });
};

const handleItemsPerPageChange = (items: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', items.toString());
    url.searchParams.set('page', '1');
    router.visit(url.toString(), { preserveScroll: true });
};

const columns: Column[] = [
    { key: 'type', title: 'Type' },
    { key: 'libelle', title: 'Libellé' },
    { key: 'description', title: 'Description' },
    { key: 'created_at', title: 'Créé le' },
    { key: 'actions', title: 'Actions' },
];

const tableData = computed(() => {
    return props.typologies.data.map(item => ({
        id: item.id,
        type: item.type,
        libelle: item.libelle,
        description: item.description || '-',
        created_at: new Date(item.created_at).toLocaleDateString('fr-FR'),
    }));
});

const deleteTypology = (id: number) => {
    if (confirm('Supprimer cette typologie ?')) {
        router.delete(`/typologies/${id}`);
    }
};
</script>

<template>
    <Head title="Typologies de dépenses" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-gray-900">Typologies de dépenses</h1>
                <Link href="/typologies/create">
                    <Button class="bg-purple-600 hover:bg-purple-700">
                        <Plus class="mr-2 h-4 w-4" /> Nouveau
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
                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <Link
                            :href="`/typologies/${item.id}/edit`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                            title="Modifier"
                        >
                            <Pencil class="h-5 w-5" />
                        </Link>
                        <button
                            @click="deleteTypology(item.id)"
                            class="inline-flex items-center justify-center rounded-md p-2 text-red-600 hover:bg-red-50 hover:text-red-700"
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
