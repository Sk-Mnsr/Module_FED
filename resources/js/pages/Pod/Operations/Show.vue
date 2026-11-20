<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import DynamicFieldInput, { type ChampDef } from '@/components/Pod/DynamicFieldInput.vue';
import OdConfirmDialog from '@/components/OdConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    AlertTriangle,
    CheckCircle2,
    Circle,
    CirclePause,
    Download,
    Paperclip,
    Plus,
    Trash2,
    XCircle,
} from 'lucide-vue-next';
import { actionEtapeLabels, labelModeFrais, labelStatutOperation, statutOperationClass } from '@/lib/podLabels';

type Piece = { id: number; nom: string; taille: number; user_name: string | null; url: string };

type EtapeCircuit = {
    index: number;
    code: string | null;
    libelle: string;
    role: string;
    etat: 'fait' | 'courant' | 'a_venir' | 'suspendu' | 'rejete' | 'annule';
    acteur: string | null;
    date: string | null;
    champs: Array<{ code: string; libelle: string; type: string; valeur: string | number | null; pieces: Piece[] }>;
};

type ChampAction = ChampDef & { valeur: string | number | null };

type Operation = {
    id: number;
    reference: string | null;
    compte_client: string;
    code_agence: string | null;
    nom_client: string | null;
    date_valeur: string | null;
    montant_demande: string | number | null;
    frais_ht: string | number;
    taux_taf: string | number | null;
    montant_taf: string | number;
    total_client: string | number;
    devise: string;
    libelle: string | null;
    statut: string;
    etape_libelle: string | null;
    motif_annulation: string | null;
    motif_decision: string | null;
    validated_at: string | null;
    validated_by_name: string | null;
    calcul_detail: Record<string, unknown> | null;
    user_name: string | null;
    created_at: string | null;
    pdf_url: string;
    produit: { id: number | null; code: string | null; libelle: string | null; mode_frais: string | null };
    lignes: Array<{
        sens: string;
        compte: string;
        libelle_ecriture: string | null;
        nature_compte: string | null;
        type_montant: string | null;
        montant: string | number;
    }>;
};

const props = defineProps<{
    operation: Operation;
    circuit: EtapeCircuit[];
    historique: Array<{
        id: number;
        action: string;
        ecran_libelle: string | null;
        user_name: string | null;
        commentaire: string | null;
        created_at: string | null;
    }>;
    action: {
        peut_agir: boolean;
        motif_blocage: string | null;
        etape: string | null;
        dernier: boolean;
        champs: ChampAction[];
    };
    reprise: {
        peut_reprendre: boolean;
        champs: ChampAction[];
    };
    peut_annuler: boolean;
}>();

const breadcrumbs = [
    { title: 'Produits divers', href: '/pod/produits' },
    { title: 'Opérations', href: '/pod/operations' },
    { title: `#${props.operation.id}`, href: `/pod/operations/${props.operation.id}` },
];

const fmt = (v: string | number | null | undefined) => {
    if (v === null || v === undefined || v === '') return '—';
    return Number(v).toLocaleString('fr-FR');
};

const fmtDate = (v: string | null) => (v ? new Date(v).toLocaleString('fr-FR') : '—');

const fmtTaille = (n: number) => (n > 1024 * 1024 ? `${(n / 1024 / 1024).toFixed(1)} Mo` : `${Math.max(1, Math.round(n / 1024))} Ko`);

const valeursInitiales = (champs: ChampAction[]) => {
    const out: Record<string, string | number | null> = {};
    for (const c of champs) {
        if (c.type !== 'fichier') out[c.code] = c.valeur ?? null;
    }
    return out;
};

const etapeForm = useForm({
    commentaire: '',
    champs_saisis: valeursInitiales(props.action.champs),
    fichiers: {} as Record<string, File[]>,
});

const repriseForm = useForm({
    commentaire: '',
    champs_saisis: valeursInitiales(props.reprise.champs),
    fichiers: {} as Record<string, File[]>,
});

watch(
    () => props.action.champs,
    (champs) => {
        etapeForm.champs_saisis = valeursInitiales(champs);
        etapeForm.fichiers = {};
    },
);

