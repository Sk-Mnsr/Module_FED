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
import { CreditCard, Download, Plus, RotateCcw, Eye, Pencil, Trash2 } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Cartes', href: '/monetique/cartes/vendus' },
    { title: 'Vendus', href: '/monetique/cartes/vendus' },
];

type SoldCardRow = {
    id?: number;
    numero_carte: string;
    date_livraison: string;
    prix_vente: number;
    vendeur: string;
    expiration: string;
    date_expiration?: string;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    per_page: number;
    total: number;
};

const props = withDefaults(
    defineProps<{
        cards?: Paginated<SoldCardRow>;
    }>(),
    {
        cards: () => ({
            data: [],
            current_page: 1,
            per_page: 15,
            total: 0,
        }),
    },
);

const page = usePage<{
    auth?: { canResponsableMonetique?: boolean };
}>();

const canResponsableMonetique = computed(() => page.props.auth?.canResponsableMonetique === true);

const vendeur = ref('');
const search = ref('');

const rows = computed(() => props.cards?.data ?? []);

const vendeurs = computed(() => {
    const unique = Array.from(new Set(rows.value.map((r) => r.vendeur))).filter(Boolean);
    return unique.sort((a, b) => a.localeCompare(b));
});

const filteredRows = computed(() => {
    const q = search.value.trim().toLowerCase();
    return rows.value.filter((r) => {
        const byVendeur = vendeur.value ? r.vendeur === vendeur.value : true;
        const bySearch = !q
            ? true
            : [
                  r.numero_carte,
                  r.date_livraison,
                  String(r.prix_vente),
                  r.vendeur,
                  r.expiration,
              ].some((x) => x.toLowerCase().includes(q));
        return byVendeur && bySearch;
    });
});

const columns = [
    { key: 'numero_carte', title: 'Numéro de carte' },
    { key: 'date_livraison', title: 'Date de livraison' },
    { key: 'prix_vente', title: 'Prix de vente' },
    { key: 'vendeur', title: 'Vendeur' },
    { key: 'expiration', title: 'Expiration' },
    { key: 'actions', title: 'Actions' },
];

const formatCfa = (n: number) => `${n.toLocaleString('fr-FR')} F CFA`;

const reload = () => router.reload({ only: ['cards'] });
</script>

<template>
    <Head title="Monétique - Cartes - Vendus" />

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
                    <Button
                        v-if="canResponsableMonetique"
                        class="bg-violet-600 hover:bg-violet-700"
                        @click="router.visit('/monetique/cartes/ajouter')"
                    >
                        <Plus class="h-4 w-4 mr-2" />
                        Ajouter
                    </Button>
                    <Button class="bg-violet-600 hover:bg-violet-700" @click="reload">
                        <RotateCcw class="h-4 w-4 mr-2" />
                        Recharger
                    </Button>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
                <div>
                    <p class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Filtres</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="vendeur" class="text-xs font-medium text-gray-600">Vendeur</Label>
                        <select
                            id="vendeur"
                            v-model="vendeur"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900"
                        >
                            <option value="">-- Tous --</option>
                            <option v-for="v in vendeurs" :key="v" :value="v">{{ v }}</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <Label for="search" class="text-xs font-medium text-gray-600">Recherche</Label>
                        <Input
                            id="search"
                            v-model="search"
                            placeholder="Rechercher un credit-card"
                            class="border-gray-300"
                        />
                    </div>
                </div>
            </div>

            <DataTable
                :headers="columns"
                :items="filteredRows"
                :show-select="false"
                :current-page="props.cards.current_page"
                :items-per-page="props.cards.per_page"
                :total-items="props.cards.total"
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

                <template #item.expiration="{ item }">
                    <ExpirationBar :expiration="item.expiration" :date-expiration="item.date_expiration" />
                </template>

                <template #item.actions="{ item }">
                    <div class="flex items-center justify-end gap-1">
                        <button
                            v-if="item.id"
                            type="button"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                            title="Mouvements de la carte"
                            @click="router.visit(`/monetique/cartes/${item.id}/mouvements`)"
                        >
                            <Eye class="h-4 w-4" />
                        </button>
                        <button type="button" class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900" title="Éditer">
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button type="button" class="inline-flex items-center justify-center rounded-md p-2 text-rose-600 hover:bg-rose-50 hover:text-rose-700" title="Supprimer">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
