<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import EncaissementBordereauDialog, {
    type BordereauEntite,
    type EncaissementBordereauPayload,
} from '@/components/monetique/EncaissementBordereauDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCardNumberDisplay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { CreditCard, Download, ExternalLink, Eye, FileText, History, Lock, Pencil, Plus, RotateCcw } from 'lucide-vue-next';

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
    { key: 'numero_carte', title: 'Numéro de carte' },
    { key: 'prix_vente', title: 'Prix de vente' },
    { key: 'vendeur', title: 'Vendeur' },
    { key: 'date_vente', title: 'Date de la vente' },
    { key: 'acheteur', title: 'Acheteur' },
    { key: 'type_acheteur', title: "Type de l'acheteur" },
    { key: 'apporteur', title: 'Apporteur' },
    { key: 'paiement', title: 'Paiement' },
    { key: 'bordereaux', title: 'Bordereaux' },
    { key: 'actions', title: 'Actions' },
];

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
</script>

<template>
    <Head title="Monétique - Ventes - Historique" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center gap-3">
                <div class="p-3 bg-violet-100 text-violet-700 rounded-xl">
                    <History class="h-6 w-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Liste des ventes</h1>
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
                            placeholder="Rechercher une vente"
                            class="border-gray-300"
                        />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <Button variant="outline" class="bg-white">
                        <Download class="h-4 w-4 mr-2" />
                        Exporter
                    </Button>
                    <Button
                        v-if="canInitiateCoficarteVente"
                        class="bg-violet-600 hover:bg-violet-700"
                        @click="goToNouveau"
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

            <DataTable
                :headers="columns"
                :items="filteredRows"
                :show-select="false"
                :current-page="props.sales.current_page"
                :items-per-page="props.sales.per_page"
                :total-items="props.sales.total"
            >
                <template #item.numero_carte="{ item }">
                    <div class="flex items-center gap-3">
                        <div
                            class="h-8 w-8 rounded-full bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600"
                        >
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

                <template #item.acheteur="{ item }">
                    <span class="text-sm text-gray-700">{{ item.acheteur }}</span>
                </template>

                <template #item.apporteur="{ item }">
                    <span class="text-sm text-gray-600">{{ item.apporteur }}</span>
                </template>

                <template #item.paiement="{ item }">
                    <span
                        :class="[
                            'inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold border',
                            item.paiement.includes('attente')
                                ? 'bg-amber-50 text-amber-900 border-amber-200'
                                : 'bg-emerald-50 text-emerald-900 border-emerald-200',
                        ]"
                    >
                        {{ item.paiement }}
                    </span>
                </template>

                <template #item.bordereaux="{ item }">
                    <div class="flex items-center justify-end gap-1 flex-wrap">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-md p-2 text-violet-700 hover:bg-violet-50"
                            title="Bordereau commercial (aperçu / impression)"
                            @click="ouvrirBordereauCcDepuisLigne(item.bordereau_cc_payload)"
                        >
                            <FileText class="h-4 w-4" />
                        </button>
                        <a
                            v-if="item.bordereau_caisse_url"
                            :href="item.bordereau_caisse_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center justify-center rounded-md p-2 text-emerald-700 hover:bg-emerald-50"
                            title="Bordereau caisse (pièce jointe)"
                        >
                            <ExternalLink class="h-4 w-4" />
                        </a>
                        <span
                            v-else
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-300 cursor-not-allowed"
                            title="Pas encore de bordereau caisse (ex. en attente caisse)"
                        >
                            <ExternalLink class="h-4 w-4" />
                        </span>
                    </div>
                </template>

                <template #item.actions="{ item }">
                    <div class="flex items-center justify-end gap-1">
                        <button
                            v-if="item.card_id"
                            type="button"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                            title="Mouvements de la carte"
                            @click="router.visit(`/monetique/cartes/${item.card_id}/mouvements`)"
                        >
                            <Eye class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                            title="Modifier"
                        >
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                            title="Verrouiller"
                        >
                            <Lock class="h-4 w-4" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <EncaissementBordereauDialog v-model:open="bordereauCcOpen" :payload="bordereauCcPayload" :entite="bordereauEntite" />
    </AppLayout>
</template>