watch(
    () => props.reprise.champs,
    (champs) => {
        repriseForm.champs_saisis = valeursInitiales(champs);
        repriseForm.fichiers = {};
    },
);

const motif = ref('');
const motifErreur = ref<string | null>(null);

type Confirmation = {
    title: string;
    description: string;
    label: string;
    variant: 'default' | 'danger' | 'warning' | 'success';
    avecMotif?: boolean;
    run: (finish: () => void) => void;
};

const confirmation = ref<Confirmation | null>(null);
const confirmationOuverte = ref(false);
const confirmationEnCours = ref(false);
const motifAnnulation = ref('');

const demander = (c: Confirmation) => {
    confirmation.value = c;
    confirmationOuverte.value = true;
};

const confirmer = () => {
    const c = confirmation.value;
    if (!c) return;
    confirmationEnCours.value = true;
    c.run(() => {
        confirmationEnCours.value = false;
        confirmationOuverte.value = false;
    });
};

const validerEtape = () => {
    demander({
        title: props.action.dernier ? 'Valider le dossier' : 'Valider l’étape',
        description: props.action.dernier
            ? 'Le circuit sera terminé et le dossier passera au statut Validé.'
            : `L’étape « ${props.action.etape} » sera validée et le dossier passera à l’étape suivante.`,
        label: props.action.dernier ? 'Valider le dossier' : 'Valider l’étape',
        variant: 'success',
        run: (finish) =>
            etapeForm.post(`/pod/operations/${props.operation.id}/valider`, {
                forceFormData: true,
                preserveScroll: true,
                onFinish: finish,
            }),
    });
};

const decider = (type: 'suspendre' | 'rejeter') => {
    motifErreur.value = null;
    if (!motif.value.trim()) {
        motifErreur.value = type === 'rejeter' ? 'Le motif de rejet est obligatoire.' : 'Le motif de suspension est obligatoire.';
        return;
    }
    const envoyer = (finish: () => void) =>
        router.post(
            `/pod/operations/${props.operation.id}/${type}`,
            { motif: motif.value },
            { preserveScroll: true, onFinish: finish },
        );
    demander(
        type === 'rejeter'
            ? {
                  title: 'Rejeter le dossier',
                  description: `Le dossier sera rejeté définitivement.\nMotif : ${motif.value.trim()}`,
                  label: 'Rejeter',
                  variant: 'danger',
                  run: envoyer,
              }
            : {
                  title: 'Suspendre le dossier',
                  description: `Le dossier retournera à l’initiateur pour être complété.\nMotif : ${motif.value.trim()}`,
                  label: 'Suspendre',
                  variant: 'warning',
                  run: envoyer,
              },
    );
};

const reprendre = () => {
    repriseForm.post(`/pod/operations/${props.operation.id}/reprendre`, { forceFormData: true, preserveScroll: true });
};

const annuler = () => {
    motifAnnulation.value = '';
    demander({
        title: 'Annuler le dossier',
        description: 'Le dossier sortira du circuit et ne pourra plus être traité.',
        label: 'Annuler le dossier',
        variant: 'warning',
        avecMotif: true,
        run: (finish) =>
            router.post(
                `/pod/operations/${props.operation.id}/annuler`,
                { motif_annulation: motifAnnulation.value },
                { onFinish: finish },
            ),
    });
};

const destroy = () => {
    demander({
        title: 'Supprimer ce brouillon',
        description: 'Le brouillon sera supprimé définitivement.',
        label: 'Supprimer',
        variant: 'danger',
        run: (finish) => router.delete(`/pod/operations/${props.operation.id}`, { onFinish: finish }),
    });
};

const soldeApresFrais = computed(() => {
    const v = (props.operation.calcul_detail as { solde_apres_frais?: number | null } | null)?.solde_apres_frais;
    return v === null || v === undefined ? null : Number(v);
});

const placeholderComptes = computed(() => props.operation.lignes.filter((l) => /X{3,}/i.test(l.compte)));
const hasMontants = computed(() => Number(props.operation.total_client) > 0 || props.operation.lignes.some((l) => Number(l.montant) > 0));

