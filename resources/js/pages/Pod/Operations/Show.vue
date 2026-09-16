<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { AlertTriangle, CheckCircle2, Download, Plus, Trash2, XCircle } from 'lucide-vue-next';
import { labelStatutOperation, statutOperationClass } from '@/lib/podLabels';

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
    motif_annulation: string | null;
    validated_at: string | null;
    validated_by_name: string | null;
    calcul_detail: Record<string, unknown> | null;
    champs_saisis: Record<string, string | number | null>;
    champs_labels: Record<string, { libelle: string; type: string }>;
    user_name: string | null;
    created_at: string | null;
    pdf_url: string;
    produit: {
        id: number | null;
        code: string | null;
        libelle: string | null;
        mode_frais: string | null;
    };
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

const modeLabels: Record<string, string> = {
    fixe: 'Frais fixe',
    tranche: 'Par tranche',
    taux: 'Au pourcentage',
    mixte: 'Fixe + taux',
    manuel: 'Saisie manuelle',
    gratuit: 'Gratuit',
};

const placeholderComptes = computed(() =>
    props.operation.lignes.filter((l) => /X{3,}/i.test(l.compte)),
);

const totalDebit = computed(() =>
    props.operation.lignes
        .filter((l) => l.sens === 'D')
        .reduce((s, l) => s + Number(l.montant || 0), 0),
);

const totalCredit = computed(() =>
    props.operation.lignes
        .filter((l) => l.sens === 'C')
        .reduce((s, l) => s + Number(l.montant || 0), 0),
);

const destroy = () => {
    if (!confirm('Supprimer ce brouillon ?')) return;
    router.delete(`/pod/operations/${props.operation.id}`);
};

const valider = () => {
    if (!confirm('Valider cette opération ?')) return;
    router.post(`/pod/operations/${props.operation.id}/valider`);
};

const annuler = () => {
    const motif = window.prompt('Motif d’annulation (optionnel) :') ?? '';
    router.post(`/pod/operations/${props.operation.id}/annuler`, { motif_annulation: motif });
};

const statutClass = statutOperationClass;

const champsEntries = computed(() => {
    const saisies = props.operation.champs_saisis || {};
    const labels = props.operation.champs_labels || {};
    return Object.keys(saisies).map((code) => ({
        code,
        libelle: labels[code]?.libelle || code,
        value: saisies[code],
    }));
});

const constantesEntries = computed(() => {
    const detail = props.operation.calcul_detail || {};
    const raw = detail.constantes;
    if (!raw || typeof raw !== 'object') return [] as Array<{
        code: string;
        libelle: string;
        mode?: string;
        valeur: string | number | null;
    }>;
    return Object.values(raw as Record<string, {
        code: string;
        libelle: string;
        mode?: string;
        valeur: string | number | null;
    }>);
});

const displayChampValue = (v: string | number | null | undefined) => {
    if (v === null || v === undefined || v === '') return '—';
    return String(v);
};
</script>

