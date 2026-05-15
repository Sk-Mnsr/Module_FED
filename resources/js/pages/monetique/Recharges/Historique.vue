<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCardNumberDisplay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Banknote, CreditCard, ExternalLink, FileText, History, Plus, RotateCcw } from 'lucide-vue-next';
import { computed, ref, watch, onMounted } from 'vue';
import EncaissementBordereauDialog, {
    type BordereauEntite,
    type EncaissementBordereauPayload,
} from '@/components/monetique/EncaissementBordereauDialog.vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Recharges', href: '/monetique/recharges/historique' },
    { title: 'Historique', href: '/monetique/recharges/historique' },
];

type Row = {
    id: number;
    numero_carte: string;
    montant: number;
    titulaire_carte?: string | null;
    email_titulaire?: string | null;
    honoraire_chargement?: number;
    montant_total_affichage: number;
    carte_interne?: boolean;
    payment_status: string;
    paiement: string;
    demandeur: string;
    caissier: string;
    campagne?: string | null;
    created_at: string;
    confirmed_at: string | null;
    encaissement_code?: string | null;
    bordereau_caisse_url?: string | null;
    bordereau_cc_payload?: EncaissementBordereauPayload;
};

type Paginated = {
    data: Row[];
    current_page: number;
    per_page: number;
    total: number;
};

const props = withDefaults(
    defineProps<{
        recharges?: Paginated;
        filters?: { q: string; statut: string };
    }>(),
    {
        recharges: () => ({ data: [], current_page: 1, per_page: 15, total: 0 }),
        filters: () => ({ q: '', statut: '' }),
    },
);

const qLocal = ref(props.filters.q ?? '');
const statutLocal = ref(props.filters.statut ?? '');

const listQuery = () => ({
    q: qLocal.value.trim() || undefined,
    statut: statutLocal.value || undefined,
    per_page: props.recharges.per_page,
});

const applyFilters = () => {
    router.get('/monetique/recharges/historique', { ...listQuery(), page: 1 }, { preserveState: true, replace: true });
};

const onPageChange = (page: number) => {
    router.get('/monetique/recharges/historique', { ...listQuery(), page }, { preserveState: true, replace: true });
};

const onItemsPerPageChange = (perPage: number) => {
    router.get('/monetique/recharges/historique', { ...listQuery(), per_page: perPage, page: 1 }, {
        preserveState: true,
        replace: true,
    });
};

const reload = () => {
    router.reload({ only: ['recharges', 'filters'] });
};

const formatCfa = (n: number) => `${n.toLocaleString('fr-FR')} F CFA`;

const columns = [
    { key: 'numero_carte', title: 'Carte' },
    { key: 'titulaire_carte', title: 'Titulaire' },
    { key: 'montant', title: 'Montants' },
    { key: 'paiement', title: 'Paiement' },
    { key: 'demandeur', title: 'Demandeur' },
    { key: 'caissier', title: 'Caissier' },
    { key: 'campagne', title: 'Campagne' },
    { key: 'created_at', title: 'Créée le' },
    { key: 'confirmed_at', title: 'Confirmée le' },
    { key: 'bordereaux', title: 'Bordereaux' },
];

const rows = computed(() => props.recharges?.data ?? []);

const statutOptions = [
    { value: '', label: 'Tous les statuts' },
    { value: 'en_attente', label: 'En attente caisse' },
    { value: 'encaisse', label: 'Encaissé' },
];

const page = usePage<{
    auth?: { canInitiateCoficarteRecharge?: boolean };
    flash?: { bordereau_cc?: EncaissementBordereauPayload | null };
}>();

const bordereauCcOpen = ref(false);
const bordereauCcPayload = ref<EncaissementBordereauPayload | null>(null);

const canInitiateCoficarteRecharge = computed(() => page.props.auth?.canInitiateCoficarteRecharge === true);
const goNouveau = () => router.visit('/monetique/recharges/nouveau');

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

function ouvrirBordereauCcDepuisLigne(payload: EncaissementBordereauPayload | undefined) {
    if (!payload || (payload.kind !== 'vente' && payload.kind !== 'recharge')) return;
    bordereauCcPayload.value = payload;
    bordereauCcOpen.value = true;
}

watch(
    () => props.filters,
    (f) => {
        qLocal.value = f?.q ?? '';
        statutLocal.value = f?.statut ?? '';
    },
    { deep: true },
);
</script>

