<script setup lang="ts">
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import DataTable from '@/components/DataTable.vue';
import ExpirationBar from '@/components/ExpirationBar.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatCardNumberDisplay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    Building2,
    CreditCard,
    Download,
    LayoutDashboard,
    MoreHorizontal,
    Plus,
    Layers,
    Pencil,
    RotateCcw,
    Eye,
    Trash2,
} from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Cartes', href: '/monetique/cartes/en-stock' },
    { title: 'En Stock', href: '/monetique/cartes/en-stock' },
];

export type StockStatutKey = 'au_siege' | 'en_agence' | 'en_vente' | 'en_attente_encaissement';

type StockCardRow = {
    id?: number;
    numero_carte: string;
    numero_lot?: string | null;
    prix_vente: number;
    prix_achat?: number | null;
    reference_facture: string;
    reference_bon_livraison?: string | null;
    date_livraison?: string | null;
    possesseur: string;
    agence_nom?: string | null;
    agence_code?: string | null;
    statut_key: StockStatutKey;
    expiration: string;
    date_expiration?: string;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    per_page: number;
    total: number;
};

type RefFactureRow = {
    reference_facture: string;
    cards_count: number;
};

type LotRow = {
    value: string;
    label: string;
    cards_count: number;
};

export type StockPanoramaTotals = {
    cartes: number;
    valeur_stock_cfa: number;
    attente_caisse: number;
    au_siege: number;
    en_agence_stock: number;
    en_vente_cc: number;
};

export type StockPanoramaAgenceRow = {
    agence_id: number | null;
    agence_nom: string;
    agence_code: string | null;
    total: number;
    valeur_stock_cfa: number;
    attente_caisse: number;
    au_siege: number;
    en_agence_stock: number;
    en_vente_cc: number;
};

export type StockPanorama = {
    totals: StockPanoramaTotals;
    par_agence: StockPanoramaAgenceRow[];
};

const props = withDefaults(
    defineProps<{
        cards?: Paginated<StockCardRow>;
        stockPanorama?: StockPanorama | null;
        references?: RefFactureRow[];
        lots?: LotRow[];
        filters?: {
            q: string;
            reference_facture: string;
            numero_lot: string;
            statut: string;
        };
    }>(),
    {
        cards: () => ({
            data: [],
            current_page: 1,
            per_page: 15,
            total: 0,
        }),
        stockPanorama: null,
        references: () => [],
        lots: () => [],
        filters: () => ({
            q: '',
            reference_facture: '',
            numero_lot: '',
            statut: '',
        }),
    },
);

const cardPagination = computed(() => {
    const c = props.cards;
    return {
        data: (c?.data ?? []) as StockCardRow[],
        current_page: c?.current_page ?? 1,
        per_page: c?.per_page ?? 15,
        total: c?.total ?? 0,
    };
});

const qLocal = ref(props.filters.q ?? '');
const referenceLocal = ref(props.filters.reference_facture ?? '');
const lotLocal = ref(props.filters.numero_lot ?? '');
const statutLocal = ref(props.filters.statut ?? '');

const rows = computed(() => cardPagination.value.data);

const listQuery = () => ({
    q: qLocal.value.trim() || undefined,
    reference_facture: referenceLocal.value || undefined,
    numero_lot: lotLocal.value || undefined,
    statut: statutLocal.value || undefined,
    per_page: cardPagination.value.per_page,
});

const applyFilters = () => {
    router.get('/monetique/cartes/en-stock', { ...listQuery(), page: 1 }, {
        preserveState: true,
        preserveScroll: true,
        only: ['cards', 'references', 'lots', 'filters'],
    });
};

const resetFilters = () => {
    qLocal.value = '';
    referenceLocal.value = '';
    lotLocal.value = '';
    statutLocal.value = '';
    router.get('/monetique/cartes/en-stock', { per_page: cardPagination.value.per_page, page: 1 }, {
        preserveState: true,
        preserveScroll: true,
        only: ['cards', 'references', 'lots', 'filters'],
    });
};

const onReferenceChange = () => {
    lotLocal.value = '';
    applyFilters();
};

