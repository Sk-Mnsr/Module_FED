
<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import DynamicFieldInput, { type ChampDef } from '@/components/Pod/DynamicFieldInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { labelModeFrais } from '@/lib/podLabels';
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
    bareme_champ: string | null;
    taxe_champ: string | null;
    peut_initier: boolean;
    circuit: Array<{ index: number; code: string | null; libelle: string; role: string }>;
    tranches: Array<{
        libelle: string | null;
        critere: string | null;
        montant_min: string | number;
        montant_max: string | number | null;
        frais_fixe: string | number | null;
        taux: string | number | null;
    }>;
    ecrans: Array<{
        id: number;
        code: string;
        libelle: string;
        description: string | null;
        profils: string[];
    }>;
    champs: ChampDef[];
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
    { title: 'Produits divers', href: '/pod/produits' },
    { title: 'Nouvelle opération', href: '/pod/operations/create' },
];

const fieldClass =
    'h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus-visible:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 disabled:bg-slate-50 disabled:text-slate-500';

const sectionShell = 'overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-900/5';
const sectionHead = 'flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-5 py-3';

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
    champs_saisis: {} as Record<string, string | number | null>,
    fichiers: {} as Record<string, File[]>,
});

const produitSearch = ref('');
const showProductPicker = ref(!props.produit);

const initChampsSaisis = (p: ProduitDetail | null) => {
    const next: Record<string, string | number | null> = {};
    for (const c of p?.champs ?? []) {
        if (c.visible === false || c.type === 'fichier') continue;
        next[c.code] = c.valeur_defaut ?? null;
    }
    form.champs_saisis = next;
    form.fichiers = {};
};

if (props.produit) {
    initChampsSaisis(props.produit);
}

const selectedId = computed(() =>
    form.pod_produit_id === '' ? null : Number(form.pod_produit_id),
);

const activeProduit = computed<ProduitDetail | ProduitOption | null>(() => {
    if (props.produit && Number(props.produit.id) === selectedId.value) {
        return props.produit;
    }
    return props.produits.find((p) => p.id === selectedId.value) ?? null;
});

const dynamiquesChamps = computed(() =>
    (props.produit && Number(props.produit.id) === selectedId.value
        ? props.produit.champs
        : []
    ).filter((c) => c.visible !== false),
);

