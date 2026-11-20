<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import OdConfirmDialog from '@/components/OdConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import {
    AlertTriangle,
    ArrowRight,
    BookOpen,
    Calculator,
    Code2,
    History,
    LayoutList,
    Pencil,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { labelModeFrais, labelStatutProduit, modeConstanteLabels, statutProduitClass } from '@/lib/podLabels';

type Champ = {
    code: string;
    libelle: string;
    type: string;
    obligatoire: boolean;
    visible: boolean;
    gris: boolean;
    valeur_defaut: string | null;
    ecran_code?: string | null;
    options?: {
        table_code?: string;
        colonne_valeur?: string;
        colonne_libelle?: string;
        recherche?: string | null;
        valeurs?: Array<{ code: string; libelle: string; actif?: boolean }>;
        min?: number | string | null;
        max?: number | string | null;
    };
};

type Produit = {
    id: number;
    code: string;
    libelle: string;
    type_operation: string | null;
    devise: string;
    code_taf: string | null;
    libelle_ecriture_produit: string | null;
    libelle_ecriture_taf: string | null;
    compte_produit: string | null;
    compte_taf: string | null;
    compte_client_mask: string | null;
    mode_frais: string;
    frais_fixe: string | number | null;
    taux_frais: string | number | null;
    frais_min: string | number | null;
    frais_max: string | number | null;
    taux_taf: string | number | null;
    taux_taf_effectif: number;
    bareme_champ?: string | null;
    taxe_champ?: string | null;
    base_calcul: string | null;
    notes: string | null;
    statut: string;
    actif: boolean;
    created_by_name?: string | null;
    updated_by_name?: string | null;
    tranches: Array<{
        libelle: string | null;
        critere?: string | null;
        montant_min: string | number;
        montant_max: string | number | null;
        frais_fixe: string | number | null;
        taux: string | number | null;
    }>;
    lignes: Array<{
        sens: string;
        compte: string;
        libelle_ecriture: string | null;
        nature_compte: string;
        type_montant: string;
    }>;
    champs: Champ[];
    ecrans: Array<{
        code: string;
        libelle: string;
        description: string | null;
        profils: string[];
        actif: boolean;
    }>;
    constantes: Array<{
        code: string;
        libelle: string;
        type: string;
        mode: string;
        valeur_reference: string | null;
        obligatoire: boolean;
    }>;
    scripts: Array<{
        code: string;
        libelle: string;
        moteur: string;
        parametres: Record<string, unknown>;
        actif: boolean;
    }>;
};

const props = defineProps<{
    produit: Produit;
    tauxTafDefaut: number;
    profilsEcran?: Array<{ key: string; label: string }>;
    initiateurs?: Array<{ slug: string; nom: string }>;
    audits?: Array<{
        id: number;
        action: string;
        resume: string | null;
        user_name: string | null;
        created_at: string | null;
    }>;
}>();

const breadcrumbs = [
    { title: 'Produits divers', href: '/pod/produits' },
    { title: props.produit.code, href: `/pod/produits/${props.produit.id}` },
];

const statutForm = useForm({ statut: props.produit.statut });
const changeStatut = () => {
    statutForm.post(`/pod/produits/${props.produit.id}/statut`, { preserveScroll: true });
};

const confirmSuppression = ref(false);
const suppressionEnCours = ref(false);
const supprimer = () => {
    suppressionEnCours.value = true;
    router.delete(`/pod/produits/${props.produit.id}`, {
        onFinish: () => {
            suppressionEnCours.value = false;
            confirmSuppression.value = false;
        },
    });
};

type Onglet = 'ecrans' | 'frais' | 'compta' | 'scripts' | 'historique';
const onglet = ref<Onglet>('ecrans');
const onglets = computed(() => [
    { id: 'ecrans' as const, label: 'Écrans et champs', icon: LayoutList, count: props.produit.champs.length },
    { id: 'frais' as const, label: 'Frais', icon: Calculator, count: props.produit.tranches.length || null },
    { id: 'compta' as const, label: 'Comptabilité', icon: BookOpen, count: props.produit.lignes.length || null },
    {
        id: 'scripts' as const,
        label: 'Scripts et constantes',
        icon: Code2,
        count: props.produit.scripts.length + props.produit.constantes.length || null,
    },
    { id: 'historique' as const, label: 'Historique', icon: History, count: props.audits?.length || null },
]);

