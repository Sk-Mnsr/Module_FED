<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import { ref, computed } from 'vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Code, Eye, Pencil, Trash2, Lock, Unlock, Upload } from 'lucide-vue-next';

interface User {
    id: number;
    name: string;
    email: string;
    activated: boolean;
    matricule?: string | null;
    created_at: string;
    profil?: {
        id: number;
        nom: string;
        prenom: string;
        matricule: string;
        site?: string;
    };
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

const currentPage = computed(() => {
    return props.users.current_page || props.users.meta?.current_page || 1;
});
const totalItems = computed(() => {
    // Laravel paginate() retourne total à la racine, pas dans meta
    const total = props.users.total || props.users.meta?.total || 0;
    return total;
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
        router.post(`/users/${id}/toggle`, {}, {
            preserveScroll: true,
            preserveState: true,
            only: ['users'],
            onSuccess: () => {
                // Le message de succès est géré par le contrôleur
            },
        });
    }
};

const getAvatarColor = (name: string) => {
    const colors = [
        'bg-purple-500',
        'bg-blue-500',
        'bg-green-500',
        'bg-yellow-500',
        'bg-pink-500',
        'bg-indigo-500',
        'bg-red-500',
        'bg-teal-500',
    ];
    const index = name.charCodeAt(0) % colors.length;
    return colors[index];
};

const getStatusBadge = (isActive: boolean) => {
    if (isActive) {
        return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
    }
    return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
};

const getStatusLabel = (isActive: boolean) => {
    return isActive ? 'Actif' : 'Inactif';
};

const columns: Column[] = [
    {
        key: 'name',
        title: 'NOM',
        sortable: true,
    },
    {
        key: 'idflex',
        title: 'IDFLEX',
        sortable: true,
    },
    {
        key: 'email',
        title: 'EMAIL',
        sortable: true,
    },
    {
        key: 'agence',
        title: 'ENTITÉ',
    },
    {
        key: 'roles',
        title: 'RÔLES',
    },    {
        key: 'activated',
        title: 'ACTIVATION',
    },
    {
        key: 'actions',
        title: 'ACTIONS',
    },
];

const tableData = computed(() => {
    return props.users.data.map(user => ({
        id: user.id,
        name: user.name,
        idflex: user.matricule || user.profil?.matricule || user.email?.split('@')[0] || '-',
        email: user.email,
        agence: user.agence ? `${user.agence.nom}` : '—',
        roles: user.roles || [],
        activated: user.activated,
        user: user,
    }));
});

const reload = () => {
    router.reload({ only: ['users'] });
};

const applyFilters = () => {
    const params = new URLSearchParams();
    Object.entries(filters.value).forEach(([key, value]) => {
        if (value) {
            params.set(key, value);
        }
    });
    // Réinitialiser à la page 1 lors de l'application des filtres
    params.set('page', '1');
    router.visit(`/users?${params.toString()}`, { preserveScroll: true });
};

// Initialiser les filtres depuis l'URL
const initializeFilters = () => {
    const urlParams = new URLSearchParams(window.location.search);
    filters.value.role = urlParams.get('role') || '';
    filters.value.activation = urlParams.get('activation') || '';
    filters.value.search = urlParams.get('search') || '';
};

// Initialiser au chargement
initializeFilters();

const handlePageChange = (page: number) => {
    console.log('handlePageChange called:', { page, currentPage: currentPage.value, totalItems: totalItems.value, perPage: perPage.value });
    
    // Récupérer tous les paramètres actuels
    const urlParams = new URLSearchParams(window.location.search);
    
    // Mettre à jour le paramètre page
    urlParams.set('page', page.toString());
    
    // Préserver per_page s'il existe
    if (perPage.value) {
        urlParams.set('per_page', perPage.value.toString());
    }
    
    // Construire l'URL complète
    const newUrl = `/users?${urlParams.toString()}`;
    
    console.log('Navigating to:', newUrl);
    
    router.get(newUrl, {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['users'],
        replace: false,
    });
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
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-3xl font-bold text-gray-900">Liste des utilisateurs</h1>
                <div class="flex items-center gap-2">
                    <Button variant="outline" @click="showImportModal = true" class="text-blue-700 hover:bg-blue-50 hover:text-blue-800">
                        <Upload class="mr-2 h-4 w-4" /> Importer
                    </Button>
                    <Link href="/users/create">
                        <Button class="bg-purple-600 hover:bg-purple-700">+ Nouveau</Button>
                    </Link>
                </div>
            </div>

            <!-- Section Filtres -->
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <h2 class="mb-4 text-base font-semibold text-gray-700">Filtres</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-base font-medium text-gray-700">Rôle</label>
                        <select
                            v-model="filters.role"
                            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
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
                        <label class="mb-1.5 block text-base font-medium text-gray-700">Activation</label>
                        <select
                            v-model="filters.activation"
                            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option value="">Tous</option>
                            <option value="1">Activé</option>
                            <option value="0">Désactivé</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2">
                    <Input
                        v-model="filters.search"
                        type="text"
                        placeholder="Rechercher (nom, email, IDFLEX…)"
                        class="flex-1 border-gray-300 focus-visible:border-gray-400"
                        @keyup.enter="applyFilters"
                    />
                    <Button @click="applyFilters" class="bg-blue-600 hover:bg-blue-700">
                        Appliquer les filtres
                    </Button>
                    <Button variant="outline" @click="() => { filters.role = ''; filters.activation = ''; filters.search = ''; applyFilters(); }" class="border-gray-300">
                        Réinitialiser
                    </Button>
                </div>
            </div>

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
                    <span class="text-gray-900 font-medium">{{ item.name }}</span>
                </template>

                <template #item.idflex="{ item }">
                    <span class="text-gray-900">{{ item.idflex }}</span>
                </template>

                <template #item.email="{ item }">
                    <span class="text-gray-900">{{ item.email }}</span>
                </template>

                <template #item.agence="{ item }">
                    <span class="text-gray-900 text-sm">{{ item.agence }}</span>
                </template>

                <template #item.roles="{ item }">
                    <div class="flex flex-wrap gap-1">
                        <span
                            v-for="role in item.roles"
                            :key="role.id"
                            class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800"
                        >
                            {{ role.nom }}
                        </span>
                        <span v-if="!item.roles || item.roles.length === 0" class="text-gray-400 text-xs italic">
                            Aucun rôle
                        </span>
                    </div>
                </template>

                <template #item.activated="{ item }">
                    <div class="flex items-center gap-2">
                        <component 
                            :is="item.activated ? Unlock : Lock" 
                            :class="[
                                'h-5 w-5',
                                item.activated ? 'text-green-600' : 'text-gray-400'
                            ]" 
                        />
                        <span 
                            :class="[
                                'text-base font-medium',
                                item.activated ? 'text-green-700' : 'text-gray-500'
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
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Voir"
                        >
                            <Eye class="h-5 w-5" />
                        </Link>
                        <Link
                            :href="`/users/${item.id}/edit`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Modifier"
                        >
                            <Pencil class="h-5 w-5" />
                        </Link>
                        <button
                            @click="toggleUser(item.id)"
                            :class="[
                                'inline-flex items-center justify-center rounded-md p-2 transition-colors',
                                item.activated 
                                    ? 'text-orange-600 hover:bg-orange-50 hover:text-orange-700' 
                                    : 'text-green-600 hover:bg-green-50 hover:text-green-700'
                            ]"
                            :title="item.activated ? 'Désactiver' : 'Activer'"
                        >
                            <component :is="item.activated ? Lock : Unlock" class="h-5 w-5" />
                        </button>
                        <button
                            @click="deleteUser(item.id)"
                            class="inline-flex items-center justify-center rounded-md p-2 text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors"
                            title="Supprimer"
                        >
                            <Trash2 class="h-5 w-5" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <Dialog :open="showImportModal" @update:open="showImportModal = $event">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Importer des utilisateurs</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submitImport" class="space-y-4 py-4">
                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
                        <p>Importez un fichier Excel ou CSV. Les lignes sans email, nom ou fonction sont ignorées.</p>
                        <p class="mt-2 font-medium">
                            <a href="/users/export-template" class="text-blue-700 underline hover:text-blue-900">
                                Télécharger le template Excel
                            </a>
                        </p>
                        <p class="mt-2 text-xs text-blue-700">
                            Colonnes : Nom, Fonction, Email, IDFLEX, Mot de passe (optionnel), Role (slug ou nom), Departement, Code agence.
                            Si le mot de passe est vide, un mot de passe temporaire est généré (changement obligatoire à la première connexion).
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="import-file">Fichier Excel (.xlsx, .xls, .csv)</Label>
                        <Input
                            id="import-file"
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            @input="importForm.file = ($event.target as HTMLInputElement).files?.[0] ?? null"
                            required
                        />
                        <p v-if="importForm.errors.file" class="text-sm text-red-600">
                            {{ importForm.errors.file }}
                        </p>
                    </div>
                    <DialogFooter class="pt-4">
                        <Button type="button" variant="outline" @click="showImportModal = false" :disabled="importForm.processing">
                            Annuler
                        </Button>
                        <Button type="submit" class="bg-blue-600 hover:bg-blue-700" :disabled="importForm.processing">
                            <Upload v-if="!importForm.processing" class="mr-2 h-4 w-4" />
                            {{ importForm.processing ? 'Importation en cours...' : 'Importer' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

