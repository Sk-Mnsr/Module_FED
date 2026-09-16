<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import DynamicFieldInput, { type ChampDef } from '@/components/Pod/DynamicFieldInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { labelModeFrais, labelStatutProduit, statutProduitClass } from '@/lib/podLabels';
import {
    ArrowLeft,
    ChevronDown,
    ChevronUp,
    FileText,
    LayoutList,
    Plus,
    Settings2,
    TableProperties,
    Trash2,
} from 'lucide-vue-next';

type Tranche = {
    libelle?: string | null;
    montant_min?: number | string | null;
    montant_max?: number | string | null;
    frais_fixe?: number | string | null;
    taux?: number | string | null;
    frais_min?: number | string | null;
    frais_max?: number | string | null;
};

type Ligne = {
    sens: string;
    compte: string;
    libelle_ecriture?: string | null;
    nature_compte?: string;
    type_montant?: string;
    montant_fixe?: number | string | null;
    taux?: number | string | null;
    obligatoire?: boolean;
};

type TableSource = {
    code: string;
    libelle: string;
    columns: Array<{ code: string; libelle: string }>;
};

type EcranForm = {
    code: string;
    libelle: string;
    description?: string | null;
    profils: string[];
    actif: boolean;
};

type ConstanteForm = {
    code: string;
    libelle: string;
    type: string;
    mode: string;
    valeur_reference?: string | null;
    obligatoire: boolean;
};

type ScriptForm = {
    code: string;
    libelle: string;
    moteur: string;
    parametres: Record<string, string | number | null>;
    description?: string | null;
    actif: boolean;
};

type ProfilOption = { key: string; label: string };
type MoteurOption = { key: string; label: string; description: string };

type ChampForm = ChampDef & {
    options: NonNullable<ChampDef['options']>;
    valeurs?: Array<{ code: string; libelle: string }>;
    ecran_code?: string | null;
};

type Produit = {
    id?: number;
    code: string;
    libelle: string;
    type_operation?: string | null;
    devise: string;
    code_taf?: string | null;
    libelle_ecriture_produit?: string | null;
    libelle_ecriture_taf?: string | null;
    compte_produit?: string | null;
    compte_taf?: string | null;
    compte_client_mask?: string | null;
    initiateur?: string | null;
    validateur?: string | null;
    mode_frais: string;
    frais_fixe?: number | string | null;
    taux_frais?: number | string | null;
    frais_min?: number | string | null;
    frais_max?: number | string | null;
    taux_taf?: number | string | null;
    base_calcul?: string | null;
    notes?: string | null;
    statut: string;
    actif: boolean;
    tranches?: Tranche[];
    lignes?: Ligne[];
    champs?: ChampForm[];
    ecrans?: EcranForm[];
    constantes?: ConstanteForm[];
    scripts?: ScriptForm[];
};

const props = defineProps<{
    produit: Produit | null;
    tauxTafDefaut: number;
    statuts: string[];
    modesFrais: string[];
    naturesCompte: string[];
    typesMontant: string[];
    initiateurs: string[];
    typesChamp: string[];
    tablesSources: TableSource[];
    profilsEcran: ProfilOption[];
    ecranTemplates: EcranForm[];
    modesConstante: string[];
    typesConstante: string[];
    moteursScript: MoteurOption[];
}>();

const editing = computed(() => !!props.produit?.id);

const breadcrumbs = computed(() => [
    { title: 'Produits divers', href: '/pod/produits' },
    {
        title: editing.value ? `Modifier ${props.produit?.code}` : 'Nouveau produit',
        href: editing.value ? `/pod/produits/${props.produit?.id}/edit` : '/pod/produits/create',
    },
]);

const emptyLigne = (): Ligne => ({
    sens: 'D',
    compte: '',
    libelle_ecriture: '',
    nature_compte: 'client',
    type_montant: 'frais_ht',
    obligatoire: true,
});

const emptyTranche = (): Tranche => ({
    libelle: '',
    montant_min: 0,
    montant_max: null,
    frais_fixe: null,
    taux: null,
    frais_min: null,
    frais_max: null,
});

const emptyChamp = (): ChampForm => ({
    code: '',
    libelle: '',
    type: 'texte',
    obligatoire: false,
    visible: true,
    gris: false,
    valeur_defaut: '',
    ecran_code: '',
    options: {
        max_length: 255,
        min: null,
        max: null,
        decimales: 2,
        valeurs: [],
        table_code: '',
        colonne_valeur: '',
        colonne_libelle: '',
        colonnes_desactivees: [],
    },
});

const emptyEcran = (): EcranForm => ({
    code: '',
    libelle: '',
    description: '',
    profils: [],
    actif: true,
});

const emptyConstante = (): ConstanteForm => ({
    code: '',
    libelle: '',
    type: 'texte',
    mode: 'defaut',
    valeur_reference: '',
    obligatoire: false,
});

const emptyScript = (): ScriptForm => ({
    code: '',
    libelle: '',
    moteur: 'valeur_fixe',
    parametres: { valeur: '' },
    description: '',
    actif: true,
});

const normalizeChamp = (c: ChampForm): ChampForm => ({
    ...emptyChamp(),
    ...c,
    ecran_code: c.ecran_code ?? '',
    options: {
        ...emptyChamp().options,
        ...(c.options ?? {}),
        valeurs: c.options?.valeurs ?? [],
    },
});

const normalizeEcran = (e: EcranForm): EcranForm => ({
    ...emptyEcran(),
    ...e,
    profils: [...(e.profils ?? [])],
});

