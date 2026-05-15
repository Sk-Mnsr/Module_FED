<script setup lang="ts">
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import DataTable from '@/components/DataTable.vue';
import ExpirationBar from '@/components/ExpirationBar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCardNumberDisplay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    Building2,
    CreditCard,
    Download,
    Landmark,
    LayoutDashboard,
    Plus,
    Pencil,
    RotateCcw,
    Eye,
    ShoppingBag,
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
    prix_vente: number;
    reference_facture: string;
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
    }>(),
    {
        cards: () => ({
            data: [],
            current_page: 1,
            per_page: 15,
            total: 0,
        }),
        stockPanorama: null,
    },
);

/** Métadonnées serveur (paginator Laravel → Inertia, snake_case). */
const cardPagination = computed(() => {
    const c = props.cards;
    return {
        data: (c?.data ?? []) as StockCardRow[],
        current_page: c?.current_page ?? 1,
        per_page: c?.per_page ?? 15,
        total: c?.total ?? 0,
    };
});

const possesseur = ref('');
const statutFiltre = ref<'' | StockStatutKey>('');
const search = ref('');

const rows = computed(() => cardPagination.value.data);

const listQuery = () => ({
    per_page: cardPagination.value.per_page,
});

const onPageChange = (page: number) => {
    router.get('/monetique/cartes/en-stock', { ...listQuery(), page }, {
        preserveState: true,
        preserveScroll: true,
        only: ['cards'],
    });
};

