<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { ref, computed } from 'vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Eye, Pencil, Trash2, Lock, Unlock, Upload, Users, Plus, RotateCcw } from 'lucide-vue-next';

interface User {
    id: number;
    name: string;
    email: string;
    activated: boolean;
    matricule?: string | null;
    created_at: string;
    roles?: {
        id: number;
        nom: string;
        slug: string;
    }[];
    agence?: {
        id: number;
        code: string;
        nom: string;
    } | null;
}

interface Props {
    users: {
        data: User[];
        links: any[];
        meta?: any;
        total?: number;
        current_page?: number;
        per_page?: number;
        last_page?: number;
    };
    roles?: Array<{ id: number; nom: string }>;
}

const props = defineProps<Props>();

const filters = ref({
    role: '',
    activation: '',
    search: '',
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Utilisateurs',
        href: '#',
    },
];

const fieldClass =
    'h-10 border-slate-300 bg-white text-slate-900 shadow-sm placeholder:text-slate-400 focus-visible:border-primary focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';

const selectClass =
    'flex h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';

const currentPage = computed(() => {
    return props.users.current_page || props.users.meta?.current_page || 1;
});
const totalItems = computed(() => {
    return props.users.total || props.users.meta?.total || 0;
});
const perPage = computed(() => {
    return props.users.per_page || props.users.meta?.per_page || 5;
});

const deleteUser = (id: number) => {
    if (confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?')) {
        router.delete(`/users/${id}`);
    }
};

const toggleUser = (id: number) => {
    if (confirm('Êtes-vous sûr de vouloir changer le statut de cet utilisateur ?')) {
        router.post(
            `/users/${id}/toggle`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                only: ['users'],
            },
        );
    }
};

const columns: Column[] = [
    { key: 'name', title: 'NOM', sortable: true },
    { key: 'idflex', title: 'IDFLEX', sortable: true },
    { key: 'email', title: 'EMAIL', sortable: true },
    { key: 'agence', title: 'ENTITÉ' },
    { key: 'roles', title: 'RÔLES' },
    { key: 'activated', title: 'ACTIVATION' },
    { key: 'actions', title: 'ACTIONS' },
];

const tableData = computed(() => {
    return props.users.data.map((user) => ({
        id: user.id,
        name: user.name,
        idflex: user.matricule || user.email?.split('@')[0] || '-',
        email: user.email,
        agence: user.agence ? `${user.agence.nom}` : '—',
        roles: user.roles || [],
        activated: user.activated,
        user: user,
    }));
});

const applyFilters = () => {
    const params = new URLSearchParams();
    Object.entries(filters.value).forEach(([key, value]) => {
        if (value) {
            params.set(key, value);
        }
    });
    params.set('page', '1');
    router.visit(`/users?${params.toString()}`, { preserveScroll: true });
};

const resetFilters = () => {
    filters.value.role = '';
    filters.value.activation = '';
    filters.value.search = '';
    applyFilters();
};

const initializeFilters = () => {
    const urlParams = new URLSearchParams(window.location.search);
    filters.value.role = urlParams.get('role') || '';
    filters.value.activation = urlParams.get('activation') || '';
    filters.value.search = urlParams.get('search') || '';
};

initializeFilters();

const handlePageChange = (page: number) => {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('page', page.toString());
    if (perPage.value) {
        urlParams.set('per_page', perPage.value.toString());
    }
    router.get(
        `/users?${urlParams.toString()}`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ['users'],
            replace: false,
        },
    );
};

const handleItemsPerPageChange = (items: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', items.toString());
    url.searchParams.set('page', '1');
    router.visit(url.toString(), { preserveScroll: true });
};

const handleSort = (column: string, direction: 'asc' | 'desc') => {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', column);
    url.searchParams.set('direction', direction);
    router.visit(url.toString(), { preserveScroll: true });
};

const showImportModal = ref(false);
const importForm = useForm({
    file: null as File | null,
});

const submitImport = () => {
    importForm.post('/users/import', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            showImportModal.value = false;
            importForm.reset('file');
        },
    });
};
</script>