const onPageChange = (page: number) => {
    router.get('/monetique/cartes/en-stock', { ...listQuery(), page }, {
        preserveState: true,
        preserveScroll: true,
        only: ['cards', 'references', 'lots', 'filters'],
    });
};

const onItemsPerPageChange = (perPage: number) => {
    router.get('/monetique/cartes/en-stock', { ...listQuery(), per_page: perPage, page: 1 }, {
        preserveState: true,
        preserveScroll: true,
        only: ['cards', 'references', 'lots', 'filters'],
    });
};

const statutLabel = (k: StockStatutKey) => {
    switch (k) {
        case 'au_siege':
            return 'Au siège';
        case 'en_agence':
            return 'En agence';
        case 'en_vente':
            return 'En vente';
        case 'en_attente_encaissement':
            return 'Attente caisse';
    }
};

function statutCourt(r: StockCardRow): string {
    const base = statutLabel(r.statut_key);
    if (r.statut_key === 'au_siege') {
        return base;
    }
    const code = r.agence_code?.trim();
    return code ? `${base} · ${code}` : base;
}

function statutDetail(r: StockCardRow): string {
    const nom = r.agence_nom?.trim();
    if (!nom || r.statut_key === 'au_siege') {
        return statutLabel(r.statut_key);
    }
    const code = r.agence_code?.trim() ? ` (${r.agence_code})` : '';
    return `${statutLabel(r.statut_key)} — ${nom}${code}`;
}

const columns = [
    { key: 'numero_carte', title: 'N° carte' },
    { key: 'numero_lot', title: 'Lot' },
    { key: 'prix_vente', title: 'Prix' },
    { key: 'reference_facture', title: 'Facture' },
    { key: 'possesseur', title: 'Possesseur' },
    { key: 'statut', title: 'Statut' },
    { key: 'expiration', title: 'Expire' },
    { key: 'actions', title: '' },
];

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);
const canResponsableMonetique = computed(() => page.props.auth.canResponsableMonetique === true);
const isSuperAdmin = computed(() => page.props.auth.isSuperAdmin === true);
const stockPanorama = computed(() => props.stockPanorama ?? null);
const panoramaOuvert = ref(false);
const formatCfa = (n: number) => `${n.toLocaleString('fr-FR')} F CFA`;
const reload = () => router.reload({ only: ['cards', 'stockPanorama', 'references', 'lots', 'filters'] });

const deleteOpen = ref(false);
const deleteCard = ref<StockCardRow | null>(null);
const deleteProcessing = ref(false);
const deleteError = ref('');

function openDeleteDialog(row: StockCardRow) {
    deleteCard.value = row;
    deleteError.value = '';
    deleteOpen.value = true;
}

function closeDeleteDialog() {
    if (deleteProcessing.value) return;
    deleteOpen.value = false;
    deleteCard.value = null;
    deleteError.value = '';
}

function confirmDelete() {
    if (!deleteCard.value?.id) return;
    deleteProcessing.value = true;
    deleteError.value = '';
    router.delete(`/monetique/cartes/${deleteCard.value.id}`, {
        preserveScroll: true,
        onError: (errs) => {
            deleteError.value = (errs as Record<string, string>).card
                ?? (errs as Record<string, string>).message
                ?? 'Suppression impossible.';
        },
        onSuccess: () => {
            deleteOpen.value = false;
            deleteCard.value = null;
        },
        onFinish: () => {
            deleteProcessing.value = false;
        },
    });
}

const editOpen = ref(false);
const editCardId = ref<number | null>(null);
const editProcessing = ref(false);
const editError = ref('');
const editFieldErrors = ref<Record<string, string>>({});
const editForm = ref({
    numero_carte: '',
    numero_lot: '',
    reference_facture: '',
    reference_bon_livraison: '',
    prix_vente: '' as number | '',
    prix_achat: '' as number | '',
    date_livraison: '',
    date_expiration: '',
});