const onItemsPerPageChange = (perPage: number) => {
    router.get('/monetique/cartes/en-stock', { per_page: perPage, page: 1 }, {
        preserveState: true,
        preserveScroll: true,
        only: ['cards'],
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

/** Libellé statut avec entité (agence) quand elle existe. */
function statutLabelAvecAgence(r: StockCardRow): string {
    const base = statutLabel(r.statut_key);
    if (r.statut_key === 'au_siege' || !r.agence_nom?.trim()) {
        return base;
    }
    const code = r.agence_code?.trim() ? ` (${r.agence_code})` : '';
    return `${base} · ${r.agence_nom}${code}`;
}

const filteredRows = computed(() => {
    const q = search.value.trim().toLowerCase();
    return rows.value.filter((r) => {
        const byPossesseur = possesseur.value ? r.possesseur === possesseur.value : true;
        const byStatut = statutFiltre.value ? r.statut_key === statutFiltre.value : true;
        const label = statutLabelAvecAgence(r).toLowerCase();
        const bySearch = !q
            ? true
            : [
                  r.numero_carte,
                  r.reference_facture,
                  r.possesseur,
                  r.agence_nom ?? '',
                  r.agence_code ?? '',
                  label,
                  String(r.prix_vente),
                  r.expiration,
              ].some((x) => x.toLowerCase().includes(q));
        return byPossesseur && byStatut && bySearch;
    });
});

const columns = [
    { key: 'numero_carte', title: 'Numéro de carte' },
    { key: 'prix_vente', title: 'Prix de vente' },
    { key: 'reference_facture', title: 'Référence de la facture' },
    { key: 'possesseur', title: 'Possesseur' },
    { key: 'statut', title: 'Statut' },
    { key: 'expiration', title: 'Expiration' },
    { key: 'actions', title: 'Actions' },
];

const possesseurs = computed(() => {
    const unique = Array.from(new Set(rows.value.map((r) => r.possesseur))).filter(Boolean);
    return unique.sort((a, b) => a.localeCompare(b));
});

const page = usePage();

const canResponsableMonetique = computed(() => page.props.auth.canResponsableMonetique === true);

const stockPanorama = computed(() => props.stockPanorama ?? null);

const formatCfa = (n: number) => `${n.toLocaleString('fr-FR')} F CFA`;

const reload = () => router.reload({ only: ['cards', 'stockPanorama'] });

const statutBadgeClass = (k: StockStatutKey) => {
    switch (k) {
        case 'en_vente':
            return 'bg-emerald-50 text-emerald-700 border-emerald-100';
        case 'en_attente_encaissement':
            return 'bg-amber-50 text-amber-800 border-amber-100';
        case 'en_agence':
            return 'bg-violet-50 text-violet-700 border-violet-100';
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
                        <Button class="bg-violet-600 hover:bg-violet-700" @click="router.visit('/monetique/cartes/ajouter')">
                            <Plus class="h-4 w-4 mr-2" />
                            Ajouter
                        </Button>
                        <Button class="bg-violet-600 hover:bg-violet-700" @click="router.visit('/monetique/cartes/modifier-prix')">
                            <Pencil class="h-4 w-4 mr-2" />
                            Modifier Prix
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
                class="rounded-xl border border-violet-200/80 bg-gradient-to-br from-violet-50/90 via-white to-white p-6 shadow-sm space-y-5"
            >
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-violet-100 p-2 text-violet-700">
                        <LayoutDashboard class="h-5 w-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Stock coficartes</h2>
                        <p class="text-sm text-gray-600 mt-0.5">
                            Totaux tous périmètres (siège et agences) — répartition par entité.
                        </p>
                    </div>
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
                    <div class="rounded-lg border border-violet-100 bg-violet-50/60 px-4 py-3">
                        <p class="text-xs font-medium text-violet-800 uppercase tracking-wide">Stock agence</p>
                        <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ stockPanorama.totals.en_agence_stock }}</p>
                    </div>
                    <div class="rounded-lg border border-emerald-100 bg-emerald-50/60 px-4 py-3">
                        <p class="text-xs font-medium text-emerald-800 uppercase tracking-wide">En vente (CC)</p>
                        <p class="text-2xl font-bold text-emerald-900 tabular-nums">{{ stockPanorama.totals.en_vente_cc }}</p>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
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
                                <td class="px-4 py-2.5 text-right tabular-nums text-violet-800">{{ row.en_agence_stock }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-emerald-800">{{ row.en_vente_cc }}</td>
                                 <td class="px-4 py-2.5 text-right font-semibold tabular-nums text-gray-900">{{ row.total }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-gray-700">{{ formatCfa(row.valeur_stock_cfa) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
                <div>
                    <p class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Filtres</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <Label for="possesseur" class="text-xs font-medium text-gray-600">Chef d'agence</Label>
                        <select
                            id="possesseur"
                            v-model="possesseur"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900"
                        >
                            <option value="">-- Tous --</option>
                            <option v-for="p in possesseurs" :key="p" :value="p">{{ p }}</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <Label for="statut" class="text-xs font-medium text-gray-600">Statut</Label>
                        <select
                            id="statut"
                            v-model="statutFiltre"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900"
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
                            v-model="search"
                            placeholder="Recherche une carte"
                            class="border-gray-300"
                        />
                    </div>
                </div>
            </div>

            <DataTable
                :headers="columns"
                :items="filteredRows"
                :show-select="false"
                :current-page="cardPagination.current_page"
                :items-per-page="cardPagination.per_page"
                :total-items="cardPagination.total"
                :on-page-change="onPageChange"
                :on-items-per-page-change="onItemsPerPageChange"
            >
                <template #item.numero_carte="{ item }">
                    <div class="flex items-center gap-3">
                        <div class="h-8 w-8 rounded-full bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600">
                            <CreditCard class="h-4 w-4" />
                        </div>
                        <span class="font-medium font-mono text-gray-900 tabular-nums">
                            {{ formatCardNumberDisplay(item.numero_carte) }}
                        </span>
                    </div>
                </template>

                <template #item.prix_vente="{ item }">
                    <span class="text-sm text-gray-600">{{ formatCfa(item.prix_vente) }}</span>
                </template>

                <template #item.statut="{ item }">
                    <span
                        class="inline-flex items-start gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold border max-w-[min(100%,260px)]"
                        :class="statutBadgeClass(item.statut_key)"
                    >
                        <Landmark v-if="item.statut_key === 'au_siege'" class="h-3.5 w-3.5 shrink-0 mt-px" />
                        <Building2 v-else-if="item.statut_key === 'en_agence'" class="h-3.5 w-3.5 shrink-0 mt-px" />
                        <ShoppingBag v-else-if="item.statut_key === 'en_vente'" class="h-3.5 w-3.5 shrink-0 mt-px" />
                        <span class="leading-snug text-left break-words">{{ statutLabelAvecAgence(item) }}</span>
                    </span>
                </template>

                <template #item.expiration="{ item }">
                    <ExpirationBar :expiration="item.expiration" :date-expiration="item.date_expiration" />
                </template>

                <template #item.actions="{ item }">
                    <div class="flex items-center justify-end gap-1">
                        <button
                            v-if="item.id"
                            type="button"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                            title="Mouvements"
                            @click="router.visit(`/monetique/cartes/${item.id}/mouvements`)"
                        >
                            <Eye class="h-4 w-4" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