const form = useForm({
    code: props.produit?.code ?? '',
    libelle: props.produit?.libelle ?? '',
    type_operation: props.produit?.type_operation ?? '',
    devise: props.produit?.devise ?? 'XOF',
    code_taf: props.produit?.code_taf ?? '',
    libelle_ecriture_produit: props.produit?.libelle_ecriture_produit ?? '',
    libelle_ecriture_taf: props.produit?.libelle_ecriture_taf ?? '',
    compte_produit: props.produit?.compte_produit ?? '',
    compte_taf: props.produit?.compte_taf ?? '331431012',
    compte_client_mask: props.produit?.compte_client_mask ?? '251XXXXXX',
    initiateur: props.produit?.initiateur ?? 'CC',
    validateur: props.produit?.validateur ?? '',
    mode_frais: props.produit?.mode_frais ?? 'fixe',
    frais_fixe: props.produit?.frais_fixe ?? '',
    taux_frais: props.produit?.taux_frais ?? '',
    frais_min: props.produit?.frais_min ?? '',
    frais_max: props.produit?.frais_max ?? '',
    taux_taf: (() => {
        const v = props.produit?.taux_taf;
        if (v === null || v === undefined || v === '') return '0';
        const n = Number(v);
        if (n === 0) return '0';
        if (n === 10) return '10';
        if (n === 18) return '18';
        return '0';
    })(),
    base_calcul: props.produit?.base_calcul ?? '',
    notes: props.produit?.notes ?? '',
    statut: props.produit?.statut ?? 'brouillon',
    actif: props.produit?.actif ?? true,
    tranches: (props.produit?.tranches?.length ? props.produit.tranches : []) as Tranche[],
    lignes: (props.produit?.lignes?.length ? props.produit.lignes : [emptyLigne(), emptyLigne()]) as Ligne[],
    ecrans: (props.produit?.ecrans?.length
        ? props.produit.ecrans.map(normalizeEcran)
        : []) as EcranForm[],
    champs: (props.produit?.champs?.length
        ? props.produit.champs.map(normalizeChamp)
        : []) as ChampForm[],
    scripts: (props.produit?.scripts?.length
        ? props.produit.scripts.map((s) => ({
              ...emptyScript(),
              ...s,
              parametres: { ...(s.parametres || {}) },
          }))
        : []) as ScriptForm[],
    constantes: (props.produit?.constantes?.length
        ? props.produit.constantes.map((c) => ({ ...emptyConstante(), ...c }))
        : []) as ConstanteForm[],
});

const previewValues = reactive<Record<string, string | number | null>>({});
const tablePreviewCache = ref<Record<string, Array<{ code: string; libelle: string }>>>({});

const visibleChamps = computed(() => form.champs.filter((c) => c.visible !== false && c.libelle));

const previewChamps = computed(() =>
    visibleChamps.value.map((c) => {
        if (c.type !== 'table') return c;
        const key = [
            c.options.table_code,
            c.options.colonne_valeur,
            c.options.colonne_libelle,
            (c.options.colonnes_desactivees || []).join(','),
        ].join('|');
        return {
            ...c,
            valeurs: tablePreviewCache.value[key] || c.valeurs || [],
        };
    }),
);

const columnsForTable = (code?: string) =>
    (props.tablesSources ?? []).find((t) => t.code === code)?.columns ?? [];

const tablePreviewKey = (c: ChampForm) =>
    [
        c.options.table_code,
        c.options.colonne_valeur,
        c.options.colonne_libelle,
        (c.options.colonnes_desactivees || []).join(','),
    ].join('|');

