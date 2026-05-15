<script setup lang="ts">
import EncaissementDetailDialog from '@/components/monetique/EncaissementDetailDialog.vue';
import EncaissementBordereauDialog, {
    type BordereauEntite,
    type EncaissementBordereauPayload,
} from '@/components/monetique/EncaissementBordereauDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCardNumberDisplay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Banknote, ExternalLink, FileText, History, KeyRound, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Caisse', href: '/monetique/encaissements' },
];

type HistRow = {
    encaissement_code: string | null;
    numero_carte: string;
    montant: number;
    libelle: string;
    agence: string;
    date: string;
    bordereau_caisse_url?: string | null;
    bordereau_cc_payload?: EncaissementBordereauPayload;
};

const props = withDefaults(
    defineProps<{
        onglet?: 'encaissement' | 'historique';
        code_recherche?: string;
        code_introuvable?: boolean;
        operation_par_code?: EncaissementBordereauPayload | null;
        historique_ventes?: HistRow[];
        historique_recharges?: HistRow[];
    }>(),
    {
        onglet: 'encaissement',
        code_recherche: '',
        code_introuvable: false,
        operation_par_code: null,
        historique_ventes: () => [],
        historique_recharges: () => [],
    },
);

const page = usePage<{
    flash?: { success?: string | null; error?: string | null; warning?: string | null };
}>();

const codeLocal = ref(props.code_recherche ?? '');

watch(
    () => props.code_recherche,
    (c) => {
        codeLocal.value = c ?? '';
    },
);

const formatCfa = (n: number) => `${n.toLocaleString('fr-FR')} F CFA`;

const isEncaissementTab = computed(() => props.onglet === 'encaissement');
const isHistoriqueTab = computed(() => props.onglet === 'historique');

function goEncaissementOnglet() {
    router.get('/monetique/encaissements', { onglet: 'encaissement' }, { preserveState: true, replace: true });
}

function goHistoriqueOnglet() {
    router.get('/monetique/encaissements', { onglet: 'historique' }, { preserveState: true, replace: true });
}

function rechercherParCode() {
    router.get(
        '/monetique/encaissements',
        {
            onglet: 'encaissement',
            code: codeLocal.value.trim() || undefined,
        },
        { preserveState: true, replace: true },
    );
}

const detailOpen = ref(false);
const detailPayload = ref<EncaissementBordereauPayload | null>(null);

watch(
    () => ({
        op: props.operation_par_code,
        bad: props.code_introuvable,
        q: props.code_recherche,
    }),
    ({ op, bad, q }) => {
        if (op) {
            detailPayload.value = op;
            detailOpen.value = true;
            return;
        }
        if (bad && (q?.trim() ?? '') !== '') {
            detailOpen.value = false;
            detailPayload.value = null;
        }
    },
    { immediate: true },
);

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const bordereauHistOpen = ref(false);
const bordereauHistPayload = ref<EncaissementBordereauPayload | null>(null);

const bordereauEntite: BordereauEntite = {
    raison_sociale: 'Cofina',
    sous_titre: 'Compagnie Financière Africaine',
    ligne_adresse: 'Cofina Sénégal',
    telephones: '(+221) 33 879 90 90',
    email: 'service.client@cac.cofinacorps.com',
};

function ouvrirBordereauHist(payload: EncaissementBordereauPayload | undefined) {
    if (!payload || (payload.kind !== 'vente' && payload.kind !== 'recharge')) return;
    bordereauHistPayload.value = payload;
    bordereauHistOpen.value = true;
}
</script>

