<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { computed } from 'vue';
import { Pencil, Trash2, Plus } from 'lucide-vue-next';

interface Department {
    id: number;
    name: string;
    code: string;
    manager?: {
        id: number;
        name: string;
        email?: string | null;
    } | null;
    created_at: string;
}

interface Props {
    departments: {
        data: Department[];
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
    { title: 'Départements', href: '/departments' },
];

const currentPage = computed(() => props.departments.current_page || props.departments.meta?.current_page || 1);
const totalItems = computed(() => props.departments.total || props.departments.meta?.total || 0);
const perPage = computed(() => props.departments.per_page || props.departments.meta?.per_page || 10);

const handlePageChange = (page: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set('page', page.toString());
    url.searchParams.set('per_page', perPage.value.toString());
    router.get(url.toString(), {}, { preserveScroll: true, preserveState: true, only: ['departments'] });
};

const handleItemsPerPageChange = (items: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', items.toString());
    url.searchParams.set('page', '1');
    router.visit(url.toString(), { preserveScroll: true });
};

const columns: Column[] = [
    { key: 'name', title: 'Département' },
    { key: 'code', title: 'Code' },
    { key: 'manager', title: 'N+1 (Manager)' },
    { key: 'created_at', title: 'Créé le' },
    { key: 'actions', title: 'Actions' },
];

const tableData = computed(() => {
    return props.departments.data.map(dep => ({
        id: dep.id,
        name: dep.name,
        code: dep.code,
        manager: dep.manager ? dep.manager.name || dep.manager.email || '-' : '-',
        created_at: new Date(dep.created_at).toLocaleDateString('fr-FR'),
    }));
});

const deleteDepartment = (id: number) => {
    if (confirm('Supprimer ce département ?')) {
        router.delete(`/departments/${id}`);
    }
};
</script>

<template>
    <Head title="Départements" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-gray-900">Départements</h1>
                <Link href="/departments/create">
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
                            :href="`/departments/${item.id}/edit`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                            title="Modifier"
                        >
                            <Pencil class="h-5 w-5" />
                        </Link>
                        <button
                            @click="deleteDepartment(item.id)"
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
