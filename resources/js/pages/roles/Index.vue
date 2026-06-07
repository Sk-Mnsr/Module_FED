<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { computed, ref } from 'vue';
import { Pencil, Trash2, Plus } from 'lucide-vue-next';

interface Role {
    id: number;
    nom: string;
    slug: string;
    module: string | null;
    module_keys?: string[];
    access_profile: string | null;
    description: string | null;
    actif: boolean;
    users_count: number;
}

interface ModuleOption {
    key: string;
    label: string;
}

interface ModuleMatrixRow {
    key: string;
    label: string;
    roles: string[];
}

interface Props {
    roles: {
        data: Role[];
        links: any[];
        total?: number;
        current_page?: number;
        per_page?: number;
    };
    modules: ModuleOption[];
    moduleMatrix: ModuleMatrixRow[];
    accessProfiles: Array<{ value: string; label: string }>;
    filters: {
        search: string;
        module: string;
    };
}

const props = defineProps<Props>();

const search = ref(props.filters.search);
const moduleFilter = ref(props.filters.module);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Rôles', href: '/roles' },
];

const moduleLabel = (key: string | null) =>
    props.modules.find((m) => m.key === key)?.label ?? key ?? '—';

const profileLabel = (value: string | null) =>
    props.accessProfiles.find((p) => p.value === value)?.label ?? value ?? '—';

const currentPage = computed(() => props.roles.current_page || 1);
const totalItems = computed(() => props.roles.total || 0);
const perPage = computed(() => props.roles.per_page || 15);

const applyFilters = () => {
    router.get('/roles', {
        search: search.value || undefined,
        module: moduleFilter.value || undefined,
        page: 1,
        per_page: perPage.value,
    }, { preserveState: true, preserveScroll: true });
};

const handlePageChange = (page: number) => {
    router.get('/roles', {
        search: search.value || undefined,
        module: moduleFilter.value || undefined,
        page,
        per_page: perPage.value,
    }, { preserveState: true, preserveScroll: true, only: ['roles'] });
};

const handleItemsPerPageChange = (items: number) => {
    router.get('/roles', {
        search: search.value || undefined,
        module: moduleFilter.value || undefined,
        page: 1,
        per_page: items,
    }, { preserveState: true, preserveScroll: true });
};

const columns: Column[] = [
    { key: 'nom', title: 'Nom' },
    { key: 'slug', title: 'Slug' },
    { key: 'module', title: 'Module principal' },
    { key: 'module_access', title: 'Modules accessibles' },
    { key: 'access_profile', title: 'Profil' },
    { key: 'users_count', title: 'Utilisateurs' },
    { key: 'actif', title: 'Statut' },
    { key: 'actions', title: 'Actions' },
];

const tableData = computed(() =>
    props.roles.data.map((role) => ({
        id: role.id,
        nom: role.nom,
        slug: role.slug,
        module: moduleLabel(role.module),
        module_access: (role.module_keys ?? [])
            .map((key) => moduleLabel(key))
            .join(', ') || moduleLabel(role.module),
        access_profile: profileLabel(role.access_profile),
        users_count: role.users_count,
        actif: role.actif ? 'Actif' : 'Inactif',
        isIt: role.slug === 'it',
    })),
);

const deleteRole = (id: number, slug: string) => {
    if (slug === 'it') {
        return;
    }
    if (confirm('Supprimer ce rôle ?')) {
        router.delete(`/roles/${id}`);
    }
};
</script>

<template>
    <Head title="Rôles" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-gray-900">Rôles</h1>
                <Link href="/roles/create">
                    <Button class="bg-purple-600 hover:bg-purple-700">
                        <Plus class="mr-2 h-4 w-4" /> Nouveau rôle
                    </Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white p-4">
                <div class="min-w-[200px] flex-1">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Recherche</label>
                    <Input v-model="search" placeholder="Nom ou slug…" @keyup.enter="applyFilters" />
                </div>
                <div class="min-w-[180px]">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Module</label>
                    <select
                        v-model="moduleFilter"
                        class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm"
                    >
                        <option value="">Tous</option>
                        <option v-for="module in modules" :key="module.key" :value="module.key">
                            {{ module.label }}
                        </option>
                    </select>
                </div>
                <Button type="button" variant="outline" @click="applyFilters">Filtrer</Button>
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
                <template #item.actif="{ item }">
                    <span
                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="item.actif === 'Actif' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                    >
                        {{ item.actif }}
                    </span>
                </template>
                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <Link
                            :href="`/roles/${item.id}/edit`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100"
                            title="Modifier"
                        >
                            <Pencil class="h-5 w-5" />
                        </Link>
                        <button
                            v-if="!item.isIt"
                            type="button"
                            class="inline-flex items-center justify-center rounded-md p-2 text-red-600 hover:bg-red-50"
                            title="Supprimer"
                            @click="deleteRole(item.id, item.slug)"
                        >
                            <Trash2 class="h-5 w-5" />
                        </button>
                    </div>
                </template>
            </DataTable>

            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                <h2 class="mb-3 text-lg font-semibold text-gray-900">Matrice modules × rôles</h2>
                <p class="mb-4 text-sm text-gray-600">
                    Synchronisée avec les rôles en base (table <code class="text-xs">role_module</code>).
                    Modifier un rôle met à jour immédiatement les accès menus et middleware.
                </p>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-white">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">Module</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">Rôles autorisés</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-for="row in moduleMatrix" :key="row.key">
                                <td class="px-3 py-2 font-medium text-gray-900">{{ row.label }}</td>
                                <td class="px-3 py-2 text-gray-600">
                                    <span
                                        v-for="slug in row.roles"
                                        :key="slug"
                                        class="mr-1 mb-1 inline-block rounded bg-gray-100 px-2 py-0.5 text-xs"
                                    >
                                        {{ slug }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