<template>
    <Head title="Monétique — Recharges — Historique" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-[1200px] mx-auto w-full">
            <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-violet-100 text-violet-700 rounded-xl">
                        <History class="h-6 w-6" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Historique des recharges</h1>
                        <p class="text-sm text-gray-500 mt-0.5">
                            {{ recharges.total }} enregistrement(s) dans votre périmètre
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" class="bg-white" @click="reload">
                        <RotateCcw class="h-4 w-4 sm:mr-2" />
                        <span class="hidden sm:inline">Actualiser</span>
                    </Button>
                    <Button
                        v-if="canInitiateCoficarteRecharge"
                        class="bg-violet-600 hover:bg-violet-700"
                        @click="goNouveau"
                    >
                        <Plus class="h-4 w-4 sm:mr-2" />
                        Nouvelle recharge
                    </Button>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
                <p class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Filtres</p>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                    <div class="md:col-span-5 space-y-2">
                        <Label for="rq" class="text-xs font-medium text-gray-600">Recherche</Label>
                        <Input
                            id="rq"
                            v-model="qLocal"
                            placeholder="N° carte, nom demandeur ou caissier…"
                            class="border-gray-300"
                            @keydown.enter.prevent="applyFilters"
                        />
                    </div>
                    <div class="md:col-span-4 space-y-2">
                        <Label for="rst" class="text-xs font-medium text-gray-600">Statut paiement</Label>
                        <select
                            id="rst"
                            v-model="statutLocal"
                            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900"
                        >
                            <option v-for="o in statutOptions" :key="o.value || 'all'" :value="o.value">
                                {{ o.label }}
                            </option>
                        </select>
                    </div>
                    <div class="md:col-span-3 flex gap-2">
                        <Button type="button" class="w-full bg-violet-600 hover:bg-violet-700" @click="applyFilters">
                            Filtrer
                        </Button>
                    </div>
                </div>
            </div>

            <DataTable
                :headers="columns"
                :items="rows"
                :show-select="false"
                :current-page="recharges.current_page"
                :items-per-page="recharges.per_page"
                :total-items="recharges.total"
                :on-page-change="onPageChange"
                :on-items-per-page-change="onItemsPerPageChange"
            >
                <template #item.numero_carte="{ item }">
                    <div class="flex flex-col gap-1 min-w-[140px]">
                        <div class="flex items-center gap-3">
                            <div
                                class="h-8 w-8 rounded-full bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600 shrink-0"
                            >
                                <CreditCard class="h-4 w-4" />
                            </div>
                            <span class="font-mono font-medium text-gray-900 tabular-nums text-sm">
                                {{ formatCardNumberDisplay(item.numero_carte) }}
                            </span>
                        </div>
                        <span
                            v-if="item.carte_interne === false"
                            class="inline-flex w-fit rounded-md border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-900"
                        >
                            Hors stock Coficarte
                        </span>
                    </div>
                </template>

                <template #item.titulaire_carte="{ item }">
                    <div class="text-sm text-gray-800 min-w-[120px]">
                        <span class="font-medium">{{ item.titulaire_carte?.trim() || '—' }}</span>
                        <p v-if="item.email_titulaire?.trim()" class="text-xs text-gray-500 truncate max-w-[200px]">
                            {{ item.email_titulaire.trim() }}
                        </p>
                    </div>
                </template>

                <template #item.montant="{ item }">
                    <div class="text-sm">
                        <span class="inline-flex items-center gap-1.5 font-semibold text-gray-800">
                            <Banknote class="h-3.5 w-3.5 text-emerald-600 shrink-0" />
                            {{ formatCfa(item.montant_total_affichage ?? item.montant) }}
                        </span>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Recharge {{ formatCfa(item.montant) }}
                            <span v-if="(item.honoraire_chargement ?? 0) > 0">
                                · Honoraires {{ formatCfa(item.honoraire_chargement ?? 0) }}
                            </span>
                        </p>
                    </div>
                </template>

                <template #item.paiement="{ item }">
                    <span
                        :class="[
                            'inline-flex rounded-md px-2 py-1 text-xs font-semibold border',
                            item.payment_status === 'en_attente'
                                ? 'bg-amber-50 text-amber-900 border-amber-200'
                                : 'bg-emerald-50 text-emerald-900 border-emerald-200',
                        ]"
                    >
                        {{ item.paiement }}
                    </span>
                </template>

                <template #item.demandeur="{ item }">
                    <span class="text-sm text-gray-700">{{ item.demandeur }}</span>
                </template>

                <template #item.caissier="{ item }">
                    <span class="text-sm text-gray-600">{{ item.caissier !== '—' ? item.caissier : '—' }}</span>
                </template>

                <template #item.campagne="{ item }">
                    <span class="text-sm text-gray-600">{{ item.campagne ?? '—' }}</span>
                </template>

                <template #item.created_at="{ item }">
                    <span class="text-sm text-gray-600 tabular-nums whitespace-nowrap">{{ item.created_at }}</span>
                </template>

                <template #item.confirmed_at="{ item }">
                    <span class="text-sm text-gray-600 tabular-nums whitespace-nowrap">{{
                        item.confirmed_at ?? '—'
                    }}</span>
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
            </DataTable>
        </div>

        <EncaissementBordereauDialog v-model:open="bordereauCcOpen" :payload="bordereauCcPayload" :entite="bordereauEntite" />
    </AppLayout>
</template>