const fmt = (v: string | number | null | undefined) => {
    if (v === null || v === undefined || v === '') return '—';
    return Number(v).toLocaleString('fr-FR');
};

const fmtDate = (v: string | null) => (v ? new Date(v).toLocaleString('fr-FR') : '—');

const roleNom = (slug: string) =>
    props.initiateurs?.find((role) => role.slug === slug)?.nom
    || props.profilsEcran?.find((profil) => profil.key === slug)?.label
    || slug;

const champParCode = (code?: string | null) => props.produit.champs.find((c) => c.code === code);

const libelleValeur = (champCode: string | null | undefined, valeur: string | null | undefined) => {
    if (!valeur) return 'Tous';
    return champParCode(champCode)?.options?.valeurs?.find((v) => v.code === valeur)?.libelle || valeur;
};

const taxe = computed(() => {
    if (props.produit.taxe_champ) {
        return { titre: 'Au choix de l’agent', detail: `Champ « ${champParCode(props.produit.taxe_champ)?.libelle ?? props.produit.taxe_champ} »` };
    }
    const n = Number(props.produit.taux_taf_effectif ?? 0);
    const titre = n === 10 ? 'TAF 10 %' : n === 18 ? 'TVA 18 %' : n ? `${n} %` : 'Aucune';
    return {
        titre,
        detail: props.produit.taux_taf != null ? 'Choisie sur le produit' : `Taux par défaut du module (${props.tauxTafDefaut} %)`,
    };
});

const fraisDetail = computed(() => {
    const p = props.produit;
    switch (p.mode_frais) {
        case 'fixe':
            return `${fmt(p.frais_fixe)} ${p.devise}`;
        case 'taux':
            return `${fmt(p.taux_frais)} % du montant demandé`;
        case 'mixte':
            return `${fmt(p.frais_fixe)} ${p.devise} + ${fmt(p.taux_frais)} %`;
        case 'tranche':
            return p.bareme_champ
                ? `${p.tranches.length} tranches, selon « ${champParCode(p.bareme_champ)?.libelle ?? p.bareme_champ} »`
                : `${p.tranches.length} tranches selon le montant demandé`;
        case 'manuel':
            return 'Saisis par l’agent';
        default:
            return 'Aucun frais';
    }
});

const ecransAvecChamps = computed(() => {
    const groupes = props.produit.ecrans.map((e, i) => ({
        ...e,
        index: i,
        initiateur: e.profils?.length ? e.profils.map(roleNom).join(', ') : 'Tous les utilisateurs',
        champs: props.produit.champs.filter((c) => c.ecran_code === e.code),
    }));
    const orphelins = props.produit.champs.filter((c) => !c.ecran_code || !props.produit.ecrans.some((e) => e.code === c.ecran_code));
    if (orphelins.length) {
        groupes.push({
            code: '—',
            libelle: 'Champs hors écran',
            description: null,
            profils: [],
            actif: true,
            index: groupes.length,
            initiateur: 'Tous les utilisateurs',
            champs: orphelins,
        });
    }
    return groupes;
});

const tranchesParBareme = computed(() => {
    const groupes = new Map<string, Produit['tranches']>();
    for (const t of props.produit.tranches) {
        const cle = t.critere || '';
        groupes.set(cle, [...(groupes.get(cle) ?? []), t]);
    }
    return [...groupes.entries()].map(([critere, tranches]) => ({
        critere,
        titre: props.produit.bareme_champ ? libelleValeur(props.produit.bareme_champ, critere) : 'Barème',
        tranches,
    }));
});

const comptePlaceholder = computed(
    () => !props.produit.compte_produit && props.produit.mode_frais !== 'gratuit',
);

const typeChampLabels: Record<string, string> = {
    texte: 'Texte',
    liste: 'Liste',
    numerique: 'Nombre',
    date: 'Date',
    table: 'Table',
    fichier: 'Pièce jointe',
};

const actionLabels: Record<string, string> = {
    created: 'Création',
    updated: 'Modification',
    statut: 'Statut',
};

const sectionShell = 'overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-900/5';
const cardTitle = 'text-[11px] font-semibold tracking-wide text-slate-500 uppercase';
</script>

