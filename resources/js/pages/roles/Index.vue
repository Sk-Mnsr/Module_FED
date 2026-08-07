<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { computed, ref } from 'vue';
import { Pencil, Trash2, Plus, Shield, RotateCcw } from 'lucide-vue-next';

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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Rôles', href: '/roles' }];

const fieldClass =
    'h-10 border-slate-300 bg-white text-slate-900 shadow-sm placeholder:text-slate-400 focus-visible:border-primary focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';

const selectClass =
    'flex h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';

const moduleLabel = (key: string | null) =>
    props.modules.find((m) => m.key === key)?.label ?? key ?? '—';

const profileLabel = (value: string | null) =>
    props.accessProfiles.find((p) => p.value === value)?.label ?? value ?? '—';

const currentPage = computed(() => props.roles.current_page || 1);
const totalItems = computed(() => props.roles.total || 0);
const perPage = computed(() => props.roles.per_page || 15);

const applyFilters = () => {
    router.get(
        '/roles',
        {
            search: search.value || undefined,
            module: moduleFilter.value || undefined,
            page: 1,
            per_page: perPage.value,
        },
        { preserveState: true, preserveScroll: true },
    );
};

const resetFilters = () => {
    search.value = '';
    moduleFilter.value = '';
    applyFilters();
};

const handlePageChange = (page: number) => {
    router.get(
        '/roles',
        {
            search: search.value || undefined,
            module: moduleFilter.value || undefined,
            page,
            per_page: perPage.value,
        },
        { preserveState: true, preserveScroll: true, only: ['roles'] },
    );
};

const handleItemsPerPageChange = (items: number) => {
    router.get(
        '/roles',
        {
            search: search.value || undefined,
            module: moduleFilter.value || undefined,
            page: 1,
            per_page: items,
        },
        { preserveState: true, preserveScroll: true },
    );
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
        module_access:
            (role.module_keys ?? []).map((key) => moduleLabel(key)).join(', ') ||
            moduleLabel(role.module),
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
        <div class="flex min-h-0 flex-1 flex-col gap-4 p-4 sm:p-6">
            <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                <div
                    class="border-b border-border/80 bg-gradient-to-r from-primary/5 via-card to-transparent px-5 py-5 sm:px-6 dark:from-primary/10"
                >
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm"
                            >
                                <Shield class="size-5" />
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-primary">
                                    Paramétrage
                                </p>
                                <h1 class="text-xl font-semibold tracking-tight text-foreground">Rôles</h1>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    <span class="font-semibold text-primary">{{ totalItems }}</span>
                                    rôle{{ totalItems > 1 ? 's' : '' }}
                                </p>
                            </div>
                        </div>
                        <Button as-child>
                            <Link href="/roles/create" class="inline-flex items-center gap-2">
                                <Plus class="size-4" />
                                Nouveau rôle
                            </Link>
                        </Button>
                    </div>
                </div>

                <div class="space-y-4 border-b border-border/80 px-4 py-4 sm:px-5">
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-[200px] flex-1">
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Recherche</label>
                            <Input
                                v-model="search"
                                placeholder="Nom ou slug…"
                                :class="fieldClass"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                        <div class="min-w-[180px]">
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Module</label>
                            <select v-model="moduleFilter" :class="selectClass">
                                <option value="">Tous</option>
                                <option
                                    v-for="module in modules"
                                    :key="module.key"
                                    :value="module.key"
                                >
                                    {{ module.label }}
                                </option>
                            </select>
                        </div>
                        <Button type="button" @click="applyFilters">Filtrer</Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="border-slate-300"
                            @click="resetFilters"
                        >
                            <RotateCcw class="mr-2 size-4" />
                            Réinitialiser
                        </Button>
                    </div>
                </div>

                <div class="p-4 sm:p-5">
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
                        <template #item.slug="{ item }">
                            <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">
                                {{ item.slug }}
                            </code>
                        </template>
                        <template #item.actif="{ item }">
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-medium ring-1"
                                :class="
                                    item.actif === 'Actif'
                                        ? 'bg-emerald-100 text-emerald-800 ring-emerald-200/80'
                                        : 'bg-slate-100 text-slate-600 ring-slate-200/80'
                                "
                            >
                                {{ item.actif }}
                            </span>
                        </template>
                        <template #item.actions="{ item }">
                            <div class="flex items-center gap-1">
                                <Link
                                    :href="`/roles/${item.id}/edit`"
                                    class="inline-flex items-center justify-center rounded-md p-2 text-slate-600 transition-colors hover:bg-primary/5 hover:text-primary"
                                    title="Modifier"
                                >
                                    <Pencil class="size-5" />
                                </Link>
                                <button
                                    v-if="!item.isIt"
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-md p-2 text-red-600 transition-colors hover:bg-red-50"
                                    title="Supprimer"
                                    @click="deleteRole(item.id, item.slug)"
                                >
                                    <Trash2 class="size-5" />
                                </button>
                            </div>
                        </template>
                    </DataTable>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                <div
                    class="border-b border-border/80 bg-gradient-to-r from-primary/5 via-transparent to-transparent px-5 py-4 sm:px-6"
                >
                    <h2 class="text-base font-semibold text-foreground">Matrice modules × rôles</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Synchronisée avec les rôles en base (table
                        <code class="text-xs">role_module</code>). Modifier un rôle met à jour
                        immédiatement les accès menus et middleware.
                    </p>
                </div>
                <div class="overflow-x-auto p-4 sm:p-5">
                    <table class="min-w-full divide-y divide-border text-sm">
                        <thead class="bg-muted/40">
                            <tr>
                                <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Module
                                </th>
                                <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Rôles autorisés
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in moduleMatrix" :key="row.key" class="hover:bg-muted/30">
                                <td class="px-3 py-2.5 font-medium text-foreground">{{ row.label }}</td>
                                <td class="px-3 py-2.5">
                                    <span
                                        v-for="slug in row.roles"
                                        :key="slug"
                                        class="mb-1 mr-1 inline-block rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary ring-1 ring-primary/20"
                                    >
                                        {{ slug }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
