<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    ArrowRight,
    Calculator,
    CheckCircle2,
    CircleAlert,
    FileText,
    RefreshCw,
    Search,
    Scale,
} from 'lucide-vue-next';

type ProduitOption = {
    id: number;
    code: string;
    libelle: string;
    mode_frais: string;
    frais_fixe: string | number | null;
    initiateur: string | null;
    statut: string;
    base_calcul: string | null;
    devise: string;
};

type ProduitDetail = ProduitOption & {
    taux_frais: string | number | null;
    taux_taf: number;
    compte_produit: string | null;
    compte_taf: string | null;
    compte_client_mask: string | null;
    libelle_ecriture_produit: string | null;
    libelle_ecriture_taf: string | null;
    needs_montant_demande: boolean;
    needs_frais_saisi: boolean;
    tranches: Array<{
        libelle: string | null;
        montant_min: string | number;
        montant_max: string | number | null;
        frais_fixe: string | number | null;
        taux: string | number | null;
    }>;
};

type Preview = {
    frais_ht: number;
    taux_taf: number;
    montant_taf: number;
    total_client: number;
    devise: string;
    mode_frais: string;
    message: string | null;
    equilibre: boolean;
    total_debit: number;
    total_credit: number;
    tranche: Record<string, unknown> | null;
    lignes: Array<{
        sens: string;
        compte: string;
        libelle_ecriture: string | null;
        nature_compte: string | null;
        type_montant: string | null;
        montant: number;
    }>;
};

const props = defineProps<{
    produits: ProduitOption[];
    produit: ProduitDetail | null;
}>();

const breadcrumbs = [
    { title: 'POD', href: '/pod/produits' },
    { title: 'Nouvelle opération', href: '/pod/operations/create' },
];

const fieldClass =
    'h-11 border-slate-300 bg-white text-slate-900 shadow-sm placeholder:text-slate-400 focus-visible:border-primary focus-visible:ring-primary/30';

const modeLabels: Record<string, string> = {
    fixe: 'Frais fixe',
    tranche: 'Par tranche',
    taux: 'Au pourcentage',
    mixte: 'Fixe + taux',
    manuel: 'Saisie manuelle',
    gratuit: 'Gratuit',
};

const form = useForm({
    pod_produit_id: (props.produit?.id ?? '') as number | '',
    compte_client: '',
    code_agence: '',
    nom_client: '',
    date_valeur: new Date().toISOString().slice(0, 10),
    montant_demande: '' as number | '',
    frais_saisi: '' as number | '',
    reference: '',
    libelle: props.produit?.libelle_ecriture_produit || props.produit?.libelle || '',
});

const produitSearch = ref('');
const showProductPicker = ref(!props.produit);

const selectedId = computed(() =>
    form.pod_produit_id === '' ? null : Number(form.pod_produit_id),
);

const activeProduit = computed<ProduitDetail | ProduitOption | null>(() => {
    if (props.produit && Number(props.produit.id) === selectedId.value) {
        return props.produit;
    }
    return props.produits.find((p) => p.id === selectedId.value) ?? null;
});

const filteredProduits = computed(() => {
    const q = produitSearch.value.trim().toLowerCase();
    if (!q) return props.produits;
    return props.produits.filter(
        (p) =>
            p.code.toLowerCase().includes(q)
            || p.libelle.toLowerCase().includes(q)
            || (p.initiateur || '').toLowerCase().includes(q),
    );
});

const needsMontant = computed(() => {
    const m = activeProduit.value?.mode_frais;
    return m === 'tranche' || m === 'taux' || m === 'mixte';
});

const needsFraisSaisi = computed(() => activeProduit.value?.mode_frais === 'manuel');

const stepProduitOk = computed(() => selectedId.value !== null);
const stepSaisieOk = computed(() => {
    if (!form.compte_client.trim()) return false;
    if (needsMontant.value && (form.montant_demande === '' || Number(form.montant_demande) < 0)) return false;
    if (needsFraisSaisi.value && (form.frais_saisi === '' || Number(form.frais_saisi) < 0)) return false;
    return true;
});

