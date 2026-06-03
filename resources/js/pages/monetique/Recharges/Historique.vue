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
        <div class="flex min-h-0 w-full max-w-none flex-1 flex-col gap-6 px-4 pb-8 pt-3 sm:px-6 lg:px-8">
            <header class="flex shrink-0 flex-col gap-4 border-b border-gray-200 pb-6 dark:border-neutral-800 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex min-w-0 items-start gap-4">
                    <div
                        class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-red-600 text-white shadow-md shadow-red-600/25 dark:shadow-red-900/40"
                    >
                        <History class="h-7 w-7" />
                    </div>
                    <div class="min-w-0 space-y-1">
                        <p class="text-xs font-semibold uppercase tracking-wider text-red-600 dark:text-red-400">Monétique</p>
                        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-neutral-50 sm:text-3xl">
                            Historique des recharges
                        </h1>
                        <p class="text-sm text-gray-500 dark:text-neutral-400">
                            {{ recharges.total }} enregistrement(s) dans votre périmètre
                        </p>
                    </div>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <Button
                        variant="outline"
                        class="border-gray-200 bg-white dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:bg-neutral-800"
                        @click="reload"
                    >
                        <RotateCcw class="h-4 w-4 sm:mr-2" />
                        <span class="hidden sm:inline">Actualiser</span>
                    </Button>
                    <Button
                        v-if="canInitiateCoficarteRecharge"
                        class="bg-red-600 hover:bg-red-700 dark:hover:bg-red-500"
                        @click="goNouveau"
                    >
                        <Plus class="h-4 w-4 sm:mr-2" />
                        Nouvelle recharge
                    </Button>
                </div>
            </header>

            <div class="space-y-5 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900 sm:p-6 lg:p-8">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-neutral-400">Filtres</p>
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
                    <div class="space-y-2 lg:col-span-5 xl:col-span-6">
                        <Label for="rq" class="text-xs font-medium text-gray-600 dark:text-neutral-400">Recherche</Label>
                        <Input
                            id="rq"
                            v-model="qLocal"
                            placeholder="N° carte, nom demandeur ou caissier…"
                            class="h-10 border-gray-300 dark:border-neutral-700 dark:bg-neutral-950"
                            @keydown.enter.prevent="applyFilters"
                        />
                    </div>
                    <div class="space-y-2 lg:col-span-4 xl:col-span-3">
                        <Label for="rst" class="text-xs font-medium text-gray-600 dark:text-neutral-400">Statut paiement</Label>
                        <select
                            id="rst"
                            v-model="statutLocal"
                            class="flex h-10 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100"
                        >
                            <option v-for="o in statutOptions" :key="o.value || 'all'" :value="o.value">
                                {{ o.label }}
                            </option>
                        </select>
                    </div>
                    <div class="flex gap-2 lg:col-span-3 xl:col-span-3">
                        <Button type="button" class="h-10 w-full bg-red-600 hover:bg-red-700 dark:hover:bg-red-500" @click="applyFilters">
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
                    <div class="flex min-w-[9rem] flex-col gap-1">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-red-100 bg-red-50 text-red-600 dark:border-red-900/50 dark:bg-red-950/50 dark:text-red-400"
                            >
                                <CreditCard class="h-4 w-4" />
                            </div>
                            <span class="font-mono text-sm font-medium tabular-nums text-gray-900 dark:text-neutral-100">
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
                        <span class="inline-flex items-center gap-1.5 font-semibold text-red-700 dark:text-red-400">
                            <Banknote class="h-3.5 w-3.5 shrink-0 text-red-600 dark:text-red-500" />
                            {{ formatCfa(item.montant_total_affichage ?? item.montant) }}
                        </span>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-neutral-500">
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
                            'inline-flex rounded-md border px-2 py-1 text-xs font-semibold',
                            item.payment_status === 'en_attente'
                                ? 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-200'
                                : 'border-red-200 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200',
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
                            class="inline-flex items-center justify-center rounded-md p-2 text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
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
                            class="inline-flex items-center justify-center rounded-md p-2 text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
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