watch(
    () =>
        form.champs
            .filter((c) => c.type === 'table')
            .map((c) => tablePreviewKey(c))
            .join('||'),
    async () => {
        for (const c of form.champs) {
            if (c.type !== 'table') continue;
            const table = c.options.table_code;
            const valeur = c.options.colonne_valeur;
            const libelle = c.options.colonne_libelle;
            if (!table || !valeur || !libelle) continue;
            const key = tablePreviewKey(c);
            if (tablePreviewCache.value[key]) continue;
            try {
                const params = new URLSearchParams({
                    colonne_valeur: valeur,
                    colonne_libelle: libelle,
                });
                for (const d of c.options.colonnes_desactivees || []) {
                    params.append('colonnes_desactivees[]', d);
                }
                const res = await fetch(`/pod/tables/options/${encodeURIComponent(table)}?${params}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok) continue;
                const data = await res.json();
                tablePreviewCache.value = {
                    ...tablePreviewCache.value,
                    [key]: data.valeurs || [],
                };
            } catch {
                // ignore preview errors
            }
        }
    },
    { immediate: true },
);

const toggleColonneDesactivee = (champ: ChampForm, code: string) => {
    const list = [...(champ.options.colonnes_desactivees || [])];
    const i = list.indexOf(code);
    if (i >= 0) list.splice(i, 1);
    else list.push(code);
    champ.options.colonnes_desactivees = list;
    // bust cache
    const keys = Object.keys(tablePreviewCache.value).filter((k) => k.startsWith(`${champ.options.table_code}|`));
    const next = { ...tablePreviewCache.value };
    for (const k of keys) delete next[k];
    tablePreviewCache.value = next;
};

const moveChamp = (index: number, dir: -1 | 1) => {
    const target = index + dir;
    if (target < 0 || target >= form.champs.length) return;
    const copy = [...form.champs];
    const tmp = copy[index];
    copy[index] = copy[target];
    copy[target] = tmp;
    form.champs = copy;
};

const addListeValeur = (champ: ChampForm) => {
    if (!champ.options.valeurs) champ.options.valeurs = [];
    champ.options.valeurs.push({
        code: '',
        libelle: '',
        actif: true,
        ordre: champ.options.valeurs.length,
    });
};

const applyEcranTemplates = () => {
    const existing = new Set(form.ecrans.map((e) => e.code.toUpperCase()));
    for (const t of props.ecranTemplates || []) {
        if (existing.has(t.code.toUpperCase())) continue;
        form.ecrans.push(normalizeEcran({ ...t, actif: true }));
    }
};

const submit = () => {
    if (editing.value) {
        form.put(`/pod/produits/${props.produit!.id}`);
    } else {
        form.post('/pod/produits');
    }
};

const fieldError = (key: string) => (form.errors as Record<string, string>)[key];

const selectClass =
    'h-11 w-full rounded-lg border border-input bg-background px-3 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30';

const fieldClass = 'space-y-1.5';
const sectionShell =
    'overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-900/5';
const sectionHead =
    'flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-5 py-3.5';
const sectionTitle = 'text-sm font-semibold tracking-tight text-slate-900';
const sectionSub = 'mt-0.5 text-xs text-slate-500';
const sectionBody = 'space-y-5 p-5 sm:p-6';

const typeLabels: Record<string, string> = {
    texte: 'Texte',
    liste: 'Liste de valeurs',
    numerique: 'Nombre',
    date: 'Date',
    table: 'Table (référentiel)',
};

const modeConstanteLabels: Record<string, string> = {
    defaut: 'Valeur par défaut',
    champ: 'Champ saisi',
    schema: 'Schéma comptable',
    script: 'Script',
};

const refPlaceholder = (mode: string) => {
    if (mode === 'champ') return 'CODE_CHAMP';
    if (mode === 'script') return 'CODE_SCRIPT';
    if (mode === 'schema') return 'compte_produit | compte_taf | frais_fixe…';
    return 'Valeur fixe';
};

type FormTab = 'base' | 'constantes' | 'champs' | 'schema';

const selectedEcranIndex = ref<number | null>(null);
const activeTab = ref<FormTab>('base');

const selectedEcran = computed(() =>
    selectedEcranIndex.value === null ? null : form.ecrans[selectedEcranIndex.value] ?? null,
);

const openEcran = (index: number) => {
    selectedEcranIndex.value = index;
    activeTab.value = 'base';
};

const closeEcran = () => {
    selectedEcranIndex.value = null;
    activeTab.value = 'base';
};

const profilLabel = (key: string) =>
    (props.profilsEcran || []).find((p) => p.key === key)?.label || key;

const champsCountForEcran = (ecranCode: string) =>
    form.champs.filter((c) => (c.ecran_code || '').toUpperCase() === ecranCode.toUpperCase()).length;

const champsOfSelectedEcran = computed(() => {
    const ecran = selectedEcran.value;
    if (!ecran) return [] as Array<{ champ: ChampForm; index: number }>;
    const code = (ecran.code || '').toUpperCase();
    return form.champs
        .map((champ, index) => ({ champ, index }))
        .filter(({ champ }) => (champ.ecran_code || '').toUpperCase() === code);
});

const addChampToSelectedEcran = () => {
    const ecran = selectedEcran.value;
    if (!ecran) return;
    form.champs.push({
        ...emptyChamp(),
        ecran_code: ecran.code || '',
    });
};

const tabs: Array<{ id: FormTab; label: string }> = [
    { id: 'base', label: 'Base' },
    { id: 'constantes', label: 'Constantes' },
    { id: 'champs', label: 'Champs' },
    { id: 'schema', label: 'Schéma comptable' },
];

const tabIcon = {
    base: FileText,
    constantes: Settings2,
    champs: LayoutList,
    schema: TableProperties,
};

const mode = computed(() => form.mode_frais);

const showFraisFixe = computed(() => ['fixe', 'mixte'].includes(mode.value));
const showTauxFrais = computed(() => ['taux', 'mixte'].includes(mode.value));
const showTranches = computed(() => mode.value === 'tranche');
const showMinMax = computed(() => ['manuel'].includes(mode.value));
const showTauxTaf = computed(() => mode.value !== 'gratuit');

const modeHint = computed(() => {
    switch (mode.value) {
        case 'fixe':
            return 'Les frais HT sont un montant fixe paramétré.';
        case 'tranche':
            return 'Les frais dépendent du montant de la demande — définissez les tranches ci-dessous.';
        case 'mixte':
            return 'Frais = montant fixe + (montant demande × taux %). Aucun = 0 %, sinon 10 % ou 18 %.';
        case 'taux':
            return 'Les frais HT = montant demande × taux %. Aucun = 0 %, sinon 10 % ou 18 %.';
        case 'manuel':
            return 'Les frais sont saisis à l’opération (éventuellement bornés par min / plafond).';
        case 'gratuit':
            return 'Aucun frais ni TAF — opération gratuite.';
        default:
            return '';
    }
});

const syncTauxFraisFromTaf = (taf: string | number | null | undefined) => {
    const n = String(taf ?? '0');
    if (n === '0' || n === '') {
        form.taux_frais = '0';
        return;
    }
    if (n === '10' || n === '18') {
        form.taux_frais = n;
    }
};

watch(
    () => form.taux_taf,
    (v) => {
        if (showTauxFrais.value) {
            syncTauxFraisFromTaf(v);
        }
    },
);

if (showTauxFrais.value) {
    syncTauxFraisFromTaf(form.taux_taf);
}
</script>

<template>
    <Head :title="editing ? `Modifier ${produit?.code}` : 'Nouveau produit divers'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form
            class="relative min-h-[calc(100vh-4rem)] bg-gradient-to-b from-slate-50 via-white to-white"
            @submit.prevent="submit"
        >
            <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <div
                class="sticky top-0 z-10 -mx-1 rounded-2xl border border-slate-200/80 bg-white/90 px-4 py-4 shadow-sm ring-1 ring-slate-900/5 backdrop-blur supports-[backdrop-filter]:bg-white/80 sm:px-5"
            >
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-semibold tracking-[0.18em] text-primary uppercase">
                            Produits divers
                        </p>
                        <h1 class="mt-1 truncate text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                            {{ form.libelle || (editing ? 'Modifier le produit' : 'Nouveau produit') }}
                        </h1>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span
                                v-if="form.code"
                                class="inline-flex rounded-md bg-slate-900 px-2 py-0.5 font-mono text-xs font-semibold text-white"
                            >
                                {{ form.code }}
                            </span>
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="statutProduitClass(form.statut)"
                            >
                                {{ labelStatutProduit(form.statut) }}
                            </span>
                            <span
                                v-if="form.devise"
                                class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 ring-1 ring-slate-200"
                            >
                                {{ form.devise }}
                            </span>
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1"
                                :class="
                                    form.actif
                                        ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                                        : 'bg-slate-100 text-slate-500 ring-slate-200'
                                "
                            >
                                {{ form.actif ? 'Actif' : 'Inactif' }}
                            </span>
                            <span
                                v-if="form.ecrans.length"
                                class="text-xs text-slate-500"
                            >
                                {{ form.ecrans.length }} écran{{ form.ecrans.length > 1 ? 's' : '' }}
                            </span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Button as-child type="button" variant="outline" class="h-11 bg-white">
                            <Link href="/pod/produits">Annuler</Link>
                        </Button>
                        <Button type="submit" class="h-11 px-5" :disabled="form.processing">
                            {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
                        </Button>
                    </div>
                </div>

                <div v-if="selectedEcran" class="mt-4 space-y-3">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-slate-900"
                        @click="closeEcran"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Retour aux écrans
                    </button>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-md bg-slate-900 px-2 py-0.5 font-mono text-xs font-semibold text-white">
                            {{ selectedEcran.code || 'ÉCRAN' }}
                        </span>
                        <p class="text-sm font-semibold text-slate-900">
                            {{ selectedEcran.libelle || 'Sans nom' }}
                        </p>
                    </div>
                    <nav class="overflow-x-auto">
                        <div class="inline-flex min-w-full gap-1 rounded-xl bg-slate-100/80 p-1 sm:min-w-0">
                            <button
                                v-for="tab in tabs"
                                :key="tab.id"
                                type="button"
                                class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium whitespace-nowrap transition-all"
                                :class="
                                    activeTab === tab.id
                                        ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200'
                                        : 'text-slate-500 hover:text-slate-800'
                                "
                                @click="activeTab = tab.id"
                            >
                                <component
                                    :is="tabIcon[tab.id]"
                                    class="h-4 w-4"
                                    :class="activeTab === tab.id ? 'text-primary' : ''"
                                />
                                {{ tab.label }}
                            </button>
                        </div>
                    </nav>
                </div>
            </div>

            <!-- Vue produit : identité + liste des écrans -->
            <div v-if="!selectedEcran" class="space-y-6">
            <section :class="sectionShell">
                <div :class="sectionHead">
                    <div>
                        <h2 :class="sectionTitle">Informations du produit</h2>
                        <p :class="sectionSub">Code, nom, statut et devise — visibles en permanence.</p>
                    </div>
                </div>
                <div :class="sectionBody">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div :class="fieldClass">
                        <Label>Code produit *</Label>
                        <Input v-model="form.code" class="h-11 font-mono" required />
                        <p v-if="fieldError('code')" class="text-xs text-destructive">{{ fieldError('code') }}</p>
                    </div>
                    <div :class="[fieldClass, 'sm:col-span-2 xl:col-span-2']">
                        <Label>Libellé *</Label>
                        <Input v-model="form.libelle" class="h-11" required />
                        <p v-if="fieldError('libelle')" class="text-xs text-destructive">{{ fieldError('libelle') }}</p>
                    </div>
                    <div :class="fieldClass">
                        <Label>Type d’opération</Label>
                        <Input v-model="form.type_operation" class="h-11" />
                    </div>
                    <div :class="fieldClass">
                        <Label>Devise</Label>
                        <Input v-model="form.devise" class="h-11" />
                    </div>
                    <div :class="fieldClass">
                        <Label>Initiateur</Label>
                        <select v-model="form.initiateur" :class="selectClass">
                            <option v-for="i in initiateurs" :key="i" :value="i">{{ i }}</option>
                        </select>
                    </div>
                    <div :class="fieldClass">
                        <Label>Validateur</Label>
                        <Input v-model="form.validateur" class="h-11" placeholder="Profil habilité" />
                    </div>
                    <div :class="fieldClass">
                        <Label>Statut</Label>
                        <select v-model="form.statut" :class="selectClass">
                            <option v-for="s in statuts" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2 pt-7">
                        <input id="actif" v-model="form.actif" type="checkbox" class="size-4 rounded border" />
                        <Label for="actif">Produit actif</Label>
                    </div>
                </div>
                </div>
            </section>

            <section :class="sectionShell">
                <div :class="sectionHead">
                    <div>
                        <h2 :class="sectionTitle">Écrans du produit</h2>
                        <p :class="sectionSub">
                            Ouvrez un écran pour accéder à Base, Constantes, Champs et Schéma comptable.
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Button type="button" size="sm" variant="outline" class="bg-white" @click="applyEcranTemplates">
                            Modèles CDC
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            @click="
                                form.ecrans.push(emptyEcran());
                                openEcran(form.ecrans.length - 1);
                            "
                        >
                            <Plus class="mr-1 h-4 w-4" /> Écran
                        </Button>
                    </div>
                </div>
                <div :class="sectionBody">
                <p v-if="fieldError('ecrans')" class="text-xs text-destructive">{{ fieldError('ecrans') }}</p>

                <div v-if="form.ecrans.length" class="overflow-hidden rounded-xl border border-slate-200">
                    <div
                        class="hidden grid-cols-[7rem_minmax(10rem,1.4fr)_6rem_minmax(8rem,1fr)_5rem_auto] gap-3 border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-[11px] font-semibold tracking-wide text-slate-500 uppercase lg:grid"
                    >
                        <span>Code</span>
                        <span>Nom</span>
                        <span>Activation</span>
                        <span>Profils</span>
                        <span>Champs</span>
                        <span class="text-right">Actions</span>
                    </div>
                    <div
                        v-for="(e, index) in form.ecrans"
                        :key="index"
                        class="grid grid-cols-1 gap-2 border-b border-slate-100 px-4 py-3 last:border-0 lg:grid-cols-[7rem_minmax(10rem,1.4fr)_6rem_minmax(8rem,1fr)_5rem_auto] lg:items-center"
                    >
                        <button type="button" class="text-left" @click="openEcran(index)">
                            <span class="font-mono text-xs font-semibold text-slate-800">{{ e.code || '—' }}</span>
                        </button>
                        <button type="button" class="text-left" @click="openEcran(index)">
                            <span class="text-sm font-medium text-slate-900 hover:text-primary">
                                {{ e.libelle || 'Sans nom' }}
                            </span>
                        </button>
                        <span>
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium ring-1"
                                :class="
                                    e.actif
                                        ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                                        : 'bg-slate-100 text-slate-500 ring-slate-200'
                                "
                            >
                                {{ e.actif ? 'Activé' : 'Inactif' }}
                            </span>
                        </span>
                        <span class="truncate text-xs text-slate-500">
                            {{ e.profils.length ? e.profils.map(profilLabel).join(', ') : 'Tous profils' }}
                        </span>
                        <span class="text-xs text-slate-500">{{ champsCountForEcran(e.code) }}</span>
                        <span class="flex justify-end gap-1">
                            <Button type="button" size="sm" variant="outline" class="h-8 bg-white" @click="openEcran(index)">
                                Ouvrir
                            </Button>
                            <Button type="button" variant="ghost" size="icon" class="h-8 w-8" @click="form.ecrans.splice(index, 1)">
                                <Trash2 class="h-4 w-4 text-destructive" />
                            </Button>
                        </span>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-xl border border-dashed border-slate-300 bg-slate-50/50 px-4 py-8 text-center"
                >
                    <p class="text-sm font-medium text-slate-700">Aucun écran sur ce produit</p>
                    <p class="mt-1 text-sm text-slate-500">
                        Les onglets Base / Constantes / Champs / Schéma s’ouvrent dans chaque écran.
                    </p>
                    <div class="mt-4 flex justify-center gap-2">
                        <Button type="button" size="sm" variant="outline" class="bg-white" @click="applyEcranTemplates">
                            Modèles CDC
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            @click="
                                form.ecrans.push(emptyEcran());
                                openEcran(0);
                            "
                        >
                            <Plus class="mr-1 h-4 w-4" /> Écran
                        </Button>
                    </div>
                </div>
                </div>
            </section>
            </div>

            <!-- Vue écran : onglets -->
            <div v-else class="space-y-6">
            <div v-show="activeTab === 'base'" class="space-y-6">
            <section :class="sectionShell">
                <div :class="sectionHead">
                    <div>
                        <h2 :class="sectionTitle">Informations de base de l’écran</h2>
                        <p :class="sectionSub">Code, nom, activation et profils.</p>
                    </div>
                </div>
                <div :class="sectionBody">
                    <div class="grid gap-3 lg:grid-cols-[8rem_1fr_1.2fr_auto]">
                        <div :class="fieldClass">
                            <Label>Code *</Label>
                            <Input v-model="selectedEcran.code" class="h-11 font-mono uppercase" placeholder="ECR01" />
                        </div>
                        <div :class="fieldClass">
                            <Label>Nom *</Label>
                            <Input v-model="selectedEcran.libelle" class="h-11" placeholder="Informations de base" />
                        </div>
                        <div :class="fieldClass">
                            <Label>Description</Label>
                            <Input v-model="selectedEcran.description" class="h-11" />
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="selectedEcran.actif" type="checkbox" class="size-4 rounded border" />
                                Actif
                            </label>
                        </div>
                    </div>
                    <div>
                        <p class="mb-2 text-xs font-medium text-slate-500">Profils autorisés</p>
                        <div class="flex flex-wrap gap-3">
                            <label
                                v-for="p in profilsEcran || []"
                                :key="p.key"
                                class="flex items-center gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border"
                                    :checked="selectedEcran.profils.includes(p.key)"
                                    @change="
                                        () => {
                                            const i = selectedEcran.profils.indexOf(p.key);
                                            if (i >= 0) selectedEcran.profils.splice(i, 1);
                                            else selectedEcran.profils.push(p.key);
                                        }
                                    "
                                />
                                {{ p.label }}
                            </label>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            Vide = accessible à tous les utilisateurs du module.
                        </p>
                    </div>
                </div>
            </section>
            </div>

            <div v-show="activeTab === 'constantes'" class="space-y-6">
            <section :class="sectionShell">
                <div :class="sectionHead">
                    <div>
                        <h2 :class="sectionTitle">Constantes du produit</h2>
                        <p :class="sectionSub">Modes : défaut, champ, schéma comptable, script.</p>
                    </div>
                    <Button type="button" size="sm" @click="form.constantes.push(emptyConstante())">
                        <Plus class="mr-1 h-4 w-4" /> Constante
                    </Button>
                </div>
                <div :class="sectionBody">
                <div
                    v-for="(c, index) in form.constantes"
                    :key="index"
                    class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50/40 p-4 lg:grid-cols-[7rem_1fr_8rem_9rem_1fr_auto]"
                >
                    <div class="space-y-1">
                        <Label>Code</Label>
                        <Input v-model="c.code" class="font-mono uppercase" placeholder="C001" />
                    </div>
                    <div class="space-y-1">
                        <Label>Libellé</Label>
                        <Input v-model="c.libelle" />
                    </div>
                    <div class="space-y-1">
                        <Label>Type</Label>
                        <select v-model="c.type" :class="selectClass">
                            <option v-for="t in typesConstante || []" :key="t" :value="t">{{ t }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <Label>Mode</Label>
                        <select v-model="c.mode" :class="selectClass">
                            <option v-for="m in modesConstante || []" :key="m" :value="m">
                                {{ modeConstanteLabels[m] || m }}
                            </option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <Label>Référence / valeur</Label>
                        <Input v-model="c.valeur_reference" :placeholder="refPlaceholder(c.mode)" />
                    </div>
                    <div class="flex items-end justify-end gap-1">
                        <label class="mr-2 flex items-center gap-2 pb-2 text-sm">
                            <input v-model="c.obligatoire" type="checkbox" class="size-4 rounded border" />
                            Oblig.
                        </label>
                        <Button type="button" variant="ghost" size="icon" @click="form.constantes.splice(index, 1)">
                            <Trash2 class="h-4 w-4 text-destructive" />
                        </Button>
                    </div>
                </div>
                <p v-if="form.constantes.length === 0" class="text-sm text-slate-500">Aucune constante.</p>
                </div>
            </section>

            <section :class="sectionShell">
                <div :class="sectionHead">
                    <div>
                        <h2 :class="sectionTitle">Scripts (whitelist)</h2>
                        <p :class="sectionSub">Moteurs sécurisés pour alimenter les constantes.</p>
                    </div>
                    <Button type="button" size="sm" @click="form.scripts.push(emptyScript())">
                        <Plus class="mr-1 h-4 w-4" /> Script
                    </Button>
                </div>
                <div :class="sectionBody">
                <div
                    v-for="(s, index) in form.scripts"
                    :key="index"
                    class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50/40 p-4 lg:grid-cols-[8rem_1fr_12rem_1fr_auto]"
                >
                    <div class="space-y-1">
                        <Label>Code</Label>
                        <Input v-model="s.code" class="font-mono uppercase" placeholder="SCRIPT_FRAIS" />
                    </div>
                    <div class="space-y-1">
                        <Label>Libellé</Label>
                        <Input v-model="s.libelle" />
                    </div>
                    <div class="space-y-1">
                        <Label>Moteur</Label>
                        <select v-model="s.moteur" :class="selectClass">
                            <option v-for="m in moteursScript || []" :key="m.key" :value="m.key">
                                {{ m.label }}
                            </option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <Label>Paramètres</Label>
                        <Input
                            v-if="s.moteur === 'valeur_fixe'"
                            v-model="s.parametres.valeur"
                            placeholder="valeur"
                        />
                        <Input
                            v-else-if="s.moteur === 'copie_champ'"
                            v-model="s.parametres.champ"
                            placeholder="CODE_CHAMP"
                            class="font-mono uppercase"
                        />
                        <Input
                            v-else-if="s.moteur === 'frais_taux_demande' || s.moteur === 'taf_sur_frais'"
                            v-model="s.parametres.taux"
                            type="number"
                            placeholder="taux % (optionnel)"
                        />
                        <p v-else class="pt-2 text-xs text-muted-foreground">Aucun paramètre</p>
                    </div>
                    <div class="flex items-end justify-end gap-1">
                        <label class="mr-2 flex items-center gap-2 pb-2 text-sm">
                            <input v-model="s.actif" type="checkbox" class="size-4 rounded border" />
                            Actif
                        </label>
                        <Button type="button" variant="ghost" size="icon" @click="form.scripts.splice(index, 1)">
                            <Trash2 class="h-4 w-4 text-destructive" />
                        </Button>
                    </div>
                </div>
                <p v-if="form.scripts.length === 0" class="text-sm text-slate-500">Aucun script.</p>
                </div>
            </section>
            </div>

            <div v-show="activeTab === 'champs'" class="space-y-6">
            <section :class="sectionShell">
                <div :class="sectionHead">
                    <div>
                        <h2 :class="sectionTitle">Champs de l’écran</h2>
                        <p :class="sectionSub">
                            Champs affichés sur « {{ selectedEcran?.libelle || selectedEcran?.code }} ».
                        </p>
                    </div>
                    <Button type="button" size="sm" @click="addChampToSelectedEcran">
                        <Plus class="mr-1 h-4 w-4" /> Ajouter
                    </Button>
                </div>
                <div :class="sectionBody">

                <p v-if="fieldError('champs')" class="text-xs text-destructive">{{ fieldError('champs') }}</p>

                <div
                    v-for="({ champ: c, index }, localIndex) in champsOfSelectedEcran"
                    :key="index"
                    class="space-y-4 rounded-xl border border-slate-200 bg-slate-50/40 p-4"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase">
                            Champ {{ localIndex + 1 }}
                            <span v-if="c.code" class="ml-2 font-mono normal-case tracking-normal text-slate-800">{{ c.code }}</span>
                        </p>
                        <div class="flex items-center gap-1">
                            <Button type="button" variant="ghost" size="icon" :disabled="index === 0" @click="moveChamp(index, -1)">
                                <ChevronUp class="h-4 w-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :disabled="index === form.champs.length - 1"
                                @click="moveChamp(index, 1)"
                            >
                                <ChevronDown class="h-4 w-4" />
                            </Button>
                            <Button type="button" variant="ghost" size="icon" @click="form.champs.splice(index, 1)">
                                <Trash2 class="h-4 w-4 text-destructive" />
                            </Button>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <div class="space-y-1">
                            <Label>Obligation</Label>
                            <select
                                :class="selectClass"
                                :value="c.obligatoire ? 'obligatoire' : 'facultatif'"
                                @change="c.obligatoire = ($event.target as HTMLSelectElement).value === 'obligatoire'"
                            >
                                <option value="obligatoire">Obligatoire</option>
                                <option value="facultatif">Facultatif</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <Label>Type</Label>
                            <select v-model="c.type" :class="selectClass">
                                <option v-for="t in typesChamp" :key="t" :value="t">
                                    {{ typeLabels[t] || t }}
                                </option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <Label>Taille</Label>
                            <Input
                                v-if="c.type === 'texte'"
                                v-model="c.options.max_length"
                                type="number"
                                min="1"
                                placeholder="ex. 12"
                            />
                            <Input
                                v-else-if="c.type === 'numerique'"
                                v-model="c.options.decimales"
                                type="number"
                                min="0"
                                max="6"
                                placeholder="Décimales"
                            />
                            <Input
                                v-else
                                value="—"
                                disabled
                                class="h-10 opacity-60"
                            />
                        </div>
                        <div class="space-y-1">
                            <Label>Variable *</Label>
                            <Input
                                v-model="c.code"
                                placeholder="customer_account_number"
                                class="font-mono text-sm"
                            />
                            <p v-if="fieldError(`champs.${index}.code`)" class="text-xs text-destructive">
                                {{ fieldError(`champs.${index}.code`) }}
                            </p>
                        </div>
                        <div class="space-y-1">
                            <Label>Texte à afficher *</Label>
                            <Input v-model="c.libelle" placeholder="N° de compte du client" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="space-y-1">
                            <Label>Écran</Label>
                            <Input :model-value="selectedEcran?.code || ''" class="h-11 font-mono" disabled />
                        </div>
                        <div class="space-y-1">
                            <Label>Valeur par défaut</Label>
                            <Input v-model="c.valeur_defaut" />
                        </div>
                        <div class="space-y-1">
                            <Label>Affichage</Label>
                            <select
                                :class="selectClass"
                                :value="c.visible === false ? 'masque' : c.gris ? 'lecture' : 'saisie'"
                                @change="
                                    () => {
                                        const v = ($event.target as HTMLSelectElement).value;
                                        c.visible = v !== 'masque';
                                        c.gris = v === 'lecture';
                                    }
                                "
                            >
                                <option value="saisie">Saisie</option>
                                <option value="lecture">Lecture seule</option>
                                <option value="masque">Masqué</option>
                            </select>
                        </div>
                        <div
                            v-if="c.type === 'numerique'"
                            class="grid grid-cols-2 gap-2"
                        >
                            <div class="space-y-1">
                                <Label>Min</Label>
                                <Input v-model="c.options.min" type="number" />
                            </div>
                            <div class="space-y-1">
                                <Label>Max</Label>
                                <Input v-model="c.options.max" type="number" />
                            </div>
                        </div>
                    </div>

                    <div v-if="c.type === 'liste'" class="space-y-2 rounded-lg border border-dashed border-border p-3">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium">Valeurs de la liste</p>
                            <Button type="button" size="sm" variant="outline" @click="addListeValeur(c)">
                                <Plus class="mr-1 h-3.5 w-3.5" /> Valeur
                            </Button>
                        </div>
                        <div
                            v-for="(v, vi) in c.options.valeurs"
                            :key="vi"
                            class="grid gap-2 sm:grid-cols-[1fr_1.4fr_auto_auto]"
                        >
                            <Input v-model="v.code" placeholder="Code" class="font-mono" />
                            <Input v-model="v.libelle" placeholder="Libellé" />
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="v.actif" type="checkbox" class="size-4 rounded border" />
                                Actif
                            </label>
                            <Button type="button" variant="ghost" size="icon" @click="c.options.valeurs!.splice(vi, 1)">
                                <Trash2 class="h-4 w-4 text-destructive" />
                            </Button>
                        </div>
                        <p v-if="!(c.options.valeurs?.length)" class="text-sm text-muted-foreground">
                            Aucune valeur — ajoutez au moins une option.
                        </p>
                    </div>

                    <div v-else-if="c.type === 'table'" class="space-y-3 rounded-lg border border-dashed border-border p-3">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="space-y-1">
                                <Label>Table source *</Label>
                                <select v-model="c.options.table_code" :class="selectClass">
                                    <option value="">— Choisir —</option>
                                    <option v-for="t in tablesSources || []" :key="t.code" :value="t.code">
                                        {{ t.code }} — {{ t.libelle }}
                                    </option>
                                </select>
                                <p v-if="!(tablesSources || []).length" class="text-xs text-muted-foreground">
                                    Aucune table —
                                    <Link href="/pod/tables/create" class="underline">en créer une</Link>
                                </p>
                            </div>
                            <div class="space-y-1">
                                <Label>Colonne valeur *</Label>
                                <select v-model="c.options.colonne_valeur" :class="selectClass">
                                    <option value="">—</option>
                                    <option
                                        v-for="col in columnsForTable(c.options.table_code)"
                                        :key="col.code"
                                        :value="col.code"
                                    >
                                        {{ col.code }}
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <Label>Colonne libellé *</Label>
                                <select v-model="c.options.colonne_libelle" :class="selectClass">
                                    <option value="">—</option>
                                    <option
                                        v-for="col in columnsForTable(c.options.table_code)"
                                        :key="col.code"
                                        :value="col.code"
                                    >
                                        {{ col.code }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div v-if="columnsForTable(c.options.table_code).length" class="space-y-2">
                            <p class="text-xs font-medium text-muted-foreground">Colonnes désactivées (ignorées)</p>
                            <div class="flex flex-wrap gap-3">
                                <label
                                    v-for="col in columnsForTable(c.options.table_code)"
                                    :key="col.code"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border"
                                        :checked="(c.options.colonnes_desactivees || []).includes(col.code)"
                                        @change="toggleColonneDesactivee(c, col.code)"
                                    />
                                    <span class="font-mono text-xs">{{ col.code }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <p v-if="champsOfSelectedEcran.length === 0" class="text-sm text-slate-500">
                    Aucun champ sur cet écran. Cliquez sur Ajouter.
                </p>

                <div
                    v-if="champsOfSelectedEcran.some(({ champ: c }) => c.visible !== false && c.libelle)"
                    class="rounded-xl border border-primary/15 bg-primary/5 p-4"
                >
                    <p class="mb-3 text-[11px] font-semibold tracking-[0.14em] text-primary uppercase">
                        Aperçu de l’écran
                    </p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <DynamicFieldInput
                            v-for="({ champ: c }) in champsOfSelectedEcran.filter(({ champ: c }) => c.visible !== false && c.libelle)"
                            :key="c.code || c.libelle"
                            :champ="c"
                            :model-value="previewValues[c.code] ?? c.valeur_defaut ?? null"
                            @update:model-value="(v) => (previewValues[c.code] = v)"
                        />
                    </div>
                </div>
                </div>
            </section>
            </div>

            <div v-show="activeTab === 'schema'" class="space-y-6">
            <section :class="sectionShell">
                <div :class="sectionHead">
                    <div>
                        <h2 :class="sectionTitle">Règles de frais</h2>
                        <p :class="sectionSub">Choisissez un mode — seuls les champs utiles s’affichent.</p>
                    </div>
                </div>
                <div :class="sectionBody">
                    <div class="max-w-sm" :class="fieldClass">
                        <Label>Mode de frais</Label>
                        <select v-model="form.mode_frais" :class="selectClass">
                            <option v-for="m in modesFrais" :key="m" :value="m">
                                {{ labelModeFrais(m) }}
                            </option>
                        </select>
                    </div>

                    <div
                        v-if="showFraisFixe || showTauxFrais || showTauxTaf || showMinMax"
                        class="grid gap-4 rounded-xl border border-slate-200 bg-slate-50/50 p-4 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div v-if="showFraisFixe" :class="fieldClass">
                            <Label>Frais fixe</Label>
                            <Input v-model="form.frais_fixe" class="h-11" type="number" step="0.01" min="0" />
                        </div>
                        <div v-if="showTauxFrais" :class="fieldClass">
                            <Label>Taux frais (%)</Label>
                            <Input v-model="form.taux_frais" class="h-11" type="number" step="0.01" min="0" />
                            <p class="text-[11px] text-slate-500">Aligné sur TAF / taxe : Aucun = 0, sinon 10 ou 18.</p>
                        </div>
                        <div v-if="showTauxTaf" :class="fieldClass">
                            <Label>Taux TAF / taxe</Label>
                            <select
                                v-model="form.taux_taf"
                                :class="selectClass"
                                @change="syncTauxFraisFromTaf(form.taux_taf)"
                            >
                                <option value="0">Aucun</option>
                                <option value="10">TAF 10 %</option>
                                <option value="18">Taxe 18 %</option>
                            </select>
                        </div>
                        <div v-if="showMinMax" :class="fieldClass">
                            <Label>Minimum frais</Label>
                            <Input v-model="form.frais_min" class="h-11" type="number" step="0.01" min="0" />
                        </div>
                        <div v-if="showMinMax" :class="fieldClass">
                            <Label>Plafond frais</Label>
                            <Input v-model="form.frais_max" class="h-11" type="number" step="0.01" min="0" />
                        </div>
                    </div>

                    <p
                        v-if="modeHint"
                        class="rounded-xl border border-primary/10 bg-primary/5 px-3.5 py-2.5 text-sm text-slate-700"
                    >
                        {{ modeHint }}
                    </p>

                    <div :class="fieldClass">
                        <Label>Notes</Label>
                        <textarea
                            v-model="form.notes"
                            rows="2"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm shadow-sm"
                            placeholder="Commentaire interne (optionnel)"
                        />
                    </div>
                </div>
            </section>

            <section
                v-if="showTranches"
                :class="sectionShell"
            >
                <div :class="sectionHead">
                    <div>
                        <h2 :class="sectionTitle">Tranches de montant</h2>
                        <p :class="sectionSub">Intervalle de montant demande → frais associés.</p>
                    </div>
                    <Button type="button" size="sm" @click="form.tranches.push(emptyTranche())">
                        <Plus class="mr-1 h-4 w-4" /> Ajouter
                    </Button>
                </div>
                <div :class="sectionBody">
                    <div
                        v-if="form.tranches.length"
                        class="hidden grid-cols-[2fr_1fr_1fr_1fr_1fr_2.5rem] gap-2 px-1 text-xs font-medium text-slate-500 sm:grid"
                    >
                        <span>Libellé</span>
                        <span>Min</span>
                        <span>Max</span>
                        <span>Frais</span>
                        <span>Taux %</span>
                        <span />
                    </div>
                    <div
                        v-for="(t, index) in form.tranches"
                        :key="index"
                        class="grid gap-2 rounded-xl border border-slate-200 bg-slate-50/40 p-3 sm:grid-cols-[2fr_1fr_1fr_1fr_1fr_2.5rem] sm:items-center"
                    >
                        <Input v-model="t.libelle" class="h-11" placeholder="Libellé" />
                        <Input v-model="t.montant_min" class="h-11" type="number" placeholder="Min" />
                        <Input v-model="t.montant_max" class="h-11" type="number" placeholder="Max" />
                        <Input v-model="t.frais_fixe" class="h-11" type="number" placeholder="Frais" />
                        <Input v-model="t.taux" class="h-11" type="number" placeholder="Taux %" />
                        <Button type="button" variant="ghost" size="icon" @click="form.tranches.splice(index, 1)">
                            <Trash2 class="h-4 w-4 text-destructive" />
                        </Button>
                    </div>
                    <p v-if="form.tranches.length === 0" class="text-sm text-slate-500">
                        Aucune tranche — ajoutez-en au moins une.
                    </p>
                </div>
            </section>

            <section :class="sectionShell">
                <div :class="sectionHead">
                    <div>
                        <h2 :class="sectionTitle">Comptes & libellés d’écriture</h2>
                        <p :class="sectionSub">Comptes comptables et libellés générés.</p>
                    </div>
                </div>
                <div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-2">
                    <div class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/40 p-4">
                        <p class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase">Comptes</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div :class="fieldClass">
                                <Label>Compte produit</Label>
                                <Input v-model="form.compte_produit" class="h-11 font-mono" />
                            </div>
                            <div :class="fieldClass">
                                <Label>Compte TAF</Label>
                                <Input v-model="form.compte_taf" class="h-11 font-mono" />
                            </div>
                            <div :class="fieldClass">
                                <Label>Masque compte client</Label>
                                <Input v-model="form.compte_client_mask" class="h-11 font-mono" />
                            </div>
                            <div :class="fieldClass">
                                <Label>Code TAF</Label>
                                <Input v-model="form.code_taf" class="h-11 font-mono" />
                            </div>
                        </div>
                    </div>
                    <div class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/40 p-4">
                        <p class="text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase">Libellés</p>
                        <div class="space-y-3">
                            <div :class="fieldClass">
                                <Label>Libellé écriture produit</Label>
                                <Input v-model="form.libelle_ecriture_produit" class="h-11" />
                            </div>
                            <div :class="fieldClass">
                                <Label>Libellé écriture TAF</Label>
                                <Input v-model="form.libelle_ecriture_taf" class="h-11" />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section :class="sectionShell">
                <div :class="sectionHead">
                    <div>
                        <h2 :class="sectionTitle">Lignes du schéma comptable</h2>
                        <p :class="sectionSub">Écritures générées à la saisie d’opération.</p>
                    </div>
                    <Button type="button" size="sm" @click="form.lignes.push(emptyLigne())">
                        <Plus class="mr-1 h-4 w-4" /> Ligne
                    </Button>
                </div>
                <div :class="sectionBody">
                    <div
                        v-if="form.lignes.length"
                        class="hidden grid-cols-[7rem_10rem_minmax(12rem,2fr)_9rem_minmax(9rem,1fr)_2.5rem] gap-2 px-1 text-xs font-medium text-slate-500 lg:grid"
                    >
                        <span>Sens</span>
                        <span>Compte</span>
                        <span>Libellé</span>
                        <span>Nature</span>
                        <span>Type montant</span>
                        <span />
                    </div>
                    <div
                        v-for="(l, index) in form.lignes"
                        :key="index"
                        class="grid gap-2 rounded-xl border border-slate-200 bg-slate-50/40 p-3 lg:grid-cols-[7rem_10rem_minmax(12rem,2fr)_9rem_minmax(9rem,1fr)_2.5rem] lg:items-center"
                    >
                        <select v-model="l.sens" :class="selectClass">
                            <option value="D">Débit</option>
                            <option value="C">Crédit</option>
                        </select>
                        <Input v-model="l.compte" class="h-11 font-mono" placeholder="Compte" />
                        <Input v-model="l.libelle_ecriture" class="h-11" placeholder="Libellé" />
                        <select v-model="l.nature_compte" :class="selectClass">
                            <option v-for="n in naturesCompte" :key="n" :value="n">{{ n }}</option>
                        </select>
                        <select v-model="l.type_montant" :class="selectClass">
                            <option v-for="t in typesMontant" :key="t" :value="t">{{ t }}</option>
                        </select>
                        <Button type="button" variant="ghost" size="icon" @click="form.lignes.splice(index, 1)">
                            <Trash2 class="h-4 w-4 text-destructive" />
                        </Button>
                    </div>
                    <p v-if="form.lignes.length === 0" class="text-sm text-slate-500">
                        Aucune ligne — ajoutez le schéma débit / crédit.
                    </p>
                </div>
            </section>
            </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
                <Button as-child type="button" variant="outline" class="h-11 bg-white">
                    <Link href="/pod/produits">Annuler</Link>
                </Button>
                <Button type="submit" class="h-11 px-5" :disabled="form.processing">
                    {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
                </Button>
            </div>
            </div>
        </form>
    </AppLayout>
</template>