const selectProduit = (id: number) => {
    form.pod_produit_id = id;
    showProductPicker.value = false;
    form.montant_demande = '';
    form.frais_saisi = '';
    router.get(
        '/pod/operations/create',
        { produit_id: id },
        {
            preserveState: true,
            replace: true,
            only: ['produit', 'produits'],
            onSuccess: () => {
                const p = props.produit;
                if (p && Number(p.id) === id) {
                    form.libelle = p.libelle_ecriture_produit || p.libelle || '';
                }
            },
        },
    );
};

watch(
    () => props.produit,
    (p) => {
        if (p && Number(p.id) === selectedId.value) {
            if (!form.libelle) {
                form.libelle = p.libelle_ecriture_produit || p.libelle || '';
            }
        }
    },
);

const preview = ref<Preview | null>(null);
const previewError = ref<string | null>(null);
const previewLoading = ref(false);
let previewTimer: ReturnType<typeof setTimeout> | null = null;

const xsrfToken = () => {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
};

const runPreview = async () => {
    previewError.value = null;
    if (!selectedId.value || !form.compte_client.trim()) {
        preview.value = null;
        return;
    }
    if (needsFraisSaisi.value && form.frais_saisi === '') {
        preview.value = null;
        previewError.value = 'Saisissez les frais HT pour lancer le calcul.';
        return;
    }
    if (needsMontant.value && form.montant_demande === '') {
        preview.value = null;
        previewError.value = 'Saisissez le montant de la demande pour déterminer la tranche / le taux.';
        return;
    }

    previewLoading.value = true;
    try {
        const res = await fetch('/pod/operations/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                pod_produit_id: selectedId.value,
                compte_client: form.compte_client.trim(),
                montant_demande: form.montant_demande === '' ? null : Number(form.montant_demande),
                frais_saisi: form.frais_saisi === '' ? null : Number(form.frais_saisi),
            }),
        });
        const data = await res.json();
        if (!res.ok) {
            preview.value = null;
            previewError.value = data.error || data.message || 'Calcul impossible';
            return;
        }
        preview.value = data as Preview;
    } catch {
        previewError.value = 'Erreur réseau lors du calcul.';
        preview.value = null;
    } finally {
        previewLoading.value = false;
    }
};

watch(
    () => [form.pod_produit_id, form.compte_client, form.montant_demande, form.frais_saisi, props.produit?.id],
    () => {
        if (previewTimer) clearTimeout(previewTimer);
        previewTimer = setTimeout(runPreview, 300);
    },
    { immediate: true },
);

const fmt = (v: number | string | null | undefined) => {
    if (v === null || v === undefined || v === '') return '—';
    return Number(v).toLocaleString('fr-FR');
};

const canSubmit = computed(() => !!preview.value?.equilibre && !form.processing && stepSaisieOk.value);

const submit = () => {
    form.transform((data) => ({
        ...data,
        pod_produit_id: selectedId.value,
        montant_demande: data.montant_demande === '' ? null : data.montant_demande,
        frais_saisi: data.frais_saisi === '' ? null : data.frais_saisi,
    })).post('/pod/operations');
};
</script>