<template>
    <Head title="Utilisateurs" />

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
                                <Users class="size-5" />
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-primary">
                                    Paramétrage
                                </p>
                                <h1 class="text-xl font-semibold tracking-tight text-foreground">
                                    Liste des utilisateurs
                                </h1>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    <span class="font-semibold text-primary">{{ totalItems }}</span>
                                    utilisateur{{ totalItems > 1 ? 's' : '' }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                variant="outline"
                                class="border-slate-300"
                                @click="showImportModal = true"
                            >
                                <Upload class="mr-2 size-4" />
                                Importer
                            </Button>
                            <Button as-child>
                                <Link href="/users/create" class="inline-flex items-center gap-2">
                                    <Plus class="size-4" />
                                    Nouveau
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 border-b border-border/80 px-4 py-4 sm:px-5">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Rôle</label>
                            <select v-model="filters.role" :class="selectClass">
                                <option value="">Tous</option>
                                <option
                                    v-for="role in props.roles || []"
                                    :key="role.id"
                                    :value="role.id"
                                >
                                    {{ role.nom }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Activation</label>
                            <select v-model="filters.activation" :class="selectClass">
                                <option value="">Tous</option>
                                <option value="1">Activé</option>
                                <option value="0">Désactivé</option>
                            </select>
                        </div>
                        <div class="md:col-span-2 lg:col-span-1">
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Recherche</label>
                            <Input
                                v-model="filters.search"
                                type="text"
                                placeholder="Rechercher (nom, email, IDFLEX…)"
                                :class="fieldClass"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button type="button" @click="applyFilters">Appliquer les filtres</Button>
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
                        show-select
                        @page-change="handlePageChange"
                        @items-per-page-change="handleItemsPerPageChange"
                        @sort="handleSort"
                    >
                        <template #item.name="{ item }">
                            <span class="font-medium text-foreground">{{ item.name }}</span>
                        </template>

                        <template #item.idflex="{ item }">
                            <span class="tabular-nums text-foreground">{{ item.idflex }}</span>
                        </template>

                        <template #item.email="{ item }">
                            <span class="text-foreground">{{ item.email }}</span>
                        </template>

                        <template #item.agence="{ item }">
                            <span class="text-sm text-foreground">{{ item.agence }}</span>
                        </template>

                        <template #item.roles="{ item }">
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="role in item.roles"
                                    :key="role.id"
                                    class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary ring-1 ring-primary/20"
                                >
                                    {{ role.nom }}
                                </span>
                                <span
                                    v-if="!item.roles || item.roles.length === 0"
                                    class="text-xs italic text-muted-foreground"
                                >
                                    Aucun rôle
                                </span>
                            </div>
                        </template>

                        <template #item.activated="{ item }">
                            <div class="flex items-center gap-2">
                                <component
                                    :is="item.activated ? Unlock : Lock"
                                    :class="[
                                        'size-5',
                                        item.activated ? 'text-emerald-600' : 'text-muted-foreground',
                                    ]"
                                />
                                <span
                                    :class="[
                                        'text-sm font-medium',
                                        item.activated ? 'text-emerald-700' : 'text-muted-foreground',
                                    ]"
                                >
                                    {{ item.activated ? 'Activé' : 'Désactivé' }}
                                </span>
                            </div>
                        </template>

                        <template #item.actions="{ item }">
                            <div class="flex items-center gap-1">
                                <Link
                                    :href="`/users/${item.id}`"
                                    class="inline-flex items-center justify-center rounded-md p-2 text-slate-600 transition-colors hover:bg-primary/5 hover:text-primary"
                                    title="Voir"
                                >
                                    <Eye class="size-5" />
                                </Link>
                                <Link
                                    :href="`/users/${item.id}/edit`"
                                    class="inline-flex items-center justify-center rounded-md p-2 text-slate-600 transition-colors hover:bg-primary/5 hover:text-primary"
                                    title="Modifier"
                                >
                                    <Pencil class="size-5" />
                                </Link>
                                <button
                                    type="button"
                                    :class="[
                                        'inline-flex items-center justify-center rounded-md p-2 transition-colors',
                                        item.activated
                                            ? 'text-orange-600 hover:bg-orange-50 hover:text-orange-700'
                                            : 'text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700',
                                    ]"
                                    :title="item.activated ? 'Désactiver' : 'Activer'"
                                    @click="toggleUser(item.id)"
                                >
                                    <component :is="item.activated ? Lock : Unlock" class="size-5" />
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-md p-2 text-red-600 transition-colors hover:bg-red-50 hover:text-red-700"
                                    title="Supprimer"
                                    @click="deleteUser(item.id)"
                                >
                                    <Trash2 class="size-5" />
                                </button>
                            </div>
                        </template>
                    </DataTable>
                </div>
            </section>
        </div>

        <Dialog :open="showImportModal" @update:open="showImportModal = $event">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Importer des utilisateurs</DialogTitle>
                </DialogHeader>
                <form class="space-y-4 py-4" @submit.prevent="submitImport">
                    <div
                        class="rounded-lg border border-primary/20 bg-primary/5 p-3 text-sm text-foreground"
                    >
                        <p>Importez un fichier Excel ou CSV. Les lignes sans email, nom ou fonction sont ignorées.</p>
                        <p class="mt-2 font-medium">
                            <a
                                href="/users/export-template"
                                class="text-primary underline hover:text-primary/80"
                            >
                                Télécharger le template Excel
                            </a>
                        </p>
                        <p class="mt-2 text-xs text-muted-foreground">
                            Colonnes : Nom, Fonction, Email, IDFLEX, Mot de passe (optionnel), Role (slug ou
                            nom), Departement, Code agence. Si le mot de passe est vide, un mot de passe
                            temporaire est généré (changement obligatoire à la première connexion).
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="import-file">Fichier Excel (.xlsx, .xls, .csv)</Label>
                        <Input
                            id="import-file"
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            :class="fieldClass"
                            required
                            @input="
                                importForm.file =
                                    ($event.target as HTMLInputElement).files?.[0] ?? null
                            "
                        />
                        <p v-if="importForm.errors.file" class="text-sm text-red-600">
                            {{ importForm.errors.file }}
                        </p>
                    </div>
                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            class="border-slate-300"
                            :disabled="importForm.processing"
                            @click="showImportModal = false"
                        >
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="importForm.processing">
                            <Upload v-if="!importForm.processing" class="mr-2 size-4" />
                            {{ importForm.processing ? 'Importation en cours…' : 'Importer' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
