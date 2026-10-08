<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import EncaissementBordereauDialog, {
    type BordereauEntite,
    type EncaissementBordereauPayload,
} from '@/components/monetique/EncaissementBordereauDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCardNumberDisplay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { CreditCard, Download, ExternalLink, Eye, FileText, History, Lock, MoreHorizontal, Pencil, Plus, RotateCcw } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Ventes', href: '/monetique/ventes/historique' },
    { title: 'Historique', href: '/monetique/ventes/historique' },
];

type SaleRow = {
    id?: number;
    card_id?: number | null;
    numero_carte: string;
    prix_vente: number;
    vendeur: string;
    date_vente: string;
    acheteur: string;
    type_acheteur: string;
    paiement: string;
    apporteur: string;
    encaissement_code?: string | null;
    bordereau_caisse_url?: string | null;
    bordereau_cc_payload?: EncaissementBordereauPayload;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    per_page: number;
    total: number;
};

const props = withDefaults(
    defineProps<{
        sales?: Paginated<SaleRow>;
    }>(),
    {
        sales: () => ({
            data: [],
            current_page: 1,
            per_page: 15,
            total: 0,
        }),
    },
);

const vendeur = ref('');
const search = ref('');

const rows = computed(() => props.sales?.data ?? []);

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
                  String(r.prix_vente),
                  r.vendeur,
                  r.date_vente,
                  r.acheteur,
                  r.type_acheteur,
                  r.paiement,
                  r.apporteur,
                  r.encaissement_code ?? '',
              ].some((x) => String(x).toLowerCase().includes(q));
        return byVendeur && bySearch;
    });
});

const columns = [
    { key: 'numero_carte', title: 'N° carte' },
    { key: 'prix_vente', title: 'Prix' },
    { key: 'vendeur', title: 'Vendeur' },
    { key: 'date_vente', title: 'Date' },
    { key: 'acheteur', title: 'Acheteur' },
    { key: 'type_acheteur', title: 'Type' },
    { key: 'apporteur', title: 'Apporteur' },
    { key: 'paiement', title: 'Paiement' },
    { key: 'actions', title: '' },
];

function paiementCourt(label: string): string {
    const v = label.toLowerCase();
    if (v.includes('attente')) return 'Attente';
    if (v.includes('rejet')) return 'Rejetée';
    return 'Encaissé';
}

const page = usePage<{
    auth?: { canInitiateCoficarteVente?: boolean };
    flash?: { bordereau_cc?: EncaissementBordereauPayload | null };
}>();

const bordereauCcOpen = ref(false);
const bordereauCcPayload = ref<EncaissementBordereauPayload | null>(null);

const bordereauEntite: BordereauEntite = {
    raison_sociale: 'Cofina',
    sous_titre: 'Compagnie Financière Africaine',
    ligne_adresse: 'Cofina Sénégal',
    telephones: '(+221) 33 879 90 90',
    email: 'service.client@cac.cofinacorps.com',
};

function syncBordereauCcFromFlash() {
    const b = page.props.flash?.bordereau_cc;
    if (b && (b.kind === 'vente' || b.kind === 'recharge')) {
        bordereauCcPayload.value = b;
        bordereauCcOpen.value = true;
    }
}

onMounted(syncBordereauCcFromFlash);
watch(() => page.props.flash?.bordereau_cc, syncBordereauCcFromFlash);

const canInitiateCoficarteVente = computed(() => page.props.auth?.canInitiateCoficarteVente === true);

const formatCfa = (n: number) => `${n.toLocaleString('fr-FR')} F CFA`;

const goToNouveau = () => router.visit('/monetique/ventes/nouveau');
const reload = () => router.reload({ only: ['sales'] });

function ouvrirBordereauCcDepuisLigne(payload: EncaissementBordereauPayload | undefined) {
    if (!payload || (payload.kind !== 'vente' && payload.kind !== 'recharge')) return;
    bordereauCcPayload.value = payload;
    bordereauCcOpen.value = true;
}

function ouvrirBordereauCaisse(url: string | null | undefined) {
    if (!url) return;
    window.open(url, '_blank', 'noopener');
}
</script>