<template>
    <Head title="Nouvelle opération POD" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-[calc(100vh-4rem)] bg-gradient-to-b from-slate-50 via-white to-white">
            <form class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8" @submit.prevent="submit">
                <!-- En-tête -->
                <div class="mb-6 flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.2em] text-primary uppercase">POD</p>
                        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">Nouvelle opération</h1>
                        <p class="mt-2 max-w-xl text-sm text-slate-600">
                            Le produit pilote les règles. Vous ne saisissez que les informations client et les
                            montants demandés — frais, TAF et écritures se calculent seuls.
                        </p>
                    </div>

                    <div class="flex items-center gap-2 text-xs sm:text-sm">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 font-medium"
                            :class="stepProduitOk ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                        >
                            <CheckCircle2 v-if="stepProduitOk" class="h-3.5 w-3.5" />
                            <span v-else class="flex h-4 w-4 items-center justify-center rounded-full bg-slate-300 text-[10px] text-white">1</span>
                            Produit
                        </span>
                        <ArrowRight class="h-3.5 w-3.5 text-slate-400" />
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 font-medium"
                            :class="stepSaisieOk ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                        >
                            <CheckCircle2 v-if="stepSaisieOk" class="h-3.5 w-3.5" />
                            <span v-else class="flex h-4 w-4 items-center justify-center rounded-full bg-slate-300 text-[10px] text-white">2</span>
                            Saisie
                        </span>
                        <ArrowRight class="h-3.5 w-3.5 text-slate-400" />
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 font-medium"
                            :class="preview?.equilibre ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                        >
                            <CheckCircle2 v-if="preview?.equilibre" class="h-3.5 w-3.5" />
                            <span v-else class="flex h-4 w-4 items-center justify-center rounded-full bg-slate-300 text-[10px] text-white">3</span>
                            Calcul
                        </span>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)]">
                    <!-- Colonne gauche -->
                    <div class="space-y-5">
                        <!-- Produit -->
                        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                                <div class="flex items-center gap-2">
                                    <FileText class="h-4 w-4 text-primary" />
                                    <h2 class="text-sm font-semibold text-slate-900">1. Choisir le produit</h2>
                                </div>
                                <Button
                                    v-if="activeProduit && !showProductPicker"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="showProductPicker = true"
                                >
                                    Changer
                                </Button>
                            </div>

                            <div class="p-5">
                                <div
                                    v-if="activeProduit && !showProductPicker"
                                    class="rounded-xl border border-primary/20 bg-primary/5 p-4"
                                >
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-semibold tracking-wide text-primary uppercase">
                                                Code {{ activeProduit.code }}
                                            </p>
                                            <p class="mt-1 text-lg font-semibold text-slate-900">
                                                {{ activeProduit.libelle }}
                                            </p>
                                            <p class="mt-2 text-sm text-slate-600">
                                                {{ modeLabels[activeProduit.mode_frais] || activeProduit.mode_frais }}
                                                <span v-if="activeProduit.frais_fixe != null">
                                                    · {{ fmt(activeProduit.frais_fixe) }} {{ activeProduit.devise }}
                                                </span>
                                                <span v-if="'taux_taf' in activeProduit">
                                                    · TAF {{ (activeProduit as ProduitDetail).taux_taf }} %
                                                </span>
                                                <span v-if="activeProduit.initiateur">
                                                    · Initiateur {{ activeProduit.initiateur }}
                                                </span>
                                            </p>
                                            <p
                                                v-if="activeProduit.base_calcul"
                                                class="mt-2 rounded-lg bg-white/80 px-3 py-2 text-xs text-slate-600"
                                            >
                                                Règle : {{ activeProduit.base_calcul }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        v-if="produit?.tranches?.length"
                                        class="mt-4 overflow-hidden rounded-lg border border-slate-200 bg-white"
                                    >
                                        <p class="border-b border-slate-100 px-3 py-2 text-xs font-medium text-slate-500">
                                            Tranches applicables ({{ produit.tranches.length }})
                                        </p>
                                        <div class="max-h-36 overflow-auto">
                                            <table class="w-full text-xs">
                                                <tbody>
                                                    <tr
                                                        v-for="(t, i) in produit.tranches"
                                                        :key="i"
                                                        class="border-t border-slate-50"
                                                    >
                                                        <td class="px-3 py-1.5 text-slate-600">
                                                            {{ fmt(t.montant_min) }}
                                                            →
                                                            {{ t.montant_max == null ? '∞' : fmt(t.montant_max) }}
                                                        </td>
                                                        <td class="px-3 py-1.5 text-right font-medium tabular-nums">
                                                            {{ fmt(t.frais_fixe) }}
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="space-y-3">
                                    <div class="relative">
                                        <Search class="absolute top-3 left-3 h-4 w-4 text-slate-400" />
                                        <Input
                                            v-model="produitSearch"
                                            :class="fieldClass + ' pl-9'"
                                            placeholder="Rechercher un code, libellé, initiateur…"
                                        />
                                    </div>
                                    <div class="max-h-72 space-y-2 overflow-auto pr-1">
                                        <button
                                            v-for="p in filteredProduits"
                                            :key="p.id"
                                            type="button"
                                            class="flex w-full items-start justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3 text-left transition hover:border-primary/40 hover:bg-primary/5"
                                            :class="selectedId === p.id ? 'border-primary bg-primary/5 ring-1 ring-primary/30' : ''"
                                            @click="selectProduit(p.id)"
                                        >
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">
                                                    <span class="text-primary">{{ p.code }}</span>
                                                    — {{ p.libelle }}
                                                </p>
                                                <p class="mt-0.5 text-xs text-slate-500">
                                                    {{ modeLabels[p.mode_frais] || p.mode_frais }}
                                                    <span v-if="p.initiateur"> · {{ p.initiateur }}</span>
                                                </p>
                                            </div>
                                            <ArrowRight class="mt-1 h-4 w-4 shrink-0 text-slate-400" />
                                        </button>
                                        <p
                                            v-if="filteredProduits.length === 0"
                                            class="py-8 text-center text-sm text-slate-500"
                                        >
                                            Aucun produit trouvé.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Saisie -->
                        <section
                            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                            :class="!stepProduitOk ? 'opacity-50 pointer-events-none' : ''"
                        >
                            <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-4">
                                <Calculator class="h-4 w-4 text-primary" />
                                <h2 class="text-sm font-semibold text-slate-900">2. Compléter la saisie</h2>
                            </div>

                            <div class="grid gap-4 p-5 sm:grid-cols-2">
                                <div class="space-y-1.5 sm:col-span-2">
                                    <Label class="text-slate-700">Compte client *</Label>
                                    <Input
                                        v-model="form.compte_client"
                                        required
                                        :class="fieldClass"
                                        :placeholder="produit?.compte_client_mask || '251………'"
                                    />
                                </div>
                                <div class="space-y-1.5">
                                    <Label class="text-slate-700">Nom client</Label>
                                    <Input v-model="form.nom_client" :class="fieldClass" />
                                </div>
                                <div class="space-y-1.5">
                                    <Label class="text-slate-700">Code agence</Label>
                                    <Input v-model="form.code_agence" :class="fieldClass" />
                                </div>
                                <div class="space-y-1.5">
                                    <Label class="text-slate-700">Date valeur</Label>
                                    <Input v-model="form.date_valeur" type="date" :class="fieldClass" />
                                </div>
                                <div class="space-y-1.5">
                                    <Label class="text-slate-700">Référence</Label>
                                    <Input v-model="form.reference" :class="fieldClass" placeholder="Optionnel" />
                                </div>

                                <div
                                    v-if="needsMontant"
                                    class="space-y-1.5 rounded-xl border border-amber-200 bg-amber-50/60 p-3 sm:col-span-2"
                                >
                                    <Label class="text-amber-950">Montant de la demande *</Label>
                                    <Input
                                        v-model="form.montant_demande"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        required
                                        :class="fieldClass"
                                        placeholder="Ex. 2 500 000"
                                    />
                                    <p class="text-xs text-amber-800">
                                        Détermine la tranche ou le taux de frais selon le paramétrage produit.
                                    </p>
                                </div>

                                <div
                                    v-if="needsFraisSaisi"
                                    class="space-y-1.5 rounded-xl border border-sky-200 bg-sky-50/60 p-3 sm:col-span-2"
                                >
                                    <Label class="text-sky-950">Frais HT à saisir *</Label>
                                    <Input
                                        v-model="form.frais_saisi"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        required
                                        :class="fieldClass"
                                    />
                                    <p v-if="activeProduit?.base_calcul" class="text-xs text-sky-800">
                                        {{ activeProduit.base_calcul }}
                                    </p>
                                </div>

                                <div class="space-y-1.5 sm:col-span-2">
                                    <Label class="text-slate-700">Libellé</Label>
                                    <Input v-model="form.libelle" :class="fieldClass" />
                                </div>

                                <p v-if="form.errors.calcul" class="sm:col-span-2 text-sm text-destructive">
                                    {{ form.errors.calcul }}
                                </p>
                            </div>
                        </section>
                    </div>

                    <!-- Colonne droite : calcul sticky -->
                    <aside class="lg:sticky lg:top-6 lg:self-start">
                        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                                <div class="flex items-center gap-2">
                                    <Scale class="h-4 w-4 text-primary" />
                                    <h2 class="text-sm font-semibold text-slate-900">3. Aperçu du calcul</h2>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    :disabled="previewLoading"
                                    @click="runPreview"
                                >
                                    <RefreshCw class="mr-1 h-3.5 w-3.5" :class="previewLoading ? 'animate-spin' : ''" />
                                    Recalculer
                                </Button>
                            </div>

                            <div class="space-y-4 p-5">
                                <div
                                    v-if="previewError"
                                    class="flex gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-950"
                                >
                                    <CircleAlert class="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>{{ previewError }}</span>
                                </div>

                                <div
                                    v-else-if="!preview"
                                    class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                                >
                                    <p v-if="!stepProduitOk">Choisissez d’abord un produit.</p>
                                    <p v-else-if="!form.compte_client.trim()">Saisissez le compte client.</p>
                                    <p v-else>Complétez les montants requis pour voir le calcul.</p>
                                </div>

                                <template v-else>
                                    <div class="grid grid-cols-1 gap-3">
                                        <div class="rounded-xl bg-slate-900 px-4 py-4 text-white">
                                            <p class="text-xs tracking-wide text-slate-300 uppercase">
                                                Débit compte client
                                            </p>
                                            <p class="mt-1 text-2xl font-bold tabular-nums">
                                                {{ fmt(preview.total_client) }}
                                                <span class="text-base font-medium text-slate-300">{{ preview.devise }}</span>
                                            </p>
                                            <p class="mt-1 text-xs text-slate-400">Frais HT + TAF</p>
                                        </div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div class="rounded-xl border border-slate-200 px-3 py-3">
                                                <p class="text-[11px] text-slate-500 uppercase">Frais HT</p>
                                                <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900">
                                                    {{ fmt(preview.frais_ht) }}
                                                </p>
                                            </div>
                                            <div class="rounded-xl border border-slate-200 px-3 py-3">
                                                <p class="text-[11px] text-slate-500 uppercase">
                                                    TAF {{ preview.taux_taf }} %
                                                </p>
                                                <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900">
                                                    {{ fmt(preview.montant_taf) }}
                                                </p>
                                            </div>
                                        </div>
                                        <div
                                            class="flex items-center justify-between rounded-xl px-3 py-2.5 text-sm"
                                            :class="preview.equilibre
                                                ? 'bg-emerald-50 text-emerald-800'
                                                : 'bg-rose-50 text-rose-800'"
                                        >
                                            <span>Équilibre D/C</span>
                                            <span class="font-semibold">
                                                {{ preview.equilibre ? 'Équilibré' : 'Déséquilibré' }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="overflow-hidden rounded-xl border border-slate-200">
                                        <p class="border-b border-slate-100 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-500">
                                            Écritures générées
                                        </p>
                                        <table class="w-full text-xs">
                                            <tbody>
                                                <tr
                                                    v-for="(l, i) in preview.lignes"
                                                    :key="i"
                                                    class="border-t border-slate-100"
                                                >
                                                    <td class="px-3 py-2">
                                                        <span
                                                            class="inline-flex min-w-6 justify-center rounded px-1.5 py-0.5 font-bold"
                                                            :class="l.sens === 'D'
                                                                ? 'bg-rose-100 text-rose-800'
                                                                : 'bg-emerald-100 text-emerald-800'"
                                                        >
                                                            {{ l.sens }}
                                                        </span>
                                                    </td>
                                                    <td class="px-2 py-2 tabular-nums text-slate-700">{{ l.compte }}</td>
                                                    <td class="px-2 py-2 text-slate-500">{{ l.libelle_ecriture }}</td>
                                                    <td class="px-3 py-2 text-right font-medium tabular-nums text-slate-900">
                                                        {{ fmt(l.montant) }}
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </template>

                                <div class="flex flex-col gap-2 border-t border-slate-100 pt-4">
                                    <Button type="submit" class="h-11 w-full" :disabled="!canSubmit">
                                        Enregistrer l’opération
                                    </Button>
                                    <Button as-child type="button" variant="outline" class="h-11 w-full">
                                        <Link href="/pod/operations">Annuler</Link>
                                    </Button>
                                </div>
                            </div>
                        </section>
                    </aside>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