const etatClass: Record<EtapeCircuit['etat'], string> = {
    fait: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    courant: 'border-sky-300 bg-sky-50 text-sky-900 ring-2 ring-sky-200',
    a_venir: 'border-slate-200 bg-white text-slate-500',
    suspendu: 'border-amber-300 bg-amber-50 text-amber-900',
    rejete: 'border-rose-300 bg-rose-50 text-rose-900',
    annule: 'border-slate-200 bg-slate-50 text-slate-500',
};

const etatLabel: Record<EtapeCircuit['etat'], string> = {
    fait: 'Fait',
    courant: 'En attente',
    a_venir: 'À venir',
    suspendu: 'Suspendu',
    rejete: 'Rejeté',
    annule: 'Annulé',
};

const affichage = (v: string | number | null | undefined) => (v === null || v === undefined || v === '' ? '—' : String(v));

const sectionShell = 'overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-900/5';
const sectionHead = 'flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50 px-5 py-3';
const inputClass = 'h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm shadow-sm';
</script>

<template>
    <Head :title="`Opération #${operation.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-full flex-1 flex-col bg-gradient-to-b from-slate-50 via-white to-white">
            <div class="flex w-full flex-1 flex-col gap-5 p-4 sm:p-6">
                <div
                    class="flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-slate-200/80 bg-white px-5 py-4 shadow-sm ring-1 ring-slate-900/5"
                >
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-md bg-slate-900 px-2 py-0.5 font-mono text-xs font-semibold text-white">
                                {{ operation.produit.code }}
                            </span>
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="statutOperationClass(operation.statut)"
                            >
                                {{ labelStatutOperation(operation.statut) }}
                            </span>
                            <span v-if="operation.statut === 'en_cours' && operation.etape_libelle" class="text-xs text-slate-500">
                                En attente de « {{ operation.etape_libelle }} »
                            </span>
                        </div>
                        <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">{{ operation.produit.libelle }}</h1>
                        <p class="mt-1 text-sm text-slate-500">
                            Dossier #{{ operation.id }} · initié par {{ operation.user_name || '—' }} le {{ fmtDate(operation.created_at) }}
                            <span v-if="operation.reference"> · Réf. {{ operation.reference }}</span>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button as-child variant="outline" class="h-11 bg-white">
                            <a :href="operation.pdf_url" target="_blank" rel="noopener">
                                <Download class="mr-2 h-4 w-4" /> PDF
                            </a>
                        </Button>
                        <Button as-child variant="outline" class="h-11 bg-white">
                            <Link href="/pod/operations/create"><Plus class="mr-2 h-4 w-4" /> Nouvelle</Link>
                        </Button>
                        <Button v-if="peut_annuler" variant="outline" class="h-11 bg-white text-amber-800" @click="annuler">
                            <XCircle class="mr-2 h-4 w-4" /> Annuler
                        </Button>
                        <Button v-if="operation.statut === 'brouillon'" variant="destructive" class="h-11" @click="destroy">
                            <Trash2 class="mr-2 h-4 w-4" /> Supprimer
                        </Button>
                    </div>
                </div>

                <div
                    v-if="operation.motif_decision && ['suspendue', 'rejete'].includes(operation.statut)"
                    class="flex gap-3 rounded-2xl border px-4 py-3 text-sm"
                    :class="operation.statut === 'rejete' ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-amber-200 bg-amber-50 text-amber-950'"
                >
                    <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                    <div>
                        <p class="font-medium">{{ operation.statut === 'rejete' ? 'Motif du rejet' : 'Motif de la suspension' }}</p>
                        <p class="mt-0.5">{{ operation.motif_decision }}</p>
                    </div>
                </div>

                <section :class="sectionShell">
                    <div :class="sectionHead">
                        <h2 class="text-sm font-semibold text-slate-900">Circuit</h2>
                        <p class="text-xs text-slate-500">Chaque étape est traitée par un utilisateur différent.</p>
                    </div>
                    <ol class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-5">
                        <li
                            v-for="s in circuit"
                            :key="s.index"
                            class="rounded-xl border px-3 py-3"
                            :class="etatClass[s.etat]"
                        >
                            <div class="flex items-center gap-2">
                                <CheckCircle2 v-if="s.etat === 'fait'" class="h-4 w-4 shrink-0" />
                                <CirclePause v-else-if="s.etat === 'suspendu'" class="h-4 w-4 shrink-0" />
                                <XCircle v-else-if="s.etat === 'rejete'" class="h-4 w-4 shrink-0" />
                                <Circle v-else class="h-4 w-4 shrink-0" />
                                <span class="text-xs font-semibold tracking-wide uppercase">Étape {{ s.index + 1 }} · {{ etatLabel[s.etat] }}</span>
                            </div>
                            <p class="mt-1.5 text-sm font-semibold text-slate-900">{{ s.libelle }}</p>
                            <p class="text-xs opacity-80">{{ s.role }}</p>
                            <p v-if="s.acteur" class="mt-1 text-xs opacity-80">{{ s.acteur }} · {{ fmtDate(s.date) }}</p>
                        </li>
                    </ol>
                </section>

                <div class="grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(340px,1fr)]">
                    <div class="flex min-w-0 flex-col gap-5">
                        <section v-if="operation.compte_client || operation.nom_client" :class="sectionShell">
                            <div :class="sectionHead">
                                <h2 class="text-sm font-semibold text-slate-900">Client</h2>
                            </div>
                            <dl class="grid gap-4 p-5 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <dt class="text-slate-500">Compte client</dt>
                                    <dd class="mt-0.5 font-medium tabular-nums text-slate-900">{{ operation.compte_client }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Nom</dt>
                                    <dd class="mt-0.5 font-medium text-slate-900">{{ operation.nom_client || '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Agence</dt>
                                    <dd class="mt-0.5 font-medium text-slate-900">{{ operation.code_agence || '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Date valeur</dt>
                                    <dd class="mt-0.5 font-medium text-slate-900">{{ operation.date_valeur || '—' }}</dd>
                                </div>
                            </dl>
                        </section>

                        <div
                            v-if="soldeApresFrais !== null"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border px-5 py-4"
                            :class="soldeApresFrais < 0 ? 'border-rose-200 bg-rose-50' : 'border-emerald-200 bg-emerald-50'"
                        >
                            <div>
                                <p
                                    class="text-[11px] font-semibold tracking-wide uppercase"
                                    :class="soldeApresFrais < 0 ? 'text-rose-700' : 'text-emerald-700'"
                                >
                                    {{ soldeApresFrais < 0 ? 'Montant à compléter par le client' : 'Solde après frais' }}
                                </p>
                                <p class="text-xs text-slate-500">Solde net − (encours global dû + frais TTC), au moment de la demande</p>
                            </div>
                            <p
                                class="text-2xl font-bold tabular-nums"
                                :class="soldeApresFrais < 0 ? 'text-rose-700' : 'text-emerald-700'"
                            >
                                {{ fmt(Math.abs(soldeApresFrais)) }} {{ operation.devise }}
                            </p>
                        </div>

                        <section
                            v-for="s in circuit.filter((e) => e.champs.length)"
                            :key="`data-${s.index}`"
                            :class="sectionShell"
                        >
                            <div :class="sectionHead">
                                <h2 class="text-sm font-semibold text-slate-900">{{ s.libelle }}</h2>
                                <p v-if="s.acteur" class="text-xs text-slate-500">{{ s.acteur }}</p>
                            </div>
                            <dl class="grid gap-4 p-5 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                <div v-for="c in s.champs" :key="c.code">
                                    <dt class="text-slate-500">{{ c.libelle }}</dt>
                                    <dd v-if="c.type !== 'fichier'" class="mt-0.5 font-medium text-slate-900">{{ affichage(c.valeur) }}</dd>
                                    <dd v-else class="mt-1 space-y-1">
                                        <a
                                            v-for="p in c.pieces"
                                            :key="p.id"
                                            :href="p.url"
                                            class="flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                                        >
                                            <Paperclip class="h-3.5 w-3.5" /> {{ p.nom }}
                                            <span class="text-xs font-normal text-slate-400">{{ fmtTaille(p.taille) }}</span>
                                        </a>
                                        <span v-if="!c.pieces.length" class="text-slate-400">Aucun fichier</span>
                                    </dd>
                                </div>
                            </dl>
                        </section>

                        <section v-if="hasMontants" :class="sectionShell">
                            <div :class="sectionHead">
                                <h2 class="text-sm font-semibold text-slate-900">Frais et écritures</h2>
                                <p class="text-xs text-slate-500">{{ labelModeFrais(operation.produit.mode_frais || '') }}</p>
                            </div>
                            <div class="grid gap-3 p-5 sm:grid-cols-3">
                                <div class="rounded-xl border border-slate-200 p-3">
                                    <p class="text-[11px] text-slate-500 uppercase">Frais HT</p>
                                    <p class="mt-1 text-lg font-semibold tabular-nums">{{ fmt(operation.frais_ht) }} {{ operation.devise }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 p-3">
                                    <p class="text-[11px] text-slate-500 uppercase">Taxe ({{ Number(operation.taux_taf) }} %)</p>
                                    <p class="mt-1 text-lg font-semibold tabular-nums">{{ fmt(operation.montant_taf) }} {{ operation.devise }}</p>
                                </div>
                                <div class="rounded-xl bg-slate-900 p-3 text-white">
                                    <p class="text-[11px] text-slate-300 uppercase">Total client</p>
                                    <p class="mt-1 text-lg font-semibold tabular-nums">{{ fmt(operation.total_client) }} {{ operation.devise }}</p>
                                </div>
                            </div>
                            <div
                                v-if="placeholderComptes.length"
                                class="mx-5 mb-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900"
                            >
                                Compte non renseigné dans le schéma (ex. 7XXXXXXX) : la validation finale sera refusée.
                            </div>
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-left text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                                    <tr>
                                        <th class="px-5 py-2.5">Sens</th>
                                        <th class="px-5 py-2.5">Compte</th>
                                        <th class="px-5 py-2.5">Libellé</th>
                                        <th class="px-5 py-2.5 text-right">Montant</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(l, i) in operation.lignes" :key="i" class="border-t border-slate-100">
                                        <td class="px-5 py-2.5 font-semibold">{{ l.sens }}</td>
                                        <td class="px-5 py-2.5 font-mono tabular-nums">{{ l.compte }}</td>
                                        <td class="px-5 py-2.5 text-slate-600">{{ l.libelle_ecriture }}</td>
                                        <td class="px-5 py-2.5 text-right font-semibold tabular-nums">{{ fmt(l.montant) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </section>
                    </div>

                    <aside class="flex min-w-0 flex-col gap-5">
                        <section v-if="action.peut_agir" class="overflow-hidden rounded-2xl border-2 border-sky-200 bg-white shadow-sm">
                            <div class="border-b border-sky-100 bg-sky-50 px-5 py-3">
                                <p class="text-[11px] font-semibold tracking-wide text-sky-700 uppercase">À traiter</p>
                                <h2 class="text-sm font-semibold text-slate-900">{{ action.etape }}</h2>
                            </div>
                            <div class="space-y-4 p-5">
                                <DynamicFieldInput
                                    v-for="c in action.champs"
                                    :key="c.code"
                                    :champ="c"
                                    :model-value="etapeForm.champs_saisis[c.code] ?? null"
                                    :input-class="inputClass"
                                    :error="(etapeForm.errors as Record<string, string>)[`champs_saisis.${c.code}`]"
                                    @update:model-value="(v) => (etapeForm.champs_saisis[c.code] = v)"
                                    @files="(files) => (etapeForm.fichiers[c.code] = files)"
                                />
                                <div class="space-y-1.5">
                                    <Label>Commentaire</Label>
                                    <textarea
                                        v-model="etapeForm.commentaire"
                                        rows="2"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm"
                                        placeholder="Observation (optionnel)"
                                    />
                                </div>
                                <Button class="h-11 w-full" :disabled="etapeForm.processing" @click="validerEtape">
                                    <CheckCircle2 class="mr-2 h-4 w-4" />
                                    {{ action.dernier ? 'Valider le dossier' : 'Valider l’étape' }}
                                </Button>

                                <div class="space-y-2 border-t border-slate-100 pt-4">
                                    <Label>Motif (suspension ou rejet)</Label>
                                    <textarea
                                        v-model="motif"
                                        rows="2"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm"
                                        placeholder="Pièce manquante, couverture insuffisante…"
                                    />
                                    <p v-if="motifErreur" class="text-xs text-destructive">{{ motifErreur }}</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <Button variant="outline" class="h-11 bg-white text-amber-800" @click="decider('suspendre')">
                                            <CirclePause class="mr-2 h-4 w-4" /> Suspendre
                                        </Button>
                                        <Button variant="outline" class="h-11 bg-white text-rose-700" @click="decider('rejeter')">
                                            <XCircle class="mr-2 h-4 w-4" /> Rejeter
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section
                            v-else-if="action.motif_blocage"
                            class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-600"
                        >
                            <p class="font-medium text-slate-900">En attente de « {{ action.etape }} »</p>
                            <p class="mt-1">{{ action.motif_blocage }}</p>
                        </section>

                        <section v-if="reprise.peut_reprendre" class="overflow-hidden rounded-2xl border-2 border-amber-200 bg-white shadow-sm">
                            <div class="border-b border-amber-100 bg-amber-50 px-5 py-3">
                                <p class="text-[11px] font-semibold tracking-wide text-amber-700 uppercase">À compléter</p>
                                <h2 class="text-sm font-semibold text-slate-900">Reprendre le dossier</h2>
                            </div>
                            <div class="space-y-4 p-5">
                                <DynamicFieldInput
                                    v-for="c in reprise.champs"
                                    :key="c.code"
                                    :champ="c"
                                    :model-value="repriseForm.champs_saisis[c.code] ?? null"
                                    :input-class="inputClass"
                                    :error="(repriseForm.errors as Record<string, string>)[`champs_saisis.${c.code}`]"
                                    @update:model-value="(v) => (repriseForm.champs_saisis[c.code] = v)"
                                    @files="(files) => (repriseForm.fichiers[c.code] = files)"
                                />
                                <div class="space-y-1.5">
                                    <Label>Commentaire</Label>
                                    <textarea
                                        v-model="repriseForm.commentaire"
                                        rows="2"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm"
                                        placeholder="Ce qui a été complété"
                                    />
                                </div>
                                <Button class="h-11 w-full" :disabled="repriseForm.processing" @click="reprendre">
                                    Renvoyer dans le circuit
                                </Button>
                            </div>
                        </section>

                        <section :class="sectionShell">
                            <div :class="sectionHead">
                                <h2 class="text-sm font-semibold text-slate-900">Historique</h2>
                            </div>
                            <ol class="space-y-3 p-5">
                                <li v-for="h in historique" :key="h.id" class="border-l-2 border-slate-200 pl-3">
                                    <p class="text-sm font-medium text-slate-900">
                                        {{ actionEtapeLabels[h.action] || h.action }}
                                        <span v-if="h.ecran_libelle" class="font-normal text-slate-500"> · {{ h.ecran_libelle }}</span>
                                    </p>
                                    <p class="text-xs text-slate-500">{{ h.user_name || '—' }} · {{ fmtDate(h.created_at) }}</p>
                                    <p v-if="h.commentaire" class="mt-1 text-sm text-slate-700">{{ h.commentaire }}</p>
                                </li>
                                <li v-if="!historique.length" class="text-sm text-slate-500">Aucun événement.</li>
                            </ol>
                        </section>
                    </aside>
                </div>
            </div>
        </div>

        <OdConfirmDialog
            v-model:open="confirmationOuverte"
            :title="confirmation?.title ?? ''"
            :description="confirmation?.description ?? ''"
            :confirm-label="confirmation?.label"
            :variant="confirmation?.variant"
            :loading="confirmationEnCours"
            @confirm="confirmer"
        >
            <div v-if="confirmation?.avecMotif" class="space-y-1.5">
                <Label>Motif (facultatif)</Label>
                <textarea
                    v-model="motifAnnulation"
                    rows="2"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm"
                    placeholder="Raison de l’annulation"
                />
            </div>
        </OdConfirmDialog>
    </AppLayout>
</template>
