<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Pencil, Trash2 } from 'lucide-vue-next';

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
};

const props = defineProps<{
    produit: Produit;
    tauxTafDefaut: number;
}>();

const breadcrumbs = [
    { title: 'POD', href: '/pod/produits' },
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
    <Head :title="`POD ${produit.code}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-5xl flex-col gap-6 p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm text-muted-foreground">Code {{ produit.code }} · TAF {{ produit.code_taf || '—' }}</p>
                    <h1 class="text-2xl font-bold">{{ produit.libelle }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ produit.type_operation || 'Opération diverse' }} · {{ produit.devise }} ·
                        Initiateur {{ produit.initiateur || '—' }}
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
                            <option value="brouillon">brouillon</option>
                            <option value="valide">valide</option>
                            <option value="production">production</option>
                        </select>
                        <Button size="sm" type="submit" :disabled="statutForm.processing">OK</Button>
                    </form>
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
        </div>
    </AppLayout>
</template>