function openEditDialog(row: StockCardRow) {
    if (!row.id) return;
    editCardId.value = row.id;
    editError.value = '';
    editFieldErrors.value = {};
    editForm.value = {
        numero_carte: row.numero_carte ?? '',
        numero_lot: row.numero_lot ?? '',
        reference_facture: row.reference_facture ?? '',
        reference_bon_livraison: row.reference_bon_livraison ?? '',
        prix_vente: row.prix_vente ?? '',
        prix_achat: row.prix_achat ?? '',
        date_livraison: row.date_livraison ?? '',
        date_expiration: row.date_expiration ?? '',
    };
    editOpen.value = true;
}

function closeEditDialog() {
    if (editProcessing.value) return;
    editOpen.value = false;
    editCardId.value = null;
    editError.value = '';
    editFieldErrors.value = {};
}

function confirmEdit() {
    if (!editCardId.value) return;
    editProcessing.value = true;
    editError.value = '';
    editFieldErrors.value = {};
    router.put(`/monetique/cartes/${editCardId.value}`, { ...editForm.value }, {
        preserveScroll: true,
        onError: (errs) => {
            const e = errs as Record<string, string>;
            editFieldErrors.value = e;
            editError.value = e.card ?? e.message ?? '';
        },
        onSuccess: () => {
            editOpen.value = false;
            editCardId.value = null;
        },
        onFinish: () => {
            editProcessing.value = false;
        },
    });
}

const statutBadgeClass = (k: StockStatutKey) => {
    switch (k) {
        case 'en_vente':
            return 'bg-emerald-50 text-emerald-700 border-emerald-100';
        case 'en_attente_encaissement':
            return 'bg-amber-50 text-amber-800 border-amber-100';
        case 'en_agence':
            return 'bg-primary/5 text-primary border-primary/20';
        case 'au_siege':
            return 'bg-gray-100 text-gray-700 border-gray-200';
    }
};
</script>

