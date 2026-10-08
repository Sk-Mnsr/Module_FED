<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { computed, ref } from 'vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import OdConfirmDialog from '@/components/OdConfirmDialog.vue';
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    Eye,
    Lock,
    MoreHorizontal,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Trash2,
    Unlock,
    Upload,
    Users,
} from 'lucide-vue-next';

interface RoleChip {
    id: number;
    nom: string;
    slug: string;
}

interface UserRow {
    id: number;
    name: string;
    email: string;
    fonction?: string | null;
    matricule?: string | null;
    activated: boolean;
    roles: RoleChip[];
    agence?: {
        id: number;
        code: string;
        nom: string;
    } | null;
}

interface Props {
    users: {
        data: UserRow[];
        meta?: {
            current_page?: number;
            per_page?: number;
            last_page?: number;
            total?: number;
        };
        total?: number;
        current_page?: number;
        per_page?: number;
        last_page?: number;
    };
    roles?: Array<{ id: number; nom: string }>;
    stats?: {
        total: number;
        actifs: number;
        inactifs: number;
    };
    filters?: {
        search?: string;
        role?: string;
        activation?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Utilisateurs', href: '/users' }];

const fieldClass =
    'h-9 border-slate-300 bg-white text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus-visible:border-primary focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';

const selectClass =
    'h-9 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';

const filters = ref({
    role: props.filters?.role ?? '',
    activation: props.filters?.activation ?? '',
    search: props.filters?.search ?? '',
});

const sortColumn = ref(props.filters?.sort || 'name');
const sortDirection = ref<'asc' | 'desc'>(props.filters?.direction === 'desc' ? 'desc' : 'asc');

const currentPage = computed(() => props.users.current_page || props.users.meta?.current_page || 1);
const perPage = computed(() => props.users.per_page || props.users.meta?.per_page || 25);
const lastPage = computed(() => props.users.last_page || props.users.meta?.last_page || 1);
const totalItems = computed(() => props.users.total || props.users.meta?.total || props.users.data.length);
const stats = computed(() => props.stats ?? { total: totalItems.value, actifs: 0, inactifs: 0 });

const hasFilters = computed(
    () => filters.value.role !== '' || filters.value.activation !== '' || filters.value.search.trim() !== '',
);

const rangeLabel = computed(() => {
    if (totalItems.value === 0) {
        return '0 utilisateur';
    }
    const start = (currentPage.value - 1) * perPage.value + 1;
    const end = Math.min(currentPage.value * perPage.value, totalItems.value);
    return `${start}–${end} sur ${totalItems.value}`;
});

const pageNumbers = computed(() => {
    const total = lastPage.value;
    const current = currentPage.value;
    if (total <= 7) {
        return Array.from({ length: total }, (_, index) => index + 1);
    }
    const pages: Array<number | string> = [1];
    if (current > 3) {
        pages.push('…');
    }
    const from = Math.max(2, current - 1);
    const to = Math.min(total - 1, current + 1);
    for (let page = from; page <= to; page += 1) {
        pages.push(page);
    }
    if (current < total - 2) {
        pages.push('…');
    }
    pages.push(total);
    return pages;
});

function entiteLabel(user: UserRow): string {
    if (!user.agence) {
        return '—';
    }
    return user.agence.nom || user.agence.code || '—';
}

function roleTitle(roles: RoleChip[]): string {
    return roles.map((role) => role.nom).join(', ');
}

function buildParams(page = 1): URLSearchParams {
    const params = new URLSearchParams();
    const search = filters.value.search.trim();
    if (search) {
        params.set('search', search);
    }
    if (filters.value.role) {
        params.set('role', filters.value.role);
    }
    if (filters.value.activation !== '') {
        params.set('activation', filters.value.activation);
    }
    if (sortColumn.value && sortColumn.value !== 'name') {
        params.set('sort', sortColumn.value);
    } else if (sortDirection.value === 'desc') {
        params.set('sort', sortColumn.value);
    }
    if (sortDirection.value === 'desc') {
        params.set('direction', 'desc');
    }
    params.set('per_page', String(perPage.value || 25));
    params.set('page', String(page));
    return params;
}

function visit(page = 1) {
    router.get(
        `/users?${buildParams(page).toString()}`,
        {},
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

function applyFilters() {
    visit(1);
}

function resetFilters() {
    filters.value = { role: '', activation: '', search: '' };
    visit(1);
}

function toggleSort(column: string) {
    if (sortColumn.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = column;
        sortDirection.value = 'asc';
    }
    visit(1);
}

function goToPage(page: number) {
    if (page < 1 || page > lastPage.value || page === currentPage.value) {
        return;
    }
    visit(page);
}

function changePerPage(value: number) {
    const params = buildParams(1);
    params.set('per_page', String(value));
    router.get(`/users?${params.toString()}`, {}, { preserveScroll: true, preserveState: true, replace: true });
}

const pending = ref<null | { type: 'delete' | 'toggle'; user: UserRow }>(null);

function askDelete(user: UserRow) {
    pending.value = { type: 'delete', user };
}

function askToggle(user: UserRow) {
    pending.value = { type: 'toggle', user };
}

function confirmPending() {
    const action = pending.value;
    if (!action) {
        return;
    }
    pending.value = null;
    if (action.type === 'delete') {
        router.delete(`/users/${action.user.id}`, { preserveScroll: true });
        return;
    }
    router.post(`/users/${action.user.id}/toggle`, {}, { preserveScroll: true });
}

const confirmTitle = computed(() => {
    if (!pending.value) {
        return '';
    }
    return pending.value.type === 'delete' ? 'Supprimer cet utilisateur' : pending.value.user.activated ? 'Désactiver cet utilisateur' : 'Activer cet utilisateur';
});

const confirmDescription = computed(() => {
    if (!pending.value) {
        return '';
    }
    const name = pending.value.user.name;
    if (pending.value.type === 'delete') {
        return `${name} sera retiré définitivement de la liste.`;
    }
    return pending.value.user.activated
        ? `${name} ne pourra plus se connecter tant que le compte reste désactivé.`
        : `${name} pourra de nouveau se connecter.`;
});

const confirmLabel = computed(() => {
    if (!pending.value) {
        return 'Confirmer';
    }
    if (pending.value.type === 'delete') {
        return 'Supprimer';
    }
    return pending.value.user.activated ? 'Désactiver' : 'Activer';
});

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
                <div class="border-b border-border/80 bg-gradient-to-r from-primary/5 via-card to-transparent px-5 py-4 sm:px-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm">
                                <Users class="size-5" />
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-primary">Paramétrage</p>
                                <h1 class="text-xl font-semibold tracking-tight text-foreground">Liste des utilisateurs</h1>
                                <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                                    <span><span class="font-semibold text-foreground">{{ stats.total }}</span> au total</span>
                                    <span class="text-emerald-700">{{ stats.actifs }} activé{{ stats.actifs > 1 ? 's' : '' }}</span>
                                    <span>{{ stats.inactifs }} désactivé{{ stats.inactifs > 1 ? 's' : '' }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Button variant="outline" class="h-9 border-slate-300" @click="showImportModal = true">
                                <Upload class="mr-2 size-4" />
                                Importer
                            </Button>
                            <Button as-child class="h-9">
                                <Link href="/users/create" class="inline-flex items-center gap-2">
                                    <Plus class="size-4" />
                                    Nouveau
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 border-b border-border/80 px-4 py-3 sm:px-5 lg:flex-row lg:items-center">
                    <div class="relative min-w-0 flex-1">
                        <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="filters.search"
                            type="search"
                            placeholder="Nom, fonction, e-mail, IDFLEX, entité…"
                            :class="[fieldClass, 'pl-9']"
                            @keyup.enter="applyFilters"
                        />
                    </div>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-[11rem_10rem_auto] lg:w-auto">
                        <select v-model="filters.role" :class="selectClass" @change="applyFilters">
                            <option value="">Tous les rôles</option>
                            <option v-for="role in props.roles || []" :key="role.id" :value="String(role.id)">
                                {{ role.nom }}
                            </option>
                        </select>
                        <select v-model="filters.activation" :class="selectClass" @change="applyFilters">
                            <option value="">Tous les statuts</option>
                            <option value="1">Activés</option>
                            <option value="0">Désactivés</option>
                        </select>
                        <div class="flex gap-2">
                            <Button type="button" class="h-9" @click="applyFilters">
                                <Search class="mr-2 size-4" />
                                Chercher
                            </Button>
                            <Button
                                v-if="hasFilters"
                                type="button"
                                variant="outline"
                                class="h-9 border-slate-300"
                                @click="resetFilters"
                            >
                                <RotateCcw class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[68rem] text-sm">
                        <thead class="border-b border-border/80 bg-muted/40 text-left text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                            <tr>
                                <th class="px-4 py-2.5 sm:px-5">
                                    <button type="button" class="inline-flex items-center gap-1" @click="toggleSort('name')">
                                        Utilisateur
                                        <ChevronUp v-if="sortColumn === 'name' && sortDirection === 'asc'" class="size-3.5" />
                                        <ChevronDown v-else-if="sortColumn === 'name'" class="size-3.5" />
                                    </button>
                                </th>
                                <th class="px-3 py-2.5">
                                    <button type="button" class="inline-flex items-center gap-1" @click="toggleSort('fonction')">
                                        Fonction
                                        <ChevronUp v-if="sortColumn === 'fonction' && sortDirection === 'asc'" class="size-3.5" />
                                        <ChevronDown v-else-if="sortColumn === 'fonction'" class="size-3.5" />
                                    </button>
                                </th>
                                <th class="px-3 py-2.5">
                                    <button type="button" class="inline-flex items-center gap-1" @click="toggleSort('idflex')">
                                        IDFLEX
                                        <ChevronUp v-if="sortColumn === 'idflex' && sortDirection === 'asc'" class="size-3.5" />
                                        <ChevronDown v-else-if="sortColumn === 'idflex'" class="size-3.5" />
                                    </button>
                                </th>
                                <th class="px-3 py-2.5">
                                    <button type="button" class="inline-flex items-center gap-1" @click="toggleSort('email')">
                                        E-mail
                                        <ChevronUp v-if="sortColumn === 'email' && sortDirection === 'asc'" class="size-3.5" />
                                        <ChevronDown v-else-if="sortColumn === 'email'" class="size-3.5" />
                                    </button>
                                </th>
                                <th class="px-3 py-2.5">Entité</th>
                                <th class="px-3 py-2.5">Rôles</th>
                                <th class="px-3 py-2.5">Statut</th>
                                <th class="px-4 py-2.5 text-right sm:px-5">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/70">
                            <tr v-for="user in users.data" :key="user.id" class="hover:bg-muted/30">
                                <td class="px-4 py-2.5 sm:px-5">
                                    <p class="truncate font-medium whitespace-nowrap text-foreground">{{ user.name }}</p>
                                </td>
                                <td class="max-w-[12rem] px-3 py-2.5">
                                    <p class="truncate whitespace-nowrap text-muted-foreground" :title="user.fonction || ''">
                                        {{ user.fonction || '—' }}
                                    </p>
                                </td>
                                <td class="px-3 py-2.5 font-medium whitespace-nowrap tabular-nums text-foreground">
                                    {{ user.matricule || '—' }}
                                </td>
                                <td class="max-w-[16rem] px-3 py-2.5">
                                    <p class="truncate whitespace-nowrap" :title="user.email">{{ user.email }}</p>
                                </td>
                                <td class="max-w-[12rem] px-3 py-2.5">
                                    <p class="truncate whitespace-nowrap" :title="user.agence ? `${user.agence.code} · ${user.agence.nom}` : ''">
                                        {{ entiteLabel(user) }}
                                    </p>
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="flex flex-nowrap items-center gap-1" :title="roleTitle(user.roles)">
                                        <span
                                            v-for="role in user.roles.slice(0, 2)"
                                            :key="role.id"
                                            class="max-w-[9rem] truncate rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium whitespace-nowrap text-primary ring-1 ring-primary/20"
                                        >
                                            {{ role.nom }}
                                        </span>
                                        <span
                                            v-if="user.roles.length > 2"
                                            class="rounded-full bg-muted px-1.5 py-0.5 text-xs font-medium whitespace-nowrap text-muted-foreground"
                                        >
                                            +{{ user.roles.length - 2 }}
                                        </span>
                                        <span v-if="user.roles.length === 0" class="text-xs text-muted-foreground italic">Aucun rôle</span>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-semibold whitespace-nowrap"
                                        :class="user.activated
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                            : 'border-slate-200 bg-slate-50 text-slate-600'"
                                    >
                                        <Unlock v-if="user.activated" class="size-3.5" />
                                        <Lock v-else class="size-3.5" />
                                        {{ user.activated ? 'Activé' : 'Désactivé' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 sm:px-5">
                                    <div class="flex justify-end">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <button
                                                    type="button"
                                                    class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                                                    aria-label="Actions"
                                                >
                                                    <MoreHorizontal class="size-4" />
                                                </button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-48">
                                                <DropdownMenuItem class="text-sm" @click="router.visit(`/users/${user.id}`)">
                                                    <Eye class="size-4" />
                                                    Voir
                                                </DropdownMenuItem>
                                                <DropdownMenuItem class="text-sm" @click="router.visit(`/users/${user.id}/edit`)">
                                                    <Pencil class="size-4" />
                                                    Modifier
                                                </DropdownMenuItem>
                                                <DropdownMenuItem class="text-sm" @click="askToggle(user)">
                                                    <Lock v-if="user.activated" class="size-4" />
                                                    <Unlock v-else class="size-4" />
                                                    {{ user.activated ? 'Désactiver' : 'Activer' }}
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem class="text-sm text-red-600 focus:text-red-600" @click="askDelete(user)">
                                                    <Trash2 class="size-4" />
                                                    Supprimer
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="users.data.length === 0">
                                <td colspan="8" class="px-5 py-10 text-center text-sm text-muted-foreground">
                                    Aucun utilisateur ne correspond à ces critères.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border/80 px-4 py-3 sm:px-5">
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <span>{{ rangeLabel }}</span>
                        <select
                            class="h-8 rounded-md border border-slate-300 bg-white px-2 text-sm text-foreground"
                            :value="perPage"
                            @change="changePerPage(Number(($event.target as HTMLSelectElement).value))"
                        >
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                        <span>par page</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <Button variant="ghost" size="sm" class="h-8" :disabled="currentPage <= 1" @click="goToPage(currentPage - 1)">
                            <ChevronLeft class="size-4" />
                            Précédent
                        </Button>
                        <template v-for="(page, index) in pageNumbers" :key="`${page}-${index}`">
                            <span v-if="typeof page !== 'number'" class="px-1 text-muted-foreground">…</span>
                            <Button
                                v-else
                                variant="ghost"
                                size="sm"
                                class="h-8 w-8"
                                :class="page === currentPage ? 'bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground' : ''"
                                @click="goToPage(page)"
                            >
                                {{ page }}
                            </Button>
                        </template>
                        <Button variant="ghost" size="sm" class="h-8" :disabled="currentPage >= lastPage" @click="goToPage(currentPage + 1)">
                            Suivant
                            <ChevronRight class="size-4" />
                        </Button>
                    </div>
                </div>
            </section>
        </div>

        <OdConfirmDialog
            :open="pending !== null"
            :title="confirmTitle"
            :description="confirmDescription"
            :confirm-label="confirmLabel"
            :variant="pending?.type === 'delete' ? 'danger' : pending?.user.activated ? 'warning' : 'success'"
            @update:open="(open) => { if (!open) pending = null; }"
            @confirm="confirmPending"
        />

        <Dialog :open="showImportModal" @update:open="showImportModal = $event">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Importer des utilisateurs</DialogTitle>
                </DialogHeader>
                <form class="space-y-4 py-4" @submit.prevent="submitImport">
                    <div class="rounded-lg border border-primary/20 bg-primary/5 p-3 text-sm text-foreground">
                        <p>Importez un fichier Excel ou CSV. Les lignes sans email, nom ou fonction sont ignorées.</p>
                        <p class="mt-2 font-medium">
                            <a href="/users/export-template" class="text-primary underline hover:text-primary/80">
                                Télécharger le template Excel
                            </a>
                        </p>
                        <p class="mt-2 text-xs text-muted-foreground">
                            Colonnes : Nom, Fonction, Email, IDFLEX, Mot de passe (optionnel), Role (slug ou nom), Departement, Code agence.
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
                            @input="importForm.file = ($event.target as HTMLInputElement).files?.[0] ?? null"
                        />
                        <p v-if="importForm.errors.file" class="text-sm text-red-600">{{ importForm.errors.file }}</p>
                    </div>
                    <DialogFooter class="pt-4">
                        <Button type="button" variant="outline" class="border-slate-300" :disabled="importForm.processing" @click="showImportModal = false">
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
