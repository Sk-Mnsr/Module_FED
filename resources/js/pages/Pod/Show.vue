<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { labelStatutProduit, statutProduitClass } from '@/lib/podLabels';

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
    initiateur: string | null;
    validateur: string | null;
    mode_frais: string;
    frais_fixe: string | number | null;
    taux_frais: string | number | null;
    frais_min: string | number | null;
    frais_max: string | number | null;
    taux_taf: string | number | null;
    taux_taf_effectif: number;
    base_calcul: string | null;
    notes: string | null;
    statut: string;
    actif: boolean;
    created_by_name?: string | null;
    updated_by_name?: string | null;
    tranches: Array<{
        libelle: string | null;
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
    champs: Array<{
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
        };
    }>;
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

const statutForm = useForm({
    statut: props.produit.statut,
});

const changeStatut = () => {
    statutForm.post(`/pod/produits/${props.produit.id}/statut`, { preserveScroll: true });
};

const destroy = () => {
    if (!confirm(`Supprimer le produit ${props.produit.code} ?`)) return;
    router.delete(`/pod/produits/${props.produit.id}`);
};

const fmt = (v: string | number | null | undefined) => {
    if (v === null || v === undefined || v === '') return '—';
    return Number(v).toLocaleString('fr-FR');
};
</script>