<template>
    <Head title="Monétique - Cartes - En Stock" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Liste des coficartes</h1>
                </div>

                <div class="flex items-center gap-2">
                    <Button variant="outline" class="bg-white">
                        <Download class="h-4 w-4 mr-2" />
                        Export
                    </Button>
                    <template v-if="canResponsableMonetique">
                        <Button class="bg-primary hover:bg-primary/90" @click="router.visit('/monetique/cartes/ajouter')">
                            <Plus class="h-4 w-4 mr-2" />
                            Ajouter
                        </Button>
                        <Button class="bg-primary hover:bg-primary/90" @click="router.visit('/monetique/cartes/modifier-prix')">
                            <Pencil class="h-4 w-4 mr-2" />
                            Modifier Prix
                        </Button>
                        <Button class="bg-violet-700 hover:bg-violet-800" @click="router.visit('/monetique/cartes/modifier-lots')">
                            <Layers class="h-4 w-4 mr-2" />
                            Modifier lots
                        </Button>
                    </template>
                    <Button variant="outline" class="bg-white" title="Recharger les données depuis le serveur" @click="reload">
                        <RotateCcw class="h-4 w-4 mr-2" />
                        Actualiser
                    </Button>
                </div>
            </div>

            <section
                v-if="stockPanorama"
                class="space-y-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
            >
                <div class="flex items-center justify-between gap-3">
                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                        <LayoutDashboard class="h-4 w-4 text-primary" />
                        Stock
                    </span>
                    <button
                        v-if="!panoramaOuvert"
                        type="button"
                        class="text-xs font-medium text-primary hover:underline"
                        @click="panoramaOuvert = true"
                    >
                        Répartition
                    </button>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Cartes</p>
                        <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ stockPanorama.totals.cartes }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide flex items-center gap-1">
                            <Banknote class="h-3.5 w-3.5" /> Valeur stock
                        </p>
                        <p class="text-lg font-bold text-gray-900 tabular-nums">{{ formatCfa(stockPanorama.totals.valeur_stock_cfa) }}</p>
                    </div>
                    <div class="rounded-lg border border-amber-100 bg-amber-50/80 px-4 py-3">
                        <p class="text-xs font-medium text-amber-800 uppercase tracking-wide">Attente caisse</p>
                        <p class="text-2xl font-bold text-amber-900 tabular-nums">{{ stockPanorama.totals.attente_caisse }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Au siège</p>
                        <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ stockPanorama.totals.au_siege }}</p>
                    </div>
                    <div class="rounded-lg border border-primary/20 bg-primary/5 px-4 py-3">
                        <p class="text-xs font-medium text-primary uppercase tracking-wide">Stock agence</p>
                        <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ stockPanorama.totals.en_agence_stock }}</p>
                    </div>
                    <div class="rounded-lg border border-emerald-100 bg-emerald-50/60 px-4 py-3">
                        <p class="text-xs font-medium text-emerald-800 uppercase tracking-wide">En vente (CC)</p>
                        <p class="text-2xl font-bold text-emerald-900 tabular-nums">{{ stockPanorama.totals.en_vente_cc }}</p>
                    </div>
                </div>

                <div v-show="panoramaOuvert" class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                    <div class="flex items-center justify-end border-b border-gray-200 bg-gray-50/90 px-3 py-1.5">
                        <button
                            type="button"
                            class="text-xs font-medium text-primary hover:underline"
                            @click="panoramaOuvert = false"
                        >
                            Masquer
                        </button>
                    </div>
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50/90 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                <th class="px-4 py-3">Entité</th>
                            
                                <th class="px-4 py-3 text-right tabular-nums">Siège</th>
                                <th class="px-4 py-3 text-right tabular-nums">en agence</th>
                                <th class="px-4 py-3 text-right tabular-nums">Niveau CC</th>
                                    <th class="px-4 py-3 text-right tabular-nums">Total Cartes</th>
                                <th class="px-4 py-3 text-right tabular-nums">Valeur Totale</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in stockPanorama.par_agence"
                                :key="row.agence_id ?? 'siege'"
                                class="border-b border-gray-100 last:border-0 hover:bg-gray-50/80"
                            >
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <Building2 class="h-4 w-4 shrink-0 text-gray-400" />
                                        <div>
                                            <span class="font-medium text-gray-900">{{ row.agence_nom }}</span>
                                            <span v-if="row.agence_code" class="ml-2 text-xs text-gray-500 tabular-nums">({{ row.agence_code }})</span>
                                        </div>
                                    </div>
                                </td>
                               
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ row.au_siege }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-primary">{{ row.en_agence_stock }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-emerald-800">{{ row.en_vente_cc }}</td>
                                 <td class="px-4 py-2.5 text-right font-semibold tabular-nums text-gray-900">{{ row.total }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-gray-700">{{ formatCfa(row.valeur_stock_cfa) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div
                v-if="flash?.success"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            >
                {{ flash.success }}
            </div>
            <div
                v-if="flash?.error"
                class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"
            >
                {{ flash.error }}
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                <div class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 xl:grid-cols-6">
                    <div class="space-y-2">
                        <Label for="reference_facture" class="text-xs font-medium text-gray-600">Référence facture</Label>
                        <select
                            id="reference_facture"
                            v-model="referenceLocal"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900"
                            @change="onReferenceChange"
                        >
                            <option value="">-- Toutes --</option>
                            <option v-for="r in references" :key="r.reference_facture" :value="r.reference_facture">
                                {{ r.reference_facture }} ({{ r.cards_count }})
                            </option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <Label for="numero_lot" class="text-xs font-medium text-gray-600">Numéro de lot</Label>
                        <select
                            id="numero_lot"
                            v-model="lotLocal"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900"
                            @change="applyFilters"
                        >
                            <option value="">-- Tous --</option>
                            <option v-for="l in lots" :key="l.value" :value="l.value">
                                {{ l.label }} ({{ l.cards_count }})
                            </option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <Label for="statut" class="text-xs font-medium text-gray-600">Statut</Label>
                        <select
                            id="statut"
                            v-model="statutLocal"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900"
                            @change="applyFilters"
                        >
                            <option value="">-- Tous --</option>
                            <option value="au_siege">Au siège</option>
                            <option value="en_agence">En agence</option>
                            <option value="en_vente">En vente</option>
                            <option value="en_attente_encaissement">Attente encaissement</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <Label for="search" class="text-xs font-medium text-gray-600">Recherche</Label>
                        <Input
                            id="search"
                            v-model="qLocal"
                            placeholder="N° carte, lot, facture…"
                            class="border-gray-300"
                            @keydown.enter="applyFilters"
                        />
                    </div>
                    <div class="flex flex-wrap items-center gap-2 xl:col-span-2">
                        <Button type="button" class="bg-primary hover:bg-primary/90" @click="applyFilters">
                            Filtrer
                        </Button>
                        <Button type="button" variant="outline" class="bg-white" @click="resetFilters">
                            Réinitialiser
                        </Button>
                    </div>
                </div>
            </div>

            <DataTable
                :headers="columns"
                :items="rows"
                :show-select="false"
                :current-page="cardPagination.current_page"
                :items-per-page="cardPagination.per_page"
                :total-items="cardPagination.total"
                :on-page-change="onPageChange"
                :on-items-per-page-change="onItemsPerPageChange"
            >
                <template #item.numero_carte="{ item }">
                    <div class="flex items-center gap-3 whitespace-nowrap">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-rose-100 bg-rose-50 text-rose-600">
                            <CreditCard class="h-4 w-4" />
                        </div>
                        <span class="font-mono font-medium tabular-nums text-gray-900">
                            {{ formatCardNumberDisplay(item.numero_carte) }}
                        </span>
                    </div>
                </template>

                <template #item.numero_lot="{ item }">
                    <span class="whitespace-nowrap text-sm text-gray-700">{{ item.numero_lot || '—' }}</span>
                </template>

                <template #item.prix_vente="{ item }">
                    <span class="whitespace-nowrap text-sm text-gray-600">{{ formatCfa(item.prix_vente) }}</span>
                </template>

                <template #item.reference_facture="{ item }">
                    <span class="whitespace-nowrap text-sm text-gray-700">{{ item.reference_facture || '—' }}</span>
                </template>

                <template #item.possesseur="{ item }">
                    <span class="whitespace-nowrap text-sm text-gray-800">{{ item.possesseur || '—' }}</span>
                </template>

                <template #item.statut="{ item }">
                    <span
                        class="inline-flex whitespace-nowrap rounded-md px-2 py-0.5 text-xs font-semibold border"
                        :class="statutBadgeClass(item.statut_key)"
                        :title="statutDetail(item)"
                    >
                        {{ statutCourt(item) }}
                    </span>
                </template>

                <template #item.expiration="{ item }">
                    <div class="w-36">
                        <ExpirationBar :expiration="item.expiration" :date-expiration="item.date_expiration" />
                    </div>
                </template>

                <template #item.actions="{ item }">
                    <div class="flex justify-end">
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button
                                    type="button"
                                    class="inline-flex size-8 items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                                    aria-label="Actions"
                                >
                                    <MoreHorizontal class="h-4 w-4" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-44">
                                <DropdownMenuItem
                                    v-if="item.id"
                                    class="text-sm"
                                    @click="router.visit(`/monetique/cartes/${item.id}/mouvements`)"
                                >
                                    <Eye class="size-4" />
                                    Mouvements
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="isSuperAdmin && item.id"
                                    class="text-sm"
                                    @click="openEditDialog(item)"
                                >
                                    <Pencil class="size-4" />
                                    Modifier
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="isSuperAdmin && item.id"
                                    class="text-sm text-rose-700 focus:text-rose-700"
                                    @click="openDeleteDialog(item)"
                                >
                                    <Trash2 class="size-4" />
                                    Supprimer
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </template>
            </DataTable>
        </div>

        <Dialog :open="editOpen" @update:open="(v) => { if (!v) closeEditDialog(); }">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="inline-flex items-center gap-2">
                        <Pencil class="size-5" />
                        Modifier la carte
                    </DialogTitle>
                    <DialogDescription>
                        Réservé au super administrateur. Les cartes déjà vendues ne peuvent pas être modifiées.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="edit_numero_carte">Numéro de carte</Label>
                        <Input id="edit_numero_carte" v-model="editForm.numero_carte" class="font-mono" />
                        <p v-if="editFieldErrors.numero_carte" class="text-xs text-rose-600">{{ editFieldErrors.numero_carte }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="edit_numero_lot">Numéro de lot</Label>
                        <Input id="edit_numero_lot" v-model="editForm.numero_lot" />
                        <p v-if="editFieldErrors.numero_lot" class="text-xs text-rose-600">{{ editFieldErrors.numero_lot }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="edit_reference_facture">Référence facture</Label>
                        <Input id="edit_reference_facture" v-model="editForm.reference_facture" />
                        <p v-if="editFieldErrors.reference_facture" class="text-xs text-rose-600">{{ editFieldErrors.reference_facture }}</p>
                    </div>
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="edit_reference_bon_livraison">Référence bon de livraison</Label>
                        <Input id="edit_reference_bon_livraison" v-model="editForm.reference_bon_livraison" />
                        <p v-if="editFieldErrors.reference_bon_livraison" class="text-xs text-rose-600">{{ editFieldErrors.reference_bon_livraison }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="edit_prix_vente">Prix de vente (F CFA)</Label>
                        <Input id="edit_prix_vente" v-model="editForm.prix_vente" type="number" min="0" />
                        <p v-if="editFieldErrors.prix_vente" class="text-xs text-rose-600">{{ editFieldErrors.prix_vente }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="edit_prix_achat">Prix d’achat (F CFA)</Label>
                        <Input id="edit_prix_achat" v-model="editForm.prix_achat" type="number" min="0" />
                        <p v-if="editFieldErrors.prix_achat" class="text-xs text-rose-600">{{ editFieldErrors.prix_achat }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="edit_date_livraison">Date de livraison</Label>
                        <Input id="edit_date_livraison" v-model="editForm.date_livraison" type="date" />
                        <p v-if="editFieldErrors.date_livraison" class="text-xs text-rose-600">{{ editFieldErrors.date_livraison }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="edit_date_expiration">Date d’expiration</Label>
                        <Input id="edit_date_expiration" v-model="editForm.date_expiration" type="date" />
                        <p v-if="editFieldErrors.date_expiration" class="text-xs text-rose-600">{{ editFieldErrors.date_expiration }}</p>
                    </div>
                </div>
                <p v-if="editError" class="text-sm text-rose-700">{{ editError }}</p>
                <DialogFooter class="gap-2">
                    <Button type="button" variant="outline" class="bg-white" :disabled="editProcessing" @click="closeEditDialog">
                        Annuler
                    </Button>
                    <Button
                        type="button"
                        class="bg-primary hover:bg-primary/90"
                        :disabled="editProcessing"
                        @click="confirmEdit"
                    >
                        {{ editProcessing ? 'Enregistrement…' : 'Enregistrer' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="deleteOpen" @update:open="(v) => { if (!v) closeDeleteDialog(); }">
            <DialogContent class="sm:max-w-md border-rose-200/80 bg-rose-50/40">
                <DialogHeader>
                    <DialogTitle class="inline-flex items-center gap-2 text-rose-950">
                        <Trash2 class="size-5" />
                        Supprimer la carte
                    </DialogTitle>
                    <DialogDescription class="text-rose-900/80">
                        Cette action est définitive. Réservée au super administrateur.
                    </DialogDescription>
                </DialogHeader>
                <div v-if="deleteCard" class="rounded-lg border border-rose-200 bg-white/80 px-3 py-2.5 text-sm text-slate-700">
                    <p class="font-mono tabular-nums font-medium">
                        {{ formatCardNumberDisplay(deleteCard.numero_carte) }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        Facture {{ deleteCard.reference_facture || '—' }}
                        · lot {{ deleteCard.numero_lot || '—' }}
                    </p>
                </div>
                <p v-if="deleteError" class="text-sm text-rose-700">{{ deleteError }}</p>
                <DialogFooter class="gap-2">
                    <Button type="button" variant="outline" class="bg-white" :disabled="deleteProcessing" @click="closeDeleteDialog">
                        Annuler
                    </Button>
                    <Button
                        type="button"
                        class="bg-rose-600 text-white hover:bg-rose-700"
                        :disabled="deleteProcessing"
                        @click="confirmDelete"
                    >
                        {{ deleteProcessing ? 'Suppression…' : 'Confirmer la suppression' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