<template>
    <Head title="Monétique — Caisse Coficarte" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative min-h-[calc(100vh-7rem)] rounded-2xl overflow-hidden border border-white/10 shadow-xl">
            <!-- Fond visuel caisse -->
            <div class="absolute inset-0 z-0 pointer-events-none" aria-hidden="true">
                <img
                    src="/cofina_caisse.jpg"
                    alt=""
                    class="absolute inset-0 w-full h-full object-cover object-center scale-105"
                />
                <div class="absolute inset-0 bg-gradient-to-b from-slate-900/75 via-slate-900/65 to-slate-950/85" />
            </div>

            <div class="relative z-10 flex flex-col min-h-[calc(100vh-7rem)]">
                <div class="p-4 sm:p-6 pb-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex p-1 rounded-xl bg-white/10 backdrop-blur-md border border-white/20 w-full sm:w-auto">
                        <button
                            type="button"
                            class="flex-1 sm:flex-none rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors"
                            :class="
                                isEncaissementTab
                                    ? 'bg-white text-slate-900 shadow'
                                    : 'text-white/90 hover:bg-white/10'
                            "
                            @click="goEncaissementOnglet"
                        >
                            Encaissement
                        </button>
                        <button
                            type="button"
                            class="flex-1 sm:flex-none rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors flex items-center justify-center gap-2"
                            :class="
                                isHistoriqueTab ? 'bg-white text-slate-900 shadow' : 'text-white/90 hover:bg-white/10'
                            "
                            @click="goHistoriqueOnglet"
                        >
                            <History class="h-4 w-4 shrink-0" />
                            Historique
                        </button>
                    </div>
                </div>

                <div v-if="flashSuccess || flashError" class="px-4 sm:px-6 pt-4 space-y-2">
                    <div
                        v-if="flashSuccess"
                        class="rounded-lg border border-emerald-200/80 bg-emerald-50/95 px-4 py-3 text-sm text-emerald-950 shadow-sm backdrop-blur-sm"
                    >
                        {{ flashSuccess }}
                    </div>
                    <div
                        v-if="flashError"
                        class="rounded-lg border border-red-200/80 bg-red-50/95 px-4 py-3 text-sm text-red-950 shadow-sm backdrop-blur-sm"
                    >
                        {{ flashError }}
                    </div>
                </div>

                <!-- Onglet saisie code -->
                <div
                    v-if="isEncaissementTab"
                    class="flex-1 flex flex-col items-center justify-center px-4 sm:px-8 py-12 sm:py-20"
                >
                    <div
                        class="w-full max-w-md rounded-2xl border border-white/25 bg-white/90 backdrop-blur-xl shadow-2xl px-6 py-8 sm:px-10 sm:py-10 space-y-6"
                    >
                        <div class="text-center space-y-2">
                            <div
                                class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-red-600 text-white shadow-lg"
                            >
                                <KeyRound class="h-7 w-7" />
                            </div>
                            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Code d’encaissement</h1>
                            <p class="text-sm text-slate-600 leading-relaxed">
                                Saisissez le code du bordereau pour ouvrir l’opération, puis joignez le bordereau de caisse afin de valider l’encaissement.
                            </p>
                        </div>

                        <div class="space-y-3">
                            <Label for="code-enc-caisse" class="text-xs font-semibold text-slate-600 uppercase tracking-wide">
                                Code
                            </Label>
                            <Input
                                id="code-enc-caisse"
                                v-model="codeLocal"
                                placeholder="ex. V-XXXXXXXX ou R-XXXXXXXX"
                                class="font-mono uppercase text-center text-lg h-12 border-slate-200 bg-white"
                                autocomplete="off"
                                @keydown.enter.prevent="rechercherParCode"
                            />
                            <Button
                                type="button"
                                class="h-11 w-full bg-red-600 text-base font-semibold hover:bg-red-700"
                                @click="rechercherParCode"
                            >
                                <Search class="h-4 w-4 sm:mr-2" />
                                Rechercher l’opération
                            </Button>
                        </div>

                        <div
                            v-if="code_introuvable && (code_recherche?.trim() ?? '') !== ''"
                            class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-950"
                        >
                            Aucune opération en attente ne correspond à ce code dans votre périmètre.
                        </div>
                    </div>
                </div>

                <!-- Onglet historique -->
                <div v-else class="flex-1 px-4 sm:px-8 py-8 space-y-10 overflow-y-auto">
                    <section class="rounded-xl border border-white/20 bg-white/90 backdrop-blur-md shadow-lg overflow-hidden">
                        <div class="px-4 py-3 border-b border-slate-200/80 bg-white/80 flex items-center gap-2">
                            <span
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-rose-100 text-rose-800"
                            >
                                <Banknote class="h-4 w-4" />
                            </span>
                            <h2 class="font-semibold text-slate-900">Ventes encaissées (récent)</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-slate-50/90 text-slate-600 text-xs uppercase tracking-wide">
                                    <tr>
                                        <th class="px-4 py-3 font-medium">Code</th>
                                        <th class="px-4 py-3 font-medium">Carte</th>
                                        <th class="px-4 py-3 font-medium">Client</th>
                                        <th class="px-4 py-3 font-medium">Agence</th>
                                        <th class="px-4 py-3 font-medium text-right">Montant</th>
                                        <th class="px-4 py-3 font-medium">Date</th>
                                        <th class="px-4 py-3 font-medium text-right">Bordereaux</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(row, idx) in historique_ventes" :key="'v-' + idx" class="bg-white/70">
                                        <td class="px-4 py-2.5 font-mono text-xs">{{ row.encaissement_code ?? '—' }}</td>
                                        <td class="px-4 py-2.5 font-mono tabular-nums">{{
                                            formatCardNumberDisplay(row.numero_carte)
                                        }}</td>
                                        <td class="px-4 py-2.5">{{ row.libelle }}</td>
                                        <td class="px-4 py-2.5 text-slate-600">{{ row.agence }}</td>
                                        <td class="px-4 py-2.5 text-right font-semibold tabular-nums">{{
                                            formatCfa(row.montant)
                                        }}</td>
                                        <td class="px-4 py-2.5 tabular-nums text-slate-600 whitespace-nowrap">{{
                                            row.date
                                        }}</td>
                                        <td class="px-4 py-2.5">
                                            <div class="flex items-center justify-end gap-1">
                                                <button
                                                    type="button"
                                                    class="inline-flex rounded-md p-1.5 text-violet-700 hover:bg-violet-50"
                                                    title="Bordereau commercial"
                                                    @click="ouvrirBordereauHist(row.bordereau_cc_payload)"
                                                >
                                                    <FileText class="h-4 w-4" />
                                                </button>
                                                <a
                                                    v-if="row.bordereau_caisse_url"
                                                    :href="row.bordereau_caisse_url"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="inline-flex rounded-md p-1.5 text-red-700 hover:bg-red-50"
                                                    title="Bordereau caisse"
                                                >
                                                    <ExternalLink class="h-4 w-4" />
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!historique_ventes.length">
                                        <td colspan="8" class="px-4 py-8 text-center text-slate-500">
                                            Aucune vente encaissée enregistrée dans votre périmètre.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="rounded-xl border border-white/20 bg-white/90 backdrop-blur-md shadow-lg overflow-hidden">
                        <div class="px-4 py-3 border-b border-slate-200/80 bg-white/80 flex items-center gap-2">
                            <span
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-teal-100 text-teal-800"
                            >
                                <Banknote class="h-4 w-4" />
                            </span>
                            <h2 class="font-semibold text-slate-900">Recharges encaissées (récent)</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-slate-50/90 text-slate-600 text-xs uppercase tracking-wide">
                                    <tr>
                                        <th class="px-4 py-3 font-medium">Code</th>
                                        <th class="px-4 py-3 font-medium">Carte</th>
                                        <th class="px-4 py-3 font-medium">Demandeur</th>
                                        <th class="px-4 py-3 font-medium">Agence</th>
                                        <th class="px-4 py-3 font-medium text-right">Montant</th>
                                        <th class="px-4 py-3 font-medium">Date</th>
                                        <th class="px-4 py-3 font-medium text-right">Bordereaux</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(row, idx) in historique_recharges" :key="'r-' + idx" class="bg-white/70">
                                        <td class="px-4 py-2.5 font-mono text-xs">{{ row.encaissement_code ?? '—' }}</td>
                                        <td class="px-4 py-2.5 font-mono tabular-nums">{{
                                            formatCardNumberDisplay(row.numero_carte)
                                        }}</td>
                                        <td class="px-4 py-2.5">{{ row.libelle }}</td>
                                        <td class="px-4 py-2.5 text-slate-600">{{ row.agence }}</td>
                                        <td class="px-4 py-2.5 text-right font-semibold tabular-nums">{{
                                            formatCfa(row.montant)
                                        }}</td>
                                        <td class="px-4 py-2.5 tabular-nums text-slate-600 whitespace-nowrap">{{
                                            row.date
                                        }}</td>
                                        <td class="px-4 py-2.5">
                                            <div class="flex items-center justify-end gap-1">
                                                <button
                                                    type="button"
                                                    class="inline-flex rounded-md p-1.5 text-violet-700 hover:bg-violet-50"
                                                    title="Bordereau commercial"
                                                    @click="ouvrirBordereauHist(row.bordereau_cc_payload)"
                                                >
                                                    <FileText class="h-4 w-4" />
                                                </button>
                                                <a
                                                    v-if="row.bordereau_caisse_url"
                                                    :href="row.bordereau_caisse_url"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="inline-flex rounded-md p-1.5 text-red-700 hover:bg-red-50"
                                                    title="Bordereau caisse"
                                                >
                                                    <ExternalLink class="h-4 w-4" />
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!historique_recharges.length">
                                        <td colspan="8" class="px-4 py-8 text-center text-slate-500">
                                            Aucune recharge encaissée enregistrée dans votre périmètre.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <EncaissementDetailDialog v-model:open="detailOpen" :payload="detailPayload" :caisse-flow="true" />
        <EncaissementBordereauDialog v-model:open="bordereauHistOpen" :payload="bordereauHistPayload" :entite="bordereauEntite" />
    </AppLayout>
</template>