<template>
    <Head :title="`Produit ${produit.code}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-5xl flex-col gap-6 p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] font-semibold tracking-[0.18em] text-primary uppercase">
                        Produits divers
                    </p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{{ produit.libelle }}</h1>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="inline-flex rounded-md bg-slate-900 px-2 py-0.5 font-mono text-xs font-semibold text-white">
                            {{ produit.code }}
                        </span>
                        <span
                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="statutProduitClass(produit.statut)"
                        >
                            {{ labelStatutProduit(produit.statut) }}
                        </span>
                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 ring-1 ring-slate-200">
                            {{ produit.devise }}
                        </span>
                        <span
                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1"
                            :class="
                                produit.actif
                                    ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                                    : 'bg-slate-100 text-slate-500 ring-slate-200'
                            "
                        >
                            {{ produit.actif ? 'Actif' : 'Inactif' }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ produit.type_operation || 'Opération diverse' }}
                        · Initiateur {{ produit.initiateur || '—' }}
                        · {{ produit.ecrans?.length || 0 }} écran{{ (produit.ecrans?.length || 0) > 1 ? 's' : '' }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button as-child variant="outline">
                        <Link :href="`/pod/produits/${produit.id}/edit`">
                            <Pencil class="mr-2 h-4 w-4" /> Modifier
                        </Link>
                    </Button>
                    <Button variant="destructive" @click="destroy">
                        <Trash2 class="mr-2 h-4 w-4" /> Supprimer
                    </Button>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs uppercase text-muted-foreground">Statut</p>
                    <form class="mt-2 flex gap-2" @submit.prevent="changeStatut">
                        <select
                            v-model="statutForm.statut"
                            class="h-9 flex-1 rounded-md border border-input bg-background px-2 text-sm"
                        >
                            <option value="brouillon">Brouillon</option>
                            <option value="valide">Validé</option>
                            <option value="production">Production</option>
                        </select>
                        <Button size="sm" type="submit" :disabled="statutForm.processing">OK</Button>
                    </form>
                    <p
                        v-for="(err, key) in statutForm.errors"
                        :key="key"
                        class="mt-2 text-xs text-destructive"
                    >
                        {{ err }}
                    </p>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs uppercase text-muted-foreground">Mode frais</p>
                    <p class="mt-2 text-lg font-semibold">{{ produit.mode_frais }}</p>
                    <p class="text-sm text-muted-foreground">
                        Fixe {{ fmt(produit.frais_fixe) }} · Taux {{ fmt(produit.taux_frais) }} %
                    </p>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs uppercase text-muted-foreground">TAF</p>
                    <p class="mt-2 text-lg font-semibold">{{ produit.taux_taf_effectif }} %</p>
                    <p class="text-sm text-muted-foreground">
                        Défaut appli {{ tauxTafDefaut }} % · produit
                        {{ produit.taux_taf != null ? produit.taux_taf + ' %' : 'non défini' }}
                    </p>
                </div>
            </div>

            <section class="rounded-xl border border-border bg-card p-5">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    Comptes
                </h2>
                <dl class="grid gap-3 text-sm md:grid-cols-2">
                    <div>
                        <dt class="text-muted-foreground">Compte produit</dt>
                        <dd class="font-medium">{{ produit.compte_produit || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Compte TAF</dt>
                        <dd class="font-medium">{{ produit.compte_taf || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Masque client</dt>
                        <dd class="font-medium">{{ produit.compte_client_mask || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Validateur</dt>
                        <dd class="font-medium">{{ produit.validateur || '—' }}</dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-muted-foreground">Libellé écriture produit</dt>
                        <dd class="font-medium">{{ produit.libelle_ecriture_produit || '—' }}</dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-muted-foreground">Libellé écriture TAF</dt>
                        <dd class="font-medium">{{ produit.libelle_ecriture_taf || '—' }}</dd>
                    </div>
                    <div v-if="produit.base_calcul" class="md:col-span-2">
                        <dt class="text-muted-foreground">Base de calcul</dt>
                        <dd class="font-medium">{{ produit.base_calcul }}</dd>
                    </div>
                    <div v-if="produit.notes" class="md:col-span-2">
                        <dt class="text-muted-foreground">Notes</dt>
                        <dd class="font-medium">{{ produit.notes }}</dd>
                    </div>
                </dl>
            </section>

            <section class="overflow-hidden rounded-xl border border-border bg-card">
                <div class="border-b border-border px-5 py-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    Écrans ({{ produit.ecrans?.length || 0 }})
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-2">Code</th>
                            <th class="px-4 py-2">Nom</th>
                            <th class="px-4 py-2">Profils</th>
                            <th class="px-4 py-2">Champs</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="e in produit.ecrans || []" :key="e.code" class="border-t border-border">
                            <td class="px-4 py-2 font-mono text-xs">{{ e.code }}</td>
                            <td class="px-4 py-2">
                                <p class="font-medium">{{ e.libelle }}</p>
                                <p v-if="e.description" class="text-xs text-muted-foreground">{{ e.description }}</p>
                            </td>
                            <td class="px-4 py-2 text-muted-foreground">
                                {{ e.profils?.length ? e.profils.join(', ') : 'Tous' }}
                            </td>
                            <td class="px-4 py-2 tabular-nums">
                                {{ (produit.champs || []).filter((c) => c.ecran_code === e.code).length }}
                            </td>
                        </tr>
                        <tr v-if="!(produit.ecrans?.length)">
                            <td colspan="4" class="px-4 py-6 text-center text-muted-foreground">
                                Aucun écran — saisie en bloc unique
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="overflow-hidden rounded-xl border border-border bg-card">
                <div class="border-b border-border px-5 py-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    Champs dynamiques ({{ produit.champs?.length || 0 }})
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-2">Ordre</th>
                            <th class="px-4 py-2">Code</th>
                            <th class="px-4 py-2">Libellé</th>
                            <th class="px-4 py-2">Écran</th>
                            <th class="px-4 py-2">Type</th>
                            <th class="px-4 py-2">Règles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(c, i) in produit.champs || []" :key="c.code" class="border-t border-border">
                            <td class="px-4 py-2 tabular-nums">{{ i + 1 }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ c.code }}</td>
                            <td class="px-4 py-2">{{ c.libelle }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ c.ecran_code || '—' }}</td>
                            <td class="px-4 py-2">{{ c.type }}</td>
                            <td class="px-4 py-2 text-muted-foreground">
                                <template v-if="c.type === 'table' && c.options?.table_code">
                                    {{ c.options.table_code }}
                                    <span v-if="c.options.colonne_valeur">
                                        · {{ c.options.colonne_valeur }}/{{ c.options.colonne_libelle }}
                                    </span>
                                    <span v-if="c.obligatoire"> · obligatoire</span>
                                </template>
                                <template v-else>
                                    <span v-if="c.obligatoire">obligatoire</span>
                                    <span v-if="!c.visible">{{ c.obligatoire ? ' · ' : '' }}masqué</span>
                                    <span v-if="c.gris">{{ c.obligatoire || !c.visible ? ' · ' : '' }}grisé</span>
                                    <span v-if="!c.obligatoire && c.visible && !c.gris">—</span>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="!(produit.champs?.length)">
                            <td colspan="6" class="px-4 py-6 text-center text-muted-foreground">
                                Aucun champ dynamique
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="overflow-hidden rounded-xl border border-border bg-card">
                <div class="border-b border-border px-5 py-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    Tranches ({{ produit.tranches.length }})
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-2">Libellé</th>
                            <th class="px-4 py-2">Min</th>
                            <th class="px-4 py-2">Max</th>
                            <th class="px-4 py-2">Frais</th>
                            <th class="px-4 py-2">Taux %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(t, i) in produit.tranches" :key="i" class="border-t border-border">
                            <td class="px-4 py-2">{{ t.libelle || '—' }}</td>
                            <td class="px-4 py-2 tabular-nums">{{ fmt(t.montant_min) }}</td>
                            <td class="px-4 py-2 tabular-nums">{{ t.montant_max == null ? '∞' : fmt(t.montant_max) }}</td>
                            <td class="px-4 py-2 tabular-nums">{{ fmt(t.frais_fixe) }}</td>
                            <td class="px-4 py-2 tabular-nums">{{ fmt(t.taux) }}</td>
                        </tr>
                        <tr v-if="produit.tranches.length === 0">
                            <td colspan="5" class="px-4 py-6 text-center text-muted-foreground">Aucune tranche</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="overflow-hidden rounded-xl border border-border bg-card">
                <div class="border-b border-border px-5 py-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    Schéma comptable ({{ produit.lignes.length }})
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-2">Sens</th>
                            <th class="px-4 py-2">Compte</th>
                            <th class="px-4 py-2">Libellé</th>
                            <th class="px-4 py-2">Nature</th>
                            <th class="px-4 py-2">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(l, i) in produit.lignes" :key="i" class="border-t border-border">
                            <td class="px-4 py-2 font-semibold">{{ l.sens }}</td>
                            <td class="px-4 py-2 tabular-nums">{{ l.compte }}</td>
                            <td class="px-4 py-2">{{ l.libelle_ecriture || '—' }}</td>
                            <td class="px-4 py-2">{{ l.nature_compte }}</td>
                            <td class="px-4 py-2">{{ l.type_montant }}</td>
                        </tr>
                        <tr v-if="produit.lignes.length === 0">
                            <td colspan="5" class="px-4 py-6 text-center text-muted-foreground">Aucune ligne</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="overflow-hidden rounded-xl border border-border bg-card">
                <div class="border-b border-border px-5 py-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    Scripts ({{ produit.scripts?.length || 0 }})
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-2">Code</th>
                            <th class="px-4 py-2">Libellé</th>
                            <th class="px-4 py-2">Moteur</th>
                            <th class="px-4 py-2">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in produit.scripts || []" :key="s.code" class="border-t border-border">
                            <td class="px-4 py-2 font-mono text-xs">{{ s.code }}</td>
                            <td class="px-4 py-2">{{ s.libelle }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ s.moteur }}</td>
                            <td class="px-4 py-2">{{ s.actif ? 'actif' : 'inactif' }}</td>
                        </tr>
                        <tr v-if="!(produit.scripts?.length)">
                            <td colspan="4" class="px-4 py-6 text-center text-muted-foreground">Aucun script</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="overflow-hidden rounded-xl border border-border bg-card">
                <div class="border-b border-border px-5 py-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    Constantes ({{ produit.constantes?.length || 0 }})
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-2">Code</th>
                            <th class="px-4 py-2">Libellé</th>
                            <th class="px-4 py-2">Type</th>
                            <th class="px-4 py-2">Mode</th>
                            <th class="px-4 py-2">Référence</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in produit.constantes || []" :key="c.code" class="border-t border-border">
                            <td class="px-4 py-2 font-mono text-xs">{{ c.code }}</td>
                            <td class="px-4 py-2">{{ c.libelle }}</td>
                            <td class="px-4 py-2">{{ c.type }}</td>
                            <td class="px-4 py-2">{{ c.mode }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ c.valeur_reference || '—' }}</td>
                        </tr>
                        <tr v-if="!(produit.constantes?.length)">
                            <td colspan="5" class="px-4 py-6 text-center text-muted-foreground">Aucune constante</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="overflow-hidden rounded-xl border border-border bg-card">
                <div class="border-b border-border px-5 py-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    Historique paramétrage ({{ audits?.length || 0 }})
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-2">Date</th>
                            <th class="px-4 py-2">Action</th>
                            <th class="px-4 py-2">Résumé</th>
                            <th class="px-4 py-2">Utilisateur</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in audits || []" :key="a.id" class="border-t border-border">
                            <td class="px-4 py-2 text-xs text-muted-foreground">
                                {{ a.created_at ? new Date(a.created_at).toLocaleString('fr-FR') : '—' }}
                            </td>
                            <td class="px-4 py-2 font-mono text-xs">{{ a.action }}</td>
                            <td class="px-4 py-2">{{ a.resume || '—' }}</td>
                            <td class="px-4 py-2">{{ a.user_name || '—' }}</td>
                        </tr>
                        <tr v-if="!(audits?.length)">
                            <td colspan="4" class="px-4 py-6 text-center text-muted-foreground">
                                Aucun événement d’audit
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </AppLayout>
</template>