const champsParEcran = computed(() => {
    const champs = dynamiquesChamps.value as Array<ChampDef & { ecran_code?: string | null }>;
    const ecrans = props.produit && Number(props.produit.id) === selectedId.value
        ? props.produit.ecrans || []
        : [];

    const groups: Array<{
        code: string;
        libelle: string;
        description: string | null;
        champs: typeof champs;
    }> = [];

    for (const e of ecrans) {
        const list = champs.filter((c) => (c.ecran_code || '') === e.code);
        if (list.length) {
            groups.push({
                code: e.code,
                libelle: e.libelle,
                description: e.description,
                champs: list,
            });
        }
    }

    const orphelins = champs.filter(
        (c) => !c.ecran_code || !ecrans.some((e) => e.code === c.ecran_code),
    );
    if (orphelins.length) {
        groups.push({
            code: '_general',
            libelle: ecrans.length ? 'Autres champs' : 'Champs produit',
            description: null,
            champs: orphelins,
        });
    }

    return groups;
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

const tranchesVisibles = computed(() => {
    const p = props.produit;
    if (!p) return [];
    const critere = p.bareme_champ ? String(form.champs_saisis[p.bareme_champ] ?? '') : '';
    if (!critere) return p.tranches;
    return p.tranches.filter((t) => !t.critere || t.critere.toUpperCase() === critere.toUpperCase());
});

const stepProduitOk = computed(() => selectedId.value !== null);
const stepSaisieOk = computed(() => {
    if (activeProduit.value?.mode_frais !== 'gratuit' && !form.compte_client.trim()) return false;
    if (needsMontant.value && (form.montant_demande === '' || Number(form.montant_demande) < 0)) return false;
    if (needsFraisSaisi.value && (form.frais_saisi === '' || Number(form.frais_saisi) < 0)) return false;
    for (const c of dynamiquesChamps.value) {
        if (!c.obligatoire || c.gris) continue;
        if (c.type === 'fichier') {
            if (!(form.fichiers[c.code]?.length)) return false;
            continue;
        }
        const v = form.champs_saisis[c.code];
        if (v === null || v === undefined || v === '') return false;
    }
    return true;
});

const selectProduit = (id: number) => {
    form.pod_produit_id = id;
    showProductPicker.value = false;
    form.montant_demande = '';
    form.frais_saisi = '';
    form.champs_saisis = {};
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
                    initChampsSaisis(p);
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
            if (Object.keys(form.champs_saisis).length === 0) {
                initChampsSaisis(p);
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
                champs_saisis: form.champs_saisis,
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
    () => [
        form.pod_produit_id,
        form.compte_client,
        form.montant_demande,
        form.frais_saisi,
        props.produit?.id,
        props.produit?.bareme_champ ? form.champs_saisis[props.produit.bareme_champ] : null,
        props.produit?.taxe_champ ? form.champs_saisis[props.produit.taxe_champ] : null,
    ],
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

/** Champs grisés : affichés en lecture seule (souvent remplis par la recherche Flexcube). */
const champsLecture = (champs: ChampDef[]) => champs.filter((c) => c.gris && !c.options?.recherche);
const champsEditables = (champs: ChampDef[]) => champs.filter((c) => !c.gris && !c.options?.recherche);
const champsAvecRecherche = (champs: ChampDef[]) => champs.filter((c) => !!c.options?.recherche);

const valeurLecture = (c: ChampDef) => {
    const v = form.champs_saisis[c.code];
    if (v === null || v === undefined || v === '') return '—';
    if (c.type === 'numerique' && !Number.isNaN(Number(v))) return Number(v).toLocaleString('fr-FR');
    return String(v);
};

/** Solde net − (encours global dû + frais TTC) ; négatif = montant que le client doit compléter. */
const soldeApresFrais = computed(() => {
    const codes = new Set(dynamiquesChamps.value.map((c) => c.code));
    if (!codes.has('SOLDE_NET') || !codes.has('ENCOURS_GLOBAL_DUE')) return null;
    const solde = form.champs_saisis.SOLDE_NET;
    const encours = form.champs_saisis.ENCOURS_GLOBAL_DUE;
    if (solde === null || solde === undefined || solde === '' || !preview.value) {
        return { disponible: false, valeur: 0 };
    }
    const valeur = Number(solde) - (Number(encours ?? 0) + Number(preview.value.total_client ?? 0));
    return { disponible: true, valeur };
});

const nomTaxe = (taux: number | string) => {
    const n = Number(taux);
    if (n === 18) return 'TVA 18 %';
    if (n === 10) return 'TAF 10 %';
    if (n === 0) return 'Sans taxe';
    return `Taxe ${n} %`;
};

const sansFrais = computed(() => activeProduit.value?.mode_frais === 'gratuit');

type EtatRecherche = { loading: boolean; error: string | null; valeur: string | null; resume?: string | null };
const recherches = reactive<Record<string, EtatRecherche>>({});

const champsRecherche = computed(() => dynamiquesChamps.value.filter((c) => !!c.options?.recherche));

/** Compte, nom, agence… sont saisis à la main seulement si aucune recherche Flexcube ne les remplit. */
const champsGeneraux = computed(() => !sansFrais.value && champsRecherche.value.length === 0);

const rechercheFaite = (code: string) => {
    const etat = recherches[code];
    return !!etat && etat.valeur !== null && etat.valeur === String(form.champs_saisis[code] ?? '').trim();
};

const viderResultats = () => {
    for (const c of dynamiquesChamps.value) {
        if (c.gris && !c.options?.recherche) form.champs_saisis[c.code] = null;
    }
};

const rechercher = async (champ: ChampDef) => {
    const valeur = String(form.champs_saisis[champ.code] ?? '').trim();
    recherches[champ.code] = { loading: true, error: null, valeur: null };
    if (!valeur) {
        recherches[champ.code] = { loading: false, error: `Saisissez le ${champ.libelle.toLowerCase()} avant de lancer la recherche.`, valeur: null };
        return;
    }
    try {
        const res = await fetch('/pod/operations/recherche', {
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
                champ: champ.code,
                champs_saisis: form.champs_saisis,
            }),
        });
        const data = await res.json();
        if (!res.ok) {
            viderResultats();
            recherches[champ.code] = {
                loading: false,
                error: data.error || data.message || 'La recherche n’a pas abouti. Réessayez.',
                valeur: null,
            };
            return;
        }
        for (const [code, v] of Object.entries((data.valeurs ?? {}) as Record<string, string | number | null>)) {
            form.champs_saisis[code] = v;
        }
        const systeme = (data.systeme ?? {}) as Record<string, string>;
        if (systeme.compte_client) form.compte_client = systeme.compte_client;
        if (systeme.code_agence) form.code_agence = systeme.code_agence;
        if (systeme.nom_client) form.nom_client = systeme.nom_client;
        const titulaire = systeme.nom_client || (data.valeurs?.COMPTE as string | undefined);
        const agence = data.valeurs?.AGENCE as string | undefined;
        recherches[champ.code] = {
            loading: false,
            error: null,
            valeur,
            resume: [titulaire, agence].filter(Boolean).join(' · ') || null,
        };
    } catch {
        recherches[champ.code] = {
            loading: false,
            error: 'Connexion interrompue. Vérifiez le réseau puis relancez la recherche.',
            valeur: null,
        };
    }
};

const canSubmit = computed(
    () =>
        (sansFrais.value || !!preview.value?.equilibre)
        && !form.processing
        && stepSaisieOk.value
        && champsRecherche.value.every((c) => rechercheFaite(c.code))
        && props.produit?.peut_initier !== false,
);

const champsManquants = computed(() => [
    ...dynamiquesChamps.value
        .filter((c) => c.obligatoire && !c.gris)
        .filter((c) =>
            c.type === 'fichier'
                ? !(form.fichiers[c.code]?.length)
                : [null, undefined, ''].includes(form.champs_saisis[c.code] as null),
        )
        .map((c) => c.libelle),
    ...champsRecherche.value
        .filter((c) => !rechercheFaite(c.code))
        .map((c) => `Recherche Flexcube : ${c.libelle}`),
    ...(needsMontant.value && (form.montant_demande === '' || form.montant_demande === null) ? ['Montant demandé'] : []),
]);

const submit = () => {
    form.transform((data) => ({
        ...data,
        pod_produit_id: selectedId.value,
        montant_demande: data.montant_demande === '' ? null : data.montant_demande,
        frais_saisi: data.frais_saisi === '' ? null : data.frais_saisi,
        compte_client: sansFrais.value ? null : data.compte_client,
        libelle: sansFrais.value ? null : data.libelle,
        champs_saisis: data.champs_saisis ?? {},
        fichiers: data.fichiers ?? {},
    })).post('/pod/operations', { forceFormData: true });
};
</script>

<template>
    <Head title="Nouvelle opération — Produits divers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-full flex-1 flex-col bg-gradient-to-b from-slate-50 via-white to-white">
            <form class="flex w-full flex-1 flex-col gap-5 p-4 sm:p-6" @submit.prevent="submit">
                <!-- En-tête -->
                <div
                    class="flex flex-col gap-4 rounded-2xl border border-slate-200/80 bg-white px-5 py-4 shadow-sm ring-1 ring-slate-900/5 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div>
                        <p class="text-[11px] font-semibold tracking-[0.18em] text-primary uppercase">Produits divers</p>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Nouvelle opération</h1>
                        <p class="mt-1 text-sm text-slate-500">
                            {{
                                sansFrais
                                    ? 'Remplissez le premier écran, le dossier suit ensuite son circuit.'
                                    : 'Remplissez l’écran : frais, taxe et écritures se calculent seuls.'
                            }}
                        </p>
                    </div>

                    <ol class="flex flex-wrap items-center gap-2 text-xs sm:text-sm">
                        <li
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 font-medium"
                            :class="stepProduitOk ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                        >
                            <CheckCircle2 v-if="stepProduitOk" class="h-3.5 w-3.5" />
                            <span v-else class="flex h-4 w-4 items-center justify-center rounded-full bg-slate-300 text-[10px] text-white">1</span>
                            Produit
                        </li>
                        <ArrowRight class="h-3.5 w-3.5 text-slate-300" />
                        <li
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 font-medium"
                            :class="stepSaisieOk && champsManquants.length === 0 ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                        >
                            <CheckCircle2 v-if="stepSaisieOk && champsManquants.length === 0" class="h-3.5 w-3.5" />
                            <span v-else class="flex h-4 w-4 items-center justify-center rounded-full bg-slate-300 text-[10px] text-white">2</span>
                            Saisie
                        </li>
                        <template v-if="!sansFrais">
                            <ArrowRight class="h-3.5 w-3.5 text-slate-300" />
                            <li
                                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 font-medium"
                                :class="preview?.equilibre ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                            >
                                <CheckCircle2 v-if="preview?.equilibre" class="h-3.5 w-3.5" />
                                <span v-else class="flex h-4 w-4 items-center justify-center rounded-full bg-slate-300 text-[10px] text-white">3</span>
                                Calcul
                            </li>
                        </template>
                    </ol>
                </div>

                <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_400px]">
                    <div class="flex min-w-0 flex-col gap-5">
                        <!-- Produit -->
                        <section :class="sectionShell">
                            <div :class="sectionHead">
                                <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                                    <FileText class="h-4 w-4 text-primary" />
                                    Produit
                                </h2>
                                <Button
                                    v-if="activeProduit && !showProductPicker"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="bg-white"
                                    @click="showProductPicker = true"
                                >
                                    Changer
                                </Button>
                            </div>

                            <div v-if="activeProduit && !showProductPicker" class="space-y-4 p-5">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                                    <span class="rounded-md bg-slate-900 px-2 py-0.5 font-mono text-xs font-semibold text-white">
                                        {{ activeProduit.code }}
                                    </span>
                                    <span class="text-lg font-semibold text-slate-900">{{ activeProduit.libelle }}</span>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-slate-200">
                                        {{ labelModeFrais(activeProduit.mode_frais) }}
                                    </span>
                                    <span
                                        v-if="activeProduit.mode_frais === 'fixe' && activeProduit.frais_fixe != null"
                                        class="text-sm text-slate-500"
                                    >
                                        {{ fmt(activeProduit.frais_fixe) }} {{ activeProduit.devise }}
                                    </span>
                                </div>

                                <ol
                                    v-if="produit?.circuit?.length && Number(produit.id) === selectedId"
                                    class="flex flex-wrap items-center gap-2 text-xs"
                                >
                                    <template v-for="(s, i) in produit.circuit" :key="s.index">
                                        <ArrowRight v-if="i > 0" class="h-3.5 w-3.5 text-slate-300" />
                                        <li
                                            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 ring-1"
                                            :class="s.index === 0 ? 'bg-primary/5 text-primary ring-primary/20' : 'bg-white text-slate-600 ring-slate-200'"
                                        >
                                            <span class="font-semibold">{{ s.index + 1 }}.</span>
                                            {{ s.libelle }}
                                            <span class="text-slate-400">· {{ s.role }}</span>
                                        </li>
                                    </template>
                                </ol>

                                <p v-if="activeProduit.base_calcul" class="text-xs text-slate-500">
                                    Règle : {{ activeProduit.base_calcul }}
                                </p>

                                <p
                                    v-if="produit && Number(produit.id) === selectedId && !produit.peut_initier"
                                    class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900"
                                >
                                    Votre rôle ne permet pas d’initier ce produit.
                                </p>

                                <details
                                    v-if="produit?.tranches?.length"
                                    class="group rounded-xl border border-slate-200 bg-slate-50/60"
                                >
                                    <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-2.5 text-sm font-medium text-slate-700">
                                        Barème des frais ({{ tranchesVisibles.length }} tranche{{ tranchesVisibles.length > 1 ? 's' : '' }})
                                        <ArrowRight class="h-4 w-4 text-slate-400 transition-transform group-open:rotate-90" />
                                    </summary>
                                    <table class="w-full border-t border-slate-200 text-sm">
                                        <tbody>
                                            <tr v-for="(t, i) in tranchesVisibles" :key="i" class="border-t border-slate-100 first:border-0">
                                                <td class="px-4 py-2 text-slate-600">
                                                    {{ t.libelle || `${fmt(t.montant_min)} → ${t.montant_max == null ? '…' : fmt(t.montant_max)}` }}
                                                </td>
                                                <td class="px-4 py-2 text-right font-semibold tabular-nums text-slate-900">
                                                    {{ fmt(t.frais_fixe) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </details>
                            </div>

                            <div v-else class="space-y-3 p-5">
                                <div class="relative">
                                    <Search class="absolute top-3.5 left-3 h-4 w-4 text-slate-400" />
                                    <Input
                                        v-model="produitSearch"
                                        :class="fieldClass + ' pl-9'"
                                        placeholder="Rechercher un code ou un libellé…"
                                    />
                                </div>
                                <div class="max-h-80 space-y-2 overflow-auto pr-1">
                                    <button
                                        v-for="p in filteredProduits"
                                        :key="p.id"
                                        type="button"
                                        class="flex w-full items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3 text-left transition hover:border-primary/40 hover:bg-primary/5"
                                        :class="selectedId === p.id ? 'border-primary bg-primary/5 ring-1 ring-primary/30' : ''"
                                        @click="selectProduit(p.id)"
                                    >
                                        <span class="min-w-0">
                                            <span class="flex items-center gap-2">
                                                <span class="rounded bg-slate-900 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-white">{{ p.code }}</span>
                                                <span class="truncate text-sm font-semibold text-slate-900">{{ p.libelle }}</span>
                                            </span>
                                            <span class="mt-1 block text-xs text-slate-500">{{ labelModeFrais(p.mode_frais) }}</span>
                                        </span>
                                        <ArrowRight class="h-4 w-4 shrink-0 text-slate-400" />
                                    </button>
                                    <p v-if="filteredProduits.length === 0" class="py-8 text-center text-sm text-slate-500">
                                        Aucun produit trouvé.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <!-- Saisie -->
                        <section :class="[sectionShell, !stepProduitOk ? 'pointer-events-none opacity-50' : '']">
                            <div :class="sectionHead">
                                <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                                    <Calculator class="h-4 w-4 text-primary" />
                                    {{ !champsGeneraux && produit?.circuit?.length ? produit.circuit[0].libelle : 'Saisie' }}
                                </h2>
                            </div>

                            <div class="space-y-6 p-5">
                                <div v-if="champsGeneraux" class="grid gap-4 sm:grid-cols-2">
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
                                </div>

                                <div v-for="(group, gi) in champsParEcran" :key="group.code" class="space-y-5">
                                    <p
                                        v-if="champsGeneraux && group.code !== '_general'"
                                        class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase"
                                    >
                                        {{ group.libelle }}
                                    </p>

                                    <!-- Recherche Flexcube -->
                                    <div v-for="c in champsAvecRecherche(group.champs)" :key="c.code" class="space-y-2">
                                        <div class="flex items-end gap-2">
                                            <div class="min-w-0 flex-1">
                                                <DynamicFieldInput
                                                    :champ="c"
                                                    :model-value="form.champs_saisis[c.code] ?? null"
                                                    :input-class="fieldClass"
                                                    :error="(form.errors as Record<string, string>)[`champs_saisis.${c.code}`]"
                                                    @update:model-value="(v) => (form.champs_saisis[c.code] = v)"
                                                />
                                            </div>
                                            <Button
                                                type="button"
                                                class="h-11 shrink-0 px-5"
                                                :disabled="recherches[c.code]?.loading"
                                                @click="rechercher(c)"
                                            >
                                                <RefreshCw v-if="recherches[c.code]?.loading" class="mr-2 h-4 w-4 animate-spin" />
                                                <Search v-else class="mr-2 h-4 w-4" />
                                                Rechercher
                                            </Button>
                                        </div>
                                        <p v-if="recherches[c.code]?.error" class="flex items-center gap-1.5 text-sm text-destructive">
                                            <CircleAlert class="h-4 w-4" /> {{ recherches[c.code]?.error }}
                                        </p>
                                        <p v-else-if="rechercheFaite(c.code)" class="flex items-center gap-1.5 text-sm text-emerald-700">
                                            <CheckCircle2 class="h-4 w-4 shrink-0" />
                                            <span>
                                                Compte vérifié<template v-if="recherches[c.code]?.resume"> :
                                                    <strong class="font-semibold">{{ recherches[c.code]?.resume }}</strong></template>
                                            </span>
                                        </p>
                                        <p v-else class="text-xs text-slate-500">
                                            Lancez la recherche pour récupérer les informations du compte.
                                        </p>
                                    </div>

                                    <!-- Informations en lecture seule -->
                                    <dl
                                        v-if="champsLecture(group.champs).length"
                                        class="grid gap-px overflow-hidden rounded-xl border border-slate-200 bg-slate-200 sm:grid-cols-2 lg:grid-cols-3"
                                    >
                                        <div
                                            v-for="c in champsLecture(group.champs)"
                                            :key="c.code"
                                            class="px-4 py-3"
                                            :class="c.code === 'SOLDE_NET' ? 'bg-emerald-50' : 'bg-slate-50/80'"
                                        >
                                            <dt
                                                class="text-[11px] font-medium tracking-wide uppercase"
                                                :class="c.code === 'SOLDE_NET' ? 'text-emerald-700' : 'text-slate-500'"
                                            >
                                                {{ c.libelle }}
                                            </dt>
                                            <dd
                                                class="mt-1 truncate text-sm"
                                                :class="
                                                    valeurLecture(c) === '—'
                                                        ? 'font-semibold text-slate-300'
                                                        : c.code === 'SOLDE_NET'
                                                          ? 'font-bold tabular-nums text-emerald-700'
                                                          : 'font-semibold text-slate-900'
                                                "
                                                :title="valeurLecture(c)"
                                            >
                                                {{ valeurLecture(c) }}
                                            </dd>
                                        </div>
                                        <div
                                            v-if="soldeApresFrais && champsLecture(group.champs).some((c) => c.code === 'SOLDE_NET')"
                                            class="px-4 py-3"
                                            :class="
                                                !soldeApresFrais.disponible
                                                    ? 'bg-slate-50/80'
                                                    : soldeApresFrais.valeur < 0
                                                      ? 'bg-rose-50'
                                                      : 'bg-emerald-50'
                                            "
                                        >
                                            <dt
                                                class="text-[11px] font-medium tracking-wide uppercase"
                                                :class="
                                                    !soldeApresFrais.disponible
                                                        ? 'text-slate-500'
                                                        : soldeApresFrais.valeur < 0
                                                          ? 'text-rose-700'
                                                          : 'text-emerald-700'
                                                "
                                            >
                                                {{ soldeApresFrais.disponible && soldeApresFrais.valeur < 0 ? 'Montant à compléter' : 'Solde après frais' }}
                                            </dt>
                                            <dd
                                                v-if="soldeApresFrais.disponible"
                                                class="mt-1 text-sm font-bold tabular-nums"
                                                :class="soldeApresFrais.valeur < 0 ? 'text-rose-700' : 'text-emerald-700'"
                                            >
                                                {{ Math.abs(soldeApresFrais.valeur).toLocaleString('fr-FR') }}
                                            </dd>
                                            <dd v-else class="mt-1 text-sm font-semibold text-slate-300">—</dd>                                        </div>
                                    </dl>

                                    <!-- Champs à saisir (+ montant demandé sur le dernier groupe) -->
                                    <div
                                        v-if="champsEditables(group.champs).length || (needsMontant && gi === champsParEcran.length - 1)"
                                        class="grid items-start gap-4 sm:grid-cols-2"
                                    >
                                        <DynamicFieldInput
                                            v-for="c in champsEditables(group.champs)"
                                            :key="c.code"
                                            :champ="c"
                                            :model-value="form.champs_saisis[c.code] ?? null"
                                            :input-class="fieldClass"
                                            :error="(form.errors as Record<string, string>)[`champs_saisis.${c.code}`]"
                                            @update:model-value="(v) => (form.champs_saisis[c.code] = v)"
                                            @files="(files) => (form.fichiers[c.code] = files)"
                                        />
                                        <div v-if="needsMontant && gi === champsParEcran.length - 1" class="space-y-1.5">
                                            <Label class="text-slate-700">Montant demandé <span class="text-destructive">*</span></Label>
                                            <Input
                                                v-model="form.montant_demande"
                                                type="number"
                                                step="1"
                                                min="0"
                                                required
                                                :class="fieldClass"
                                                placeholder="Ex. 2 500 000"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div v-if="(needsMontant && !champsParEcran.length) || needsFraisSaisi" class="grid gap-4 sm:grid-cols-2">
                                    <div v-if="needsMontant && !champsParEcran.length" class="space-y-1.5">
                                        <Label class="text-slate-700">Montant demandé *</Label>
                                        <Input
                                            v-model="form.montant_demande"
                                            type="number"
                                            step="1"
                                            min="0"
                                            required
                                            :class="fieldClass"
                                            placeholder="Ex. 2 500 000"
                                        />
                                        <p class="text-xs text-slate-500">
                                            Les frais sont calculés à partir de ce montant<span v-if="produit?.bareme_champ"> et du type choisi</span>.
                                        </p>
                                    </div>
                                    <div v-if="needsFraisSaisi" class="space-y-1.5">
                                        <Label class="text-slate-700">Frais HT à saisir *</Label>
                                        <Input
                                            v-model="form.frais_saisi"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            required
                                            :class="fieldClass"
                                        />
                                        <p v-if="activeProduit?.base_calcul" class="text-xs text-slate-500">{{ activeProduit.base_calcul }}</p>
                                    </div>
                                </div>

                                <div v-if="champsGeneraux" class="space-y-1.5">
                                    <Label class="text-slate-700">Libellé</Label>
                                    <Input v-model="form.libelle" :class="fieldClass" />
                                </div>

                                <p v-if="!champsGeneraux && !champsParEcran.length" class="text-sm text-slate-500">
                                    Aucun champ n’est paramétré sur le premier écran de ce produit.
                                </p>

                                <p v-if="(form.errors as Record<string, string>).calcul" class="text-sm text-destructive">
                                    {{ (form.errors as Record<string, string>).calcul }}
                                </p>
                            </div>
                        </section>
                    </div>

                    <!-- Colonne droite -->
                    <aside class="flex flex-col gap-5 xl:sticky xl:top-6 xl:self-start">
                        <section v-if="!sansFrais" :class="sectionShell">
                            <div :class="sectionHead">
                                <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                                    <Scale class="h-4 w-4 text-primary" />
                                    Calcul
                                </h2>
                                <Button type="button" variant="ghost" size="sm" :disabled="previewLoading" @click="runPreview">
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
                                    <p v-else-if="!form.compte_client.trim()">
                                        {{ champsRecherche.length ? 'Lancez la recherche du compte.' : 'Saisissez le compte client.' }}
                                    </p>
                                    <p v-else>Complétez les montants pour voir le calcul.</p>
                                </div>

                                <template v-else>
                                    <div class="rounded-xl bg-slate-900 px-5 py-4 text-white">
                                        <p class="text-[11px] tracking-wide text-slate-300 uppercase">Débit compte client</p>
                                        <p class="mt-1 text-3xl font-bold tabular-nums">
                                            {{ fmt(preview.total_client) }}
                                            <span class="text-base font-medium text-slate-300">{{ preview.devise }}</span>
                                        </p>
                                        <p v-if="preview.tranche?.libelle" class="mt-1 text-xs text-slate-400">
                                            Tranche : {{ preview.tranche.libelle }}
                                        </p>
                                    </div>

                                    <dl class="divide-y divide-slate-100 rounded-xl border border-slate-200 text-sm">
                                        <div class="flex items-center justify-between px-4 py-2.5">
                                            <dt class="text-slate-500">Frais HT</dt>
                                            <dd class="font-semibold tabular-nums text-slate-900">{{ fmt(preview.frais_ht) }}</dd>
                                        </div>
                                        <div
                                            v-if="Number(preview.taux_taf) > 0 || Number(preview.montant_taf) > 0"
                                            class="flex items-center justify-between px-4 py-2.5"
                                        >
                                            <dt class="text-slate-500">{{ nomTaxe(preview.taux_taf) }}</dt>
                                            <dd class="font-semibold tabular-nums text-slate-900">{{ fmt(preview.montant_taf) }}</dd>
                                        </div>
                                        <div class="flex items-center justify-between px-4 py-2.5">
                                            <dt class="text-slate-500">Équilibre débit / crédit</dt>
                                            <dd
                                                class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                                :class="preview.equilibre ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                                            >
                                                {{ preview.equilibre ? 'Équilibré' : 'Déséquilibré' }}
                                            </dd>
                                        </div>
                                    </dl>

                                    <details v-if="preview.lignes.length" class="group rounded-xl border border-slate-200">
                                        <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-2.5 text-sm font-medium text-slate-700">
                                            Écritures ({{ preview.lignes.length }})
                                            <ArrowRight class="h-4 w-4 text-slate-400 transition-transform group-open:rotate-90" />
                                        </summary>
                                        <ul class="divide-y divide-slate-100 border-t border-slate-100 text-xs">
                                            <li v-for="(l, i) in preview.lignes" :key="i" class="flex items-center gap-2 px-4 py-2">
                                                <span
                                                    class="inline-flex w-5 justify-center rounded font-bold"
                                                    :class="l.sens === 'D' ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800'"
                                                >
                                                    {{ l.sens }}
                                                </span>
                                                <span class="font-mono tabular-nums" :class="/X{3,}/i.test(l.compte) ? 'text-amber-700' : 'text-slate-700'">
                                                    {{ l.compte }}
                                                </span>
                                                <span class="min-w-0 flex-1 truncate text-slate-500" :title="l.libelle_ecriture ?? ''">{{ l.libelle_ecriture }}</span>
                                                <span class="font-semibold tabular-nums text-slate-900">{{ fmt(l.montant) }}</span>
                                            </li>
                                        </ul>
                                        <p
                                            v-if="preview.lignes.some((l) => /X{3,}/i.test(l.compte))"
                                            class="border-t border-amber-100 bg-amber-50 px-4 py-2 text-xs text-amber-900"
                                        >
                                            Compte produit non renseigné : la validation finale sera refusée.
                                        </p>
                                    </details>
                                </template>
                            </div>
                        </section>

                        <section :class="sectionShell">
                            <div class="space-y-4 p-5">
                                <ol v-if="sansFrais && produit?.circuit?.length" class="space-y-2">
                                    <li v-for="s in produit.circuit" :key="s.index" class="flex items-start gap-2.5 text-sm">
                                        <span
                                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                                            :class="s.index === 0 ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600'"
                                        >
                                            {{ s.index + 1 }}
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block font-medium text-slate-900">{{ s.libelle }}</span>
                                            <span class="block text-xs text-slate-500">{{ s.role }}</span>
                                        </span>
                                    </li>
                                </ol>

                                <div
                                    v-if="stepProduitOk && champsManquants.length"
                                    class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900"
                                >
                                    <p class="font-semibold">À compléter</p>
                                    <ul class="mt-1 list-disc space-y-0.5 pl-4">
                                        <li v-for="m in champsManquants" :key="m">{{ m }}</li>
                                    </ul>
                                </div>

                                <Button type="submit" class="h-12 w-full text-base" :disabled="!canSubmit">
                                    {{ form.processing ? 'Envoi…' : 'Soumettre la demande' }}
                                </Button>
                                <p v-if="produit?.circuit && produit.circuit.length > 1" class="text-center text-xs text-slate-500">
                                    Ensuite : {{ produit.circuit[1].libelle }} · {{ produit.circuit[1].role }}
                                </p>
                                <Button as-child type="button" variant="outline" class="h-11 w-full bg-white">
                                    <Link href="/pod/operations">Annuler</Link>
                                </Button>
                            </div>
                        </section>
                    </aside>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