<template>
    <Head :title="`Opération #${operation.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-[calc(100vh-4rem)] bg-gradient-to-b from-slate-50 via-white to-white">
            <div class="mx-auto flex max-w-5xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-6">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-semibold tracking-[0.2em] text-primary uppercase">
                                {{ operation.produit.code }}
                            </span>
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="statutClass(operation.statut)"
                            >
                                {{ labelStatutOperation(operation.statut) }}
                            </span>
                            <span class="text-xs text-slate-500">
                                {{ modeLabels[operation.produit.mode_frais || ''] || operation.produit.mode_frais }}
                            </span>
                        </div>
                        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">
                            {{ operation.produit.libelle }}
                        </h1>
                        <p class="mt-2 text-sm text-slate-600">
                            Opération #{{ operation.id }}
                            · {{ operation.user_name || '—' }}
                            <span v-if="operation.reference"> · Réf. {{ operation.reference }}</span>
                            <span v-if="operation.validated_by_name">
                                · Validée par {{ operation.validated_by_name }}
                            </span>
                        </p>
                        <p
                            v-if="operation.statut === 'annule' && operation.motif_annulation"
                            class="mt-1 text-sm text-slate-500"
                        >
                            Motif annulation : {{ operation.motif_annulation }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button as-child variant="outline" class="h-11">
                            <a :href="operation.pdf_url" target="_blank" rel="noopener">
                                <Download class="mr-2 h-4 w-4" /> PDF
                            </a>
                        </Button>
                        <Button as-child variant="outline" class="h-11">
                            <Link href="/pod/operations/create">
                                <Plus class="mr-2 h-4 w-4" /> Nouvelle
                            </Link>
                        </Button>
                        <Button
                            v-if="operation.statut === 'brouillon'"
                            class="h-11"
                            @click="valider"
                        >
                            <CheckCircle2 class="mr-2 h-4 w-4" /> Valider
                        </Button>
                        <Button
                            v-if="operation.statut !== 'annule'"
                            variant="outline"
                            class="h-11 text-amber-800"
                            @click="annuler"
                        >
                            <XCircle class="mr-2 h-4 w-4" /> Annuler
                        </Button>
                        <Button
                            v-if="operation.statut === 'brouillon'"
                            variant="destructive"
                            class="h-11"
                            @click="destroy"
                        >
                            <Trash2 class="mr-2 h-4 w-4" /> Supprimer
                        </Button>
                    </div>
                </div>

                <div
                    v-if="placeholderComptes.length"
                    class="flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"
                >
                    <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                    <div>
                        <p class="font-medium">Compte(s) placeholder détecté(s)</p>
                        <p class="mt-1 text-amber-900/80">
                            Le schéma utilise un compte non renseigné (ex. <code>7XXXXXXX</code>).
                            Complétez le <strong>compte produit</strong> dans le paramétrage du produit
                            <Link
                                v-if="operation.produit.id"
                                :href="`/pod/produits/${operation.produit.id}/edit`"
                                class="underline"
                            >
                                {{ operation.produit.code }}
                            </Link>
                            puis recréez l’opération.
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-[11px] tracking-wide text-slate-500 uppercase">Compte client</p>
                        <p class="mt-2 font-semibold tabular-nums text-slate-900">{{ operation.compte_client }}</p>
                        <p class="text-xs text-slate-500">{{ operation.nom_client || '—' }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-[11px] tracking-wide text-slate-500 uppercase">Frais HT</p>
                        <p class="mt-2 text-xl font-bold tabular-nums text-slate-900">
                            {{ fmt(operation.frais_ht) }}
                            <span class="text-sm font-medium text-slate-500">{{ operation.devise }}</span>
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-[11px] tracking-wide text-slate-500 uppercase">
                            TAF ({{ Number(operation.taux_taf) }} %)
                        </p>
                        <p class="mt-2 text-xl font-bold tabular-nums text-slate-900">
                            {{ fmt(operation.montant_taf) }}
                            <span class="text-sm font-medium text-slate-500">{{ operation.devise }}</span>
                        </p>
                    </div>
                    <div class="rounded-2xl bg-slate-900 p-4 text-white shadow-sm">
                        <p class="text-[11px] tracking-wide text-slate-300 uppercase">Total débit client</p>
                        <p class="mt-2 text-xl font-bold tabular-nums">
                            {{ fmt(operation.total_client) }}
                            <span class="text-sm font-medium text-slate-400">{{ operation.devise }}</span>
                        </p>
                    </div>
                </div>

                <dl class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-slate-500 uppercase">Montant demande</dt>
                        <dd class="mt-1 font-medium text-slate-900">{{ fmt(operation.montant_demande) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 uppercase">Date valeur</dt>
                        <dd class="mt-1 font-medium text-slate-900">{{ operation.date_valeur || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 uppercase">Agence</dt>
                        <dd class="mt-1 font-medium text-slate-900">{{ operation.code_agence || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 uppercase">Libellé</dt>
                        <dd class="mt-1 font-medium text-slate-900">{{ operation.libelle || '—' }}</dd>
                    </div>
                </dl>

                <section
                    v-if="champsEntries.length"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="text-sm font-semibold text-slate-900">Champs produit saisis</h2>
                    </div>
                    <dl class="grid gap-4 p-5 sm:grid-cols-2">
                        <div v-for="c in champsEntries" :key="c.code">
                            <dt class="text-xs text-slate-500 uppercase">{{ c.libelle }}</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ displayChampValue(c.value) }}</dd>
                        </div>
                    </dl>
                </section>

                <section
                    v-if="constantesEntries.length"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="text-sm font-semibold text-slate-900">Constantes résolues</h2>
                    </div>
                    <dl class="grid gap-4 p-5 sm:grid-cols-2">
                        <div v-for="c in constantesEntries" :key="c.code">
                            <dt class="text-xs text-slate-500 uppercase">
                                {{ c.libelle }}
                                <span v-if="c.mode" class="font-mono normal-case text-slate-400"> · {{ c.mode }}</span>
                            </dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ displayChampValue(c.valeur) }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <h2 class="text-sm font-semibold text-slate-900">Écritures générées</h2>
                        <p class="text-xs text-slate-500">
                            D {{ fmt(totalDebit) }} · C {{ fmt(totalCredit) }}
                        </p>
                    </div>
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th class="px-5 py-3 font-medium">Sens</th>
                                <th class="px-5 py-3 font-medium">Compte</th>
                                <th class="px-5 py-3 font-medium">Libellé</th>
                                <th class="px-5 py-3 font-medium">Nature</th>
                                <th class="px-5 py-3 font-medium text-right">Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(l, i) in operation.lignes"
                                :key="i"
                                class="border-t border-slate-100"
                            >
                                <td class="px-5 py-3">
                                    <span
                                        class="inline-flex min-w-7 justify-center rounded-md px-1.5 py-0.5 text-xs font-bold"
                                        :class="l.sens === 'D'
                                            ? 'bg-rose-100 text-rose-800'
                                            : 'bg-emerald-100 text-emerald-800'"
                                    >
                                        {{ l.sens }}
                                    </span>
                                </td>
                                <td
                                    class="px-5 py-3 font-medium tabular-nums"
                                    :class="/X{3,}/i.test(l.compte) ? 'text-amber-700' : 'text-slate-900'"
                                >
                                    {{ l.compte }}
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ l.libelle_ecriture }}</td>
                                <td class="px-5 py-3 text-slate-500">{{ l.nature_compte }}</td>
                                <td class="px-5 py-3 text-right font-semibold tabular-nums text-slate-900">
                                    {{ fmt(l.montant) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <div class="flex justify-end">
                    <Button as-child variant="outline">
                        <Link href="/pod/operations">Retour à la liste</Link>
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
