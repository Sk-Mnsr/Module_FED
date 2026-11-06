<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Plus, Search, Upload } from 'lucide-vue-next';
import { labelModeFrais, labelStatutProduit, statutProduitClass } from '@/lib/podLabels';

type Produit = {
    id: number;
    code: string;
    libelle: string;
    code_taf: string | null;
    compte_produit: string | null;
    initiateur: string | null;
    mode_frais: string;
    frais_fixe: string | number | null;
    statut: string;
    actif: boolean;
    tranches_count: number;
    lignes_count: number;
    show_url: string;
    edit_url: string;
};

const props = defineProps<{
    produits: {
        data: Produit[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: Record<string, string>;
    tauxTafDefaut: number;
    statuts: string[];
    modesFrais: string[];
    initiateurs?: Array<{ slug: string; nom: string }>;
}>();

const breadcrumbs = [
    { title: 'Produits divers', href: '/pod/produits' },
    { title: 'Produits', href: '/pod/produits' },
];

const q = ref(props.filters.q ?? '');
const statut = ref(props.filters.statut ?? '');
const initiateur = ref(props.filters.initiateur ?? '');

const tauxForm = useForm({
    taux_taf_defaut: props.tauxTafDefaut,
});

watch(
    () => props.tauxTafDefaut,
    (v) => {
        tauxForm.taux_taf_defaut = v;
    },
);

const applyFilters = () => {
    router.get(
        '/pod/produits',
        {
            q: q.value || undefined,
            statut: statut.value || undefined,
            initiateur: initiateur.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const saveTaux = () => {
    tauxForm.post('/pod/parametres/taux-taf', { preserveScroll: true });
};

const totalLabel = computed(() => {
    const n = props.produits.total;
    return n > 1 ? `${n} produits` : `${n} produit`;
});

const initiateurNom = (slug: string | null) => {
    if (!slug) return '—';
    return props.initiateurs?.find((role) => role.slug === slug)?.nom || slug;
};

const fmtMontant = (v: string | number | null | undefined) => {
    if (v === null || v === undefined || v === '') return '';
    return Number(v).toLocaleString('fr-FR');
};

const schemaLabel = (p: Produit) => {
    const lignes = p.lignes_count > 1 ? `${p.lignes_count} lignes` : `${p.lignes_count} ligne`;
    if (p.tranches_count === 0) return lignes;
    const tranches = p.tranches_count > 1 ? `${p.tranches_count} tranches` : `${p.tranches_count} tranche`;
    return `${tranches} · ${lignes}`;
};

const selectClass =
    'h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30';
</script>

<template>
    <Head title="Produits divers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-full flex-1 flex-col bg-gradient-to-b from-slate-50 via-white to-white">
            <div class="flex w-full flex-1 flex-col gap-6 p-4 sm:p-6">
                <div
                    class="flex flex-col gap-4 rounded-2xl border border-slate-200/80 bg-white px-5 py-4 shadow-sm ring-1 ring-slate-900/5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p class="text-[11px] font-semibold tracking-[0.18em] text-primary uppercase">Produits divers</p>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Produits</h1>
                        <p class="mt-1 text-sm text-slate-500">{{ totalLabel }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button as-child variant="outline" class="h-11 bg-white">
                            <Link href="/pod/import">
                                <Upload class="mr-2 h-4 w-4" />
                                Importer
                            </Link>
                        </Button>
                        <Button as-child class="h-11 px-5">
                            <Link href="/pod/produits/create">
                                <Plus class="mr-2 h-4 w-4" />
                                Nouveau produit
                            </Link>
                        </Button>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                        <div class="min-w-0 flex-1 space-y-1.5">
                            <Label>Recherche</Label>
                            <div class="relative">
                                <Search class="absolute top-3.5 left-3 h-4 w-4 text-slate-400" />
                                <Input
                                    v-model="q"
                                    class="h-11 pl-9"
                                    placeholder="Code, libellé, compte…"
                                    @keyup.enter="applyFilters"
                                />
                            </div>
                        </div>
                        <div class="w-full space-y-1.5 sm:w-44">
                            <Label>Statut</Label>
                            <select v-model="statut" :class="selectClass">
                                <option value="">Tous</option>
                                <option v-for="s in statuts" :key="s" :value="s">{{ labelStatutProduit(s) }}</option>
                            </select>
                        </div>
                        <div class="w-full space-y-1.5 sm:w-48">
                            <Label>Initiateur</Label>
                            <select v-model="initiateur" :class="selectClass">
                                <option value="">Tous</option>
                                <option v-for="role in initiateurs || []" :key="role.slug" :value="role.slug">
                                    {{ role.nom }}
                                </option>
                            </select>
                        </div>
                        <Button type="button" class="h-11" @click="applyFilters">Filtrer</Button>
                    </div>

                    <form
                        class="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4"
                        @submit.prevent="saveTaux"
                    >
                        <div class="space-y-1.5">
                            <Label>TAF par défaut (%)</Label>
                            <Input v-model="tauxForm.taux_taf_defaut" type="number" step="0.01" min="0" class="h-11 w-28" />
                        </div>
                        <Button type="submit" variant="outline" class="h-11 bg-white" :disabled="tauxForm.processing">
                            Enregistrer
                        </Button>
                        <p class="pb-2 text-xs text-slate-500">
                            Utilisé quand le produit n’a pas choisi TAF 10 % ou TVA 18 %.
                        </p>
                    </form>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-900/5">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th class="px-5 py-3">Produit</th>
                                <th class="px-5 py-3">Initiateur</th>
                                <th class="px-5 py-3">Frais</th>
                                <th class="px-5 py-3">Statut</th>
                                <th class="px-5 py-3">Schéma</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="p in produits.data"
                                :key="p.id"
                                class="border-t border-slate-100 transition hover:bg-slate-50/80"
                            >
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-md bg-slate-900 px-2 py-0.5 font-mono text-xs font-semibold text-white">
                                            {{ p.code }}
                                        </span>
                                        <span v-if="!p.actif" class="text-xs text-slate-400">Inactif</span>
                                    </div>
                                    <p class="mt-1.5 font-medium text-slate-900">{{ p.libelle }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        TAF {{ p.code_taf || '—' }} · Compte {{ p.compte_produit || '—' }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 text-slate-700">{{ initiateurNom(p.initiateur) }}</td>
                                <td class="px-5 py-4">
                                    <p class="text-slate-900">{{ labelModeFrais(p.mode_frais) }}</p>
                                    <p v-if="fmtMontant(p.frais_fixe)" class="mt-0.5 text-xs tabular-nums text-slate-500">
                                        {{ fmtMontant(p.frais_fixe) }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="statutProduitClass(p.statut)"
                                    >
                                        {{ labelStatutProduit(p.statut) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-slate-500">{{ schemaLabel(p) }}</td>
                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    <Link :href="p.show_url" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                                        Voir
                                    </Link>
                                    <Link :href="p.edit_url" class="ml-4 text-sm font-medium text-primary hover:underline">
                                        Modifier
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="produits.data.length === 0">
                                <td colspan="6" class="px-5 py-16 text-center text-slate-500">
                                    Aucun produit pour ces filtres.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="produits.last_page > 1" class="flex flex-wrap gap-2">
                    <template v-for="(link, i) in produits.links" :key="i">
                        <Button
                            v-if="link.url"
                            as-child
                            size="sm"
                            :variant="link.active ? 'default' : 'outline'"
                        >
                            <Link :href="link.url" v-html="link.label" />
                        </Button>
                        <span
                            v-else
                            class="inline-flex h-8 items-center px-2 text-sm text-slate-400"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