<template>
    <Head title="Monétique - Ventes - Historique" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex w-full flex-col gap-5 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-violet-100 p-2.5 text-violet-700">
                        <History class="h-5 w-5" />
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Liste des ventes</h1>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" class="bg-white">
                        <Download class="mr-2 h-4 w-4" />
                        Exporter
                    </Button>
                    <Button
                        v-if="canInitiateCoficarteVente"
                        class="bg-violet-600 hover:bg-violet-700"
                        @click="goToNouveau"
                    >
                        <Plus class="mr-2 h-4 w-4" />
                        Ajouter
                    </Button>
                    <Button class="bg-violet-600 hover:bg-violet-700" @click="reload">
                        <RotateCcw class="mr-2 h-4 w-4" />
                        Recharger
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
                <div class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="space-y-1.5">
                        <Label for="vendeur" class="text-xs font-medium text-gray-600">Vendeur</Label>
                        <select
                            id="vendeur"
                            v-model="vendeur"
                            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-900"
                        >
                            <option value="">— Tous —</option>
                            <option v-for="v in vendeurs" :key="v" :value="v">{{ v }}</option>
                        </select>
                    </div>
                    <div class="space-y-1.5 xl:col-span-2">
                        <Label for="search" class="text-xs font-medium text-gray-600">Recherche</Label>
                        <Input
                            id="search"
                            v-model="search"
                            placeholder="N° carte, acheteur, vendeur…"
                            class="border-gray-300"
                        />
                    </div>
                </div>
            </div>

            <DataTable
                :headers="columns"
                :items="filteredRows"
                :show-select="false"
                :current-page="props.sales.current_page"
                :items-per-page="props.sales.per_page"
                :total-items="props.sales.total"
            >
                <template #item.numero_carte="{ item }">
                    <div class="flex items-center gap-2 whitespace-nowrap">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-rose-100 bg-rose-50 text-rose-600">
                            <CreditCard class="h-4 w-4" />
                        </div>
                        <span class="font-mono font-medium tabular-nums text-gray-900">
                            {{ formatCardNumberDisplay(item.numero_carte) }}
                        </span>
                    </div>
                </template>

                <template #item.prix_vente="{ item }">
                    <span class="whitespace-nowrap text-sm text-gray-600">{{ formatCfa(item.prix_vente) }}</span>
                </template>

                <template #item.vendeur="{ item }">
                    <span class="whitespace-nowrap text-sm text-gray-800">{{ item.vendeur }}</span>
                </template>

                <template #item.date_vente="{ item }">
                    <span class="whitespace-nowrap text-sm text-gray-700">{{ item.date_vente }}</span>
                </template>

                <template #item.acheteur="{ item }">
                    <span class="whitespace-nowrap text-sm text-gray-800">{{ item.acheteur }}</span>
                </template>

                <template #item.type_acheteur="{ item }">
                    <span class="whitespace-nowrap text-sm text-gray-700">{{ item.type_acheteur }}</span>
                </template>

                <template #item.apporteur="{ item }">
                    <span class="whitespace-nowrap text-sm text-gray-700">{{ item.apporteur || '—' }}</span>
                </template>

                <template #item.paiement="{ item }">
                    <span
                        class="inline-flex whitespace-nowrap rounded-md border px-2 py-0.5 text-xs font-semibold"
                        :class="item.paiement.toLowerCase().includes('attente')
                            ? 'border-amber-200 bg-amber-50 text-amber-900'
                            : item.paiement.toLowerCase().includes('rejet')
                                ? 'border-rose-200 bg-rose-50 text-rose-800'
                                : 'border-emerald-200 bg-emerald-50 text-emerald-900'"
                        :title="item.paiement"
                    >
                        {{ paiementCourt(item.paiement) }}
                    </span>
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
                            <DropdownMenuContent align="end" class="w-52">
                                <DropdownMenuItem class="text-sm" @click="ouvrirBordereauCcDepuisLigne(item.bordereau_cc_payload)">
                                    <FileText class="size-4" />
                                    Bordereau commercial
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="item.bordereau_caisse_url"
                                    class="text-sm"
                                    @click="ouvrirBordereauCaisse(item.bordereau_caisse_url)"
                                >
                                    <ExternalLink class="size-4" />
                                    Bordereau caisse
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="item.card_id"
                                    class="text-sm"
                                    @click="router.visit(`/monetique/cartes/${item.card_id}/mouvements`)"
                                >
                                    <Eye class="size-4" />
                                    Mouvements
                                </DropdownMenuItem>
                                <DropdownMenuItem class="text-sm">
                                    <Pencil class="size-4" />
                                    Modifier
                                </DropdownMenuItem>
                                <DropdownMenuItem class="text-sm">
                                    <Lock class="size-4" />
                                    Verrouiller
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </template>
            </DataTable>
        </div>

        <EncaissementBordereauDialog v-model:open="bordereauCcOpen" :payload="bordereauCcPayload" :entite="bordereauEntite" />
    </AppLayout>
</template>