<template>
    <Head :title="`Produit ${produit.code}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-full flex-1 flex-col bg-gradient-to-b from-slate-50 via-white to-white">
            <div class="flex w-full min-w-0 flex-1 flex-col gap-5 p-4 sm:p-6">
                <!-- En-tête -->
                <div
                    class="flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-slate-200/80 bg-white px-5 py-4 shadow-sm ring-1 ring-slate-900/5"
                >
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold tracking-[0.18em] text-primary uppercase">Produits divers</p>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{{ produit.libelle }}</h1>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="rounded-md bg-slate-900 px-2 py-0.5 font-mono text-xs font-semibold text-white">{{ produit.code }}</span>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statutProduitClass(produit.statut)">
                                {{ labelStatutProduit(produit.statut) }}
                            </span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 ring-1 ring-slate-200">
                                {{ produit.devise }}
                            </span>
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-medium ring-1"
                                :class="produit.actif ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-slate-100 text-slate-500 ring-slate-200'"
                            >
                                {{ produit.actif ? 'Actif' : 'Inactif' }}
                            </span>
                            <span v-if="produit.type_operation" class="text-sm text-slate-500">{{ produit.type_operation }}</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button as-child variant="outline" class="h-11 bg-white">
                            <Link :href="`/pod/produits/${produit.id}/edit`"><Pencil class="mr-2 h-4 w-4" /> Modifier</Link>
                        </Button>
                        <Button variant="destructive" class="h-11" @click="confirmSuppression = true">
                            <Trash2 class="mr-2 h-4 w-4" /> Supprimer
                        </Button>
                    </div>
                </div>

                <!-- Synthèse -->
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div :class="[sectionShell, 'p-5']">
                        <p :class="cardTitle">Statut</p>
                        <form class="mt-3 flex gap-2" @submit.prevent="changeStatut">
                            <select v-model="statutForm.statut" class="h-10 min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-3 text-sm shadow-sm">
                                <option value="brouillon">Brouillon</option>
                                <option value="valide">Validé</option>
                                <option value="production">Production</option>
                            </select>
                            <Button class="h-10" type="submit" :disabled="statutForm.processing || statutForm.statut === produit.statut">
                                Appliquer
                            </Button>
                        </form>
                        <p v-for="(err, key) in statutForm.errors" :key="key" class="mt-2 text-xs text-destructive">{{ err }}</p>
                    </div>
                    <div :class="[sectionShell, 'p-5']">
                        <p :class="cardTitle">Frais</p>
                        <p class="mt-3 text-lg font-semibold text-slate-900">{{ labelModeFrais(produit.mode_frais) }}</p>
                        <p class="mt-0.5 text-sm text-slate-500">{{ fraisDetail }}</p>
                    </div>
                    <div :class="[sectionShell, 'p-5']">
                        <p :class="cardTitle">Taxe</p>
                        <p class="mt-3 text-lg font-semibold text-slate-900">{{ taxe.titre }}</p>
                        <p class="mt-0.5 text-sm text-slate-500">{{ taxe.detail }}</p>
                    </div>
                    <div :class="[sectionShell, 'p-5']">
                        <p :class="cardTitle">Circuit</p>
                        <ol class="mt-3 flex flex-wrap items-center gap-1.5 text-sm">
                            <template v-for="(e, i) in produit.ecrans" :key="e.code">
                                <ArrowRight v-if="i > 0" class="h-3.5 w-3.5 text-slate-300" />
                                <li class="rounded-md bg-slate-100 px-2 py-0.5 font-medium text-slate-700">{{ e.libelle }}</li>
                            </template>
                            <template v-if="produit.ecrans.length <= 1">
                                <ArrowRight class="h-3.5 w-3.5 text-slate-300" />
                                <li class="rounded-md bg-slate-100 px-2 py-0.5 font-medium text-slate-700">Validation</li>
                            </template>
                        </ol>
                        <p class="mt-1.5 text-xs text-slate-500">Chaque étape est traitée par un utilisateur différent.</p>
                    </div>
                </div>

                <div
                    v-if="comptePlaceholder"
                    class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-950"
                >
                    <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                    <p>
                        Compte produit non renseigné : les frais seront crédités sur <span class="font-mono">7XXXXXXX</span>
                        et la validation finale des dossiers sera refusée.
                        <Link :href="`/pod/produits/${produit.id}/edit`" class="font-medium underline">Compléter le schéma comptable</Link>
                    </p>
                </div>

                <!-- Onglets -->
                <section :class="sectionShell">
                    <nav class="flex gap-1 overflow-x-auto border-b border-slate-100 bg-slate-50 px-3 pt-3">
                        <button
                            v-for="o in onglets"
                            :key="o.id"
                            type="button"
                            class="-mb-px inline-flex items-center gap-2 rounded-t-lg border border-b-0 px-4 py-2.5 text-sm font-medium whitespace-nowrap transition"
                            :class="onglet === o.id ? 'border-slate-200 bg-white text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-800'"
                            @click="onglet = o.id"
                        >
                            <component :is="o.icon" class="h-4 w-4" :class="onglet === o.id ? 'text-primary' : ''" />
                            {{ o.label }}
                            <span v-if="o.count" class="rounded-full bg-slate-100 px-1.5 text-xs text-slate-500">{{ o.count }}</span>
                        </button>
                    </nav>

                    <!-- Écrans et champs -->
                    <div v-show="onglet === 'ecrans'" class="space-y-5 p-5">
                        <p v-if="!ecransAvecChamps.length" class="py-6 text-center text-sm text-slate-500">
                            Aucun écran : la saisie se fait en un seul bloc.
                        </p>
                        <div v-for="e in ecransAvecChamps" :key="e.code" class="overflow-hidden rounded-xl border border-slate-200">
                            <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-semibold text-white">
                                        {{ e.index + 1 }}
                                    </span>
                                    <span class="font-semibold text-slate-900">{{ e.libelle }}</span>
                                    <span class="font-mono text-xs text-slate-400">{{ e.code }}</span>
                                    <span v-if="!e.actif" class="rounded-full bg-slate-200 px-2 text-xs text-slate-600">Inactif</span>
                                </div>
                                <span class="text-xs text-slate-500">Initiateur : <strong class="font-medium text-slate-700">{{ e.initiateur }}</strong></span>
                            </div>
                            <p v-if="e.description" class="border-t border-slate-100 px-4 py-2 text-xs text-slate-500">{{ e.description }}</p>
                            <table class="min-w-full text-sm">
                                <thead class="border-t border-slate-100 text-left text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                                    <tr>
                                        <th class="px-4 py-2">Champ</th>
                                        <th class="px-4 py-2">Type</th>
                                        <th class="px-4 py-2">Règles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="c in e.champs" :key="c.code" class="border-t border-slate-100">
                                        <td class="px-4 py-2.5">
                                            <p class="font-medium text-slate-900">{{ c.libelle }}</p>
                                            <p class="font-mono text-[11px] text-slate-400">{{ c.code }}</p>
                                        </td>
                                        <td class="px-4 py-2.5 text-slate-600">
                                            {{ typeChampLabels[c.type] || c.type }}
                                            <span v-if="c.type === 'liste' && c.options?.valeurs?.length" class="text-xs text-slate-400">
                                                · {{ c.options.valeurs.map((v) => v.libelle).join(', ') }}
                                            </span>
                                            <span v-if="c.type === 'table' && c.options?.table_code" class="font-mono text-xs text-slate-400">
                                                · {{ c.options.table_code }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <div class="flex flex-wrap gap-1">
                                                <span v-if="c.obligatoire" class="rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-medium text-rose-700">Obligatoire</span>
                                                <span v-if="c.gris" class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">Grisé</span>
                                                <span
                                                    v-if="c.options?.recherche"
                                                    class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-medium text-sky-700"
                                                >
                                                    <Search class="h-3 w-3" /> Recherche {{ c.options.recherche }}
                                                </span>
                                                <span v-if="!c.visible" class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-500">Masqué</span>
                                                <span v-if="!c.obligatoire && !c.gris && !c.options?.recherche && c.visible" class="text-xs text-slate-400">—</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!e.champs.length">
                                        <td colspan="3" class="px-4 py-4 text-center text-xs text-slate-400">Aucun champ sur cet écran</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Frais -->
                    <div v-show="onglet === 'frais'" class="space-y-5 p-5">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="rounded-xl border border-slate-200 p-4">
                                <p :class="cardTitle">Mode</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ labelModeFrais(produit.mode_frais) }}</p>
                                <p class="text-sm text-slate-500">{{ fraisDetail }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 p-4">
                                <p :class="cardTitle">Taxe</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ taxe.titre }}</p>
                                <p class="text-sm text-slate-500">{{ taxe.detail }}</p>
                            </div>
                            <div v-if="produit.frais_min != null || produit.frais_max != null" class="rounded-xl border border-slate-200 p-4">
                                <p :class="cardTitle">Bornes</p>
                                <p class="mt-1 text-sm text-slate-700">Minimum {{ fmt(produit.frais_min) }} · Plafond {{ fmt(produit.frais_max) }}</p>
                            </div>
                        </div>

                        <p v-if="produit.base_calcul" class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            <span class="font-medium text-slate-800">Règle : </span>{{ produit.base_calcul }}
                        </p>

                        <div v-if="tranchesParBareme.length" class="grid gap-4 lg:grid-cols-2">
                            <div v-for="g in tranchesParBareme" :key="g.critere" class="overflow-hidden rounded-xl border border-slate-200">
                                <p class="bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-900">{{ g.titre }}</p>
                                <table class="min-w-full text-sm">
                                    <thead class="text-left text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                                        <tr class="border-t border-slate-100">
                                            <th class="px-4 py-2">Montant demandé</th>
                                            <th class="px-4 py-2 text-right">Frais</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(t, i) in g.tranches" :key="i" class="border-t border-slate-100">
                                            <td class="px-4 py-2.5 text-slate-700">
                                                {{ t.libelle || `${fmt(t.montant_min)} → ${t.montant_max == null ? 'au-delà' : fmt(t.montant_max)}` }}
                                            </td>
                                            <td class="px-4 py-2.5 text-right font-semibold tabular-nums text-slate-900">
                                                {{ t.frais_fixe != null ? `${fmt(t.frais_fixe)} ${produit.devise}` : `${fmt(t.taux)} %` }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <p v-else-if="produit.mode_frais === 'tranche'" class="text-sm text-slate-500">Aucune tranche paramétrée.</p>

                        <p v-if="produit.notes" class="text-sm text-slate-500"><span class="font-medium text-slate-700">Notes : </span>{{ produit.notes }}</p>
                    </div>

                    <!-- Comptabilité -->
                    <div v-show="onglet === 'compta'" class="space-y-5 p-5">
                        <dl class="grid gap-px overflow-hidden rounded-xl border border-slate-200 bg-slate-200 text-sm sm:grid-cols-2 lg:grid-cols-3">
                            <div class="bg-white px-4 py-3">
                                <dt class="text-slate-500">Compte produit</dt>
                                <dd class="mt-0.5 font-mono font-medium" :class="produit.compte_produit ? 'text-slate-900' : 'text-amber-700'">
                                    {{ produit.compte_produit || 'Non renseigné' }}
                                </dd>
                            </div>
                            <div class="bg-white px-4 py-3">
                                <dt class="text-slate-500">Masque compte client</dt>
                                <dd class="mt-0.5 font-mono font-medium text-slate-900">{{ produit.compte_client_mask || '—' }}</dd>
                            </div>
                            <div class="bg-white px-4 py-3">
                                <dt class="text-slate-500">Libellé écriture produit</dt>
                                <dd class="mt-0.5 font-medium text-slate-900">{{ produit.libelle_ecriture_produit || produit.libelle }}</dd>
                            </div>
                            <template v-if="taxe.titre !== 'Aucune'">
                                <div class="bg-white px-4 py-3">
                                    <dt class="text-slate-500">Compte TAF</dt>
                                    <dd class="mt-0.5 font-mono font-medium text-slate-900">{{ produit.compte_taf || '—' }}</dd>
                                </div>
                                <div class="bg-white px-4 py-3">
                                    <dt class="text-slate-500">Code TAF</dt>
                                    <dd class="mt-0.5 font-mono font-medium text-slate-900">{{ produit.code_taf || '—' }}</dd>
                                </div>
                                <div class="bg-white px-4 py-3">
                                    <dt class="text-slate-500">Libellé écriture TAF</dt>
                                    <dd class="mt-0.5 font-medium text-slate-900">{{ produit.libelle_ecriture_taf || '—' }}</dd>
                                </div>
                            </template>
                        </dl>

                        <div class="overflow-hidden rounded-xl border border-slate-200">
                            <p class="bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-900">Schéma d’écritures</p>
                            <table v-if="produit.lignes.length" class="min-w-full text-sm">
                                <thead class="text-left text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                                    <tr class="border-t border-slate-100">
                                        <th class="px-4 py-2">Sens</th>
                                        <th class="px-4 py-2">Compte</th>
                                        <th class="px-4 py-2">Libellé</th>
                                        <th class="px-4 py-2">Nature</th>
                                        <th class="px-4 py-2">Montant</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(l, i) in produit.lignes" :key="i" class="border-t border-slate-100">
                                        <td class="px-4 py-2.5">
                                            <span
                                                class="inline-flex w-6 justify-center rounded font-bold"
                                                :class="l.sens === 'D' ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800'"
                                            >
                                                {{ l.sens }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5 font-mono tabular-nums">{{ l.compte }}</td>
                                        <td class="px-4 py-2.5 text-slate-600">{{ l.libelle_ecriture || '—' }}</td>
                                        <td class="px-4 py-2.5 text-slate-600">{{ l.nature_compte }}</td>
                                        <td class="px-4 py-2.5 text-slate-600">{{ l.type_montant }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <p v-else class="border-t border-slate-100 px-4 py-4 text-sm text-slate-500">
                                Schéma par défaut : débit du compte client et crédit du compte produit pour les frais
                                <template v-if="taxe.titre !== 'Aucune'">, plus la taxe sur le compte TAF</template>.
                            </p>
                        </div>
                    </div>

                    <!-- Scripts et constantes -->
                    <div v-show="onglet === 'scripts'" class="space-y-5 p-5">
                        <p v-if="!produit.scripts.length && !produit.constantes.length" class="py-6 text-center text-sm text-slate-500">
                            Aucun script ni constante.
                        </p>
                        <details v-for="s in produit.scripts" :key="s.code" class="group overflow-hidden rounded-xl border border-slate-200">
                            <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 bg-slate-50 px-4 py-3">
                                <span class="flex items-center gap-2">
                                    <span class="rounded bg-slate-900 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-white">{{ s.code }}</span>
                                    <span class="font-medium text-slate-900">{{ s.libelle }}</span>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                                        :class="s.actif ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                    >
                                        {{ s.actif ? 'Actif' : 'Inactif' }}
                                    </span>
                                </span>
                                <span class="flex items-center gap-1 text-xs text-slate-500">
                                    {{ s.moteur === 'requete' ? 'Requête SQL Flexcube' : s.moteur }}
                                    <ArrowRight class="h-4 w-4 transition-transform group-open:rotate-90" />
                                </span>
                            </summary>
                            <pre
                                v-if="s.moteur === 'requete'"
                                class="max-h-96 overflow-auto border-t border-slate-100 bg-slate-950 px-4 py-3 font-mono text-xs leading-5 text-slate-100"
                            >{{ s.parametres?.requete || '—' }}</pre>
                            <p v-else class="border-t border-slate-100 px-4 py-3 font-mono text-xs text-slate-600">{{ JSON.stringify(s.parametres) }}</p>
                        </details>

                        <div v-if="produit.constantes.length" class="overflow-hidden rounded-xl border border-slate-200">
                            <p class="bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-900">Constantes</p>
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                                    <tr class="border-t border-slate-100">
                                        <th class="px-4 py-2">Constante</th>
                                        <th class="px-4 py-2">Mode</th>
                                        <th class="px-4 py-2">Référence</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="c in produit.constantes" :key="c.code" class="border-t border-slate-100">
                                        <td class="px-4 py-2.5">
                                            <p class="font-medium text-slate-900">{{ c.libelle }}</p>
                                            <p class="font-mono text-[11px] text-slate-400">{{ c.code }} · {{ c.type }}</p>
                                        </td>
                                        <td class="px-4 py-2.5 text-slate-600">{{ modeConstanteLabels[c.mode] || c.mode }}</td>
                                        <td class="px-4 py-2.5 font-mono text-xs text-slate-600">{{ c.valeur_reference || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Historique -->
                    <div v-show="onglet === 'historique'" class="p-5">
                        <ol v-if="audits?.length" class="space-y-3">
                            <li v-for="a in audits" :key="a.id" class="border-l-2 border-slate-200 pl-3">
                                <p class="text-sm font-medium text-slate-900">
                                    {{ actionLabels[a.action] || a.action }}
                                    <span v-if="a.resume" class="font-normal text-slate-600"> · {{ a.resume }}</span>
                                </p>
                                <p class="text-xs text-slate-500">{{ a.user_name || '—' }} · {{ fmtDate(a.created_at) }}</p>
                            </li>
                        </ol>
                        <p v-else class="py-6 text-center text-sm text-slate-500">Aucune modification enregistrée.</p>
                    </div>
                </section>
            </div>
        </div>

        <OdConfirmDialog
            v-model:open="confirmSuppression"
            :title="`Supprimer le produit ${produit.code}`"
            :description="`« ${produit.libelle} » et son paramétrage (écrans, champs, schéma) seront supprimés. Cette action est définitive.`"
            confirm-label="Supprimer"
            variant="danger"
            :loading="suppressionEnCours"
            @confirm="supprimer"
        />
    </AppLayout>
</template>
