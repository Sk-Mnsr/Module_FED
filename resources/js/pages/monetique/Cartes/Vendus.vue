<script setup lang="ts">
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import DataTable from '@/components/DataTable.vue';
import ExpirationBar from '@/components/ExpirationBar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatCardNumberDisplay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { CreditCard, Download, Plus, RotateCcw, Eye, Pencil, Trash2, ShoppingBag } from 'lucide-vue-next';

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

const fieldClass =
    'h-10 border-slate-300 bg-white text-slate-900 shadow-sm placeholder:text-slate-400 focus-visible:border-primary focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';

const selectClass =
    'flex h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';

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
            : [r.numero_carte, r.date_livraison, String(r.prix_vente), r.vendeur, r.expiration].some((x) =>
                  x.toLowerCase().includes(q),
              );
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

const resetFilters = () => {
    vendeur.value = '';
    search.value = '';
};
</script>

<template>
    <Head title="Monétique - Cartes - Vendus" />

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
                                <ShoppingBag class="size-5" />
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-primary">
                                    Monétique · Cartes
                                </p>
                                <h1 class="text-xl font-semibold tracking-tight text-foreground">
                                    Cartes vendues
                                </h1>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    <span class="font-semibold text-primary">{{ props.cards.total }}</span>
                                    carte{{ props.cards.total > 1 ? 's' : '' }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Button variant="outline" class="border-slate-300">
                                <Download class="mr-2 size-4" />
                                Export
                            </Button>
                            <Button
                                v-if="canResponsableMonetique"
                                @click="router.visit('/monetique/cartes/ajouter')"
                            >
                                <Plus class="mr-2 size-4" />
                                Ajouter
                            </Button>
                            <Button variant="outline" class="border-slate-300" @click="reload">
                                <RotateCcw class="mr-2 size-4" />
                                Recharger
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 border-b border-border/80 px-4 py-4 sm:px-5">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Filtres
                    </p>
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div>
                            <label for="vendeur" class="mb-1.5 block text-sm font-medium text-foreground">
                                Vendeur
                            </label>
                            <select id="vendeur" v-model="vendeur" :class="selectClass">
                                <option value="">Tous</option>
                                <option v-for="v in vendeurs" :key="v" :value="v">{{ v }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="search" class="mb-1.5 block text-sm font-medium text-foreground">
                                Recherche
                            </label>
                            <Input
                                id="search"
                                v-model="search"
                                placeholder="N° carte, vendeur, prix…"
                                :class="fieldClass"
                            />
                        </div>
                    </div>
                    <div v-if="vendeur || search" class="flex">
                        <Button type="button" variant="outline" class="border-slate-300" @click="resetFilters">
                            <RotateCcw class="mr-2 size-4" />
                            Réinitialiser
                        </Button>
                    </div>
                </div>

                <div class="p-4 sm:p-5">
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
                                <div
                                    class="flex size-8 items-center justify-center rounded-full border border-primary/20 bg-primary/10 text-primary"
                                >
                                    <CreditCard class="size-4" />
                                </div>
                                <span class="font-mono font-medium tabular-nums text-foreground">
                                    {{ formatCardNumberDisplay(item.numero_carte) }}
                                </span>
                            </div>
                        </template>

                        <template #item.prix_vente="{ item }">
                            <span class="font-medium tabular-nums text-foreground">
                                {{ formatCfa(item.prix_vente) }}
                            </span>
                        </template>

                        <template #item.expiration="{ item }">
                            <ExpirationBar
                                :expiration="item.expiration"
                                :date-expiration="item.date_expiration"
                            />
                        </template>

                        <template #item.actions="{ item }">
                            <div class="flex items-center justify-end gap-1">
                                <button
                                    v-if="item.id"
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-md p-2 text-slate-600 transition-colors hover:bg-primary/5 hover:text-primary"
                                    title="Mouvements de la carte"
                                    @click="router.visit(`/monetique/cartes/${item.id}/mouvements`)"
                                >
                                    <Eye class="size-4" />
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-md p-2 text-slate-600 transition-colors hover:bg-primary/5 hover:text-primary"
                                    title="Éditer"
                                >
                                    <Pencil class="size-4" />
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-md p-2 text-red-600 transition-colors hover:bg-red-50 hover:text-red-700"
                                    title="Supprimer"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </template>
                    </DataTable>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
