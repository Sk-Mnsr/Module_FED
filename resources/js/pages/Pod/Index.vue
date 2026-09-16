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

const totalLabel = computed(() => `${props.produits.total} produit(s)`);
</script>

<template>
    <Head title="Produits divers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">Produits divers</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Paramétrage des produits (frais, TAF, schémas comptables). {{ totalLabel }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button as-child variant="outline">
                        <Link href="/pod/import">
                            <Upload class="mr-2 h-4 w-4" />
                            Importer la fiche
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link href="/pod/produits/create">
                            <Plus class="mr-2 h-4 w-4" />
                            Nouveau produit
                        </Link>
                    </Button>
                </div>
            </div>

            <div
                class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 sm:flex-row sm:items-end"
            >
                <div class="flex-1 space-y-1">
                    <Label>Recherche</Label>
                    <div class="relative">
                        <Search class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                        <Input v-model="q" class="pl-9" placeholder="Code, libellé, compte…" @keyup.enter="applyFilters" />
                    </div>
                </div>
                <div class="w-full space-y-1 sm:w-40">
                    <Label>Statut</Label>
                    <select v-model="statut" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option value="">Tous</option>
                        <option v-for="s in statuts" :key="s" :value="s">{{ labelStatutProduit(s) }}</option>
                    </select>
                </div>
                <div class="w-full space-y-1 sm:w-36">
                    <Label>Initiateur</Label>
                    <select
                        v-model="initiateur"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">Tous</option>
                        <option value="CC">CC</option>
                        <option value="OPS">OPS</option>
                        <option value="FINANCE">FINANCE</option>
                    </select>
                </div>
                <Button type="button" @click="applyFilters">Filtrer</Button>
            </div>

            <div class="rounded-xl border border-border bg-card p-4">
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="saveTaux">
                    <div class="space-y-1">
                        <Label>Taux TAF par défaut (%)</Label>
                        <Input v-model="tauxForm.taux_taf_defaut" type="number" step="0.01" min="0" class="w-40" />
                    </div>
                    <Button type="submit" variant="outline" :disabled="tauxForm.processing">Enregistrer</Button>
                    <p class="text-xs text-muted-foreground">
                        Appliqué si le produit n’a pas de taux TAF propre.
                    </p>
                </form>
            </div>

            <div class="overflow-hidden rounded-xl border border-border bg-card">
                <table class="min-w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Code</th>
                            <th class="px-4 py-3 font-medium">Libellé</th>
                            <th class="px-4 py-3 font-medium">Initiateur</th>
                            <th class="px-4 py-3 font-medium">Mode frais</th>
                            <th class="px-4 py-3 font-medium">Statut</th>
                            <th class="px-4 py-3 font-medium">Schéma</th>
                            <th class="px-4 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="p in produits.data"
                            :key="p.id"
                            class="border-t border-border hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-semibold tabular-nums">{{ p.code }}</td>
                            <td class="px-4 py-3">
                                <div>{{ p.libelle }}</div>
                                <div class="text-xs text-muted-foreground">
                                    TAF {{ p.code_taf || '—' }} · Cpt {{ p.compte_produit || '—' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ p.initiateur || '—' }}</td>
                            <td class="px-4 py-3">
                                {{ labelModeFrais(p.mode_frais) }}
                                <span v-if="p.frais_fixe != null" class="text-muted-foreground">
                                    ({{ p.frais_fixe }})
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="statutProduitClass(p.statut)"
                                >
                                    {{ labelStatutProduit(p.statut) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ p.tranches_count }} tranche(s) · {{ p.lignes_count }} ligne(s)
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="p.show_url" class="text-primary hover:underline">Voir</Link>
                                <span class="mx-2 text-muted-foreground">·</span>
                                <Link :href="p.edit_url" class="text-primary hover:underline">Modifier</Link>
                            </td>
                        </tr>
                        <tr v-if="produits.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">
                                Aucun produit. Importez la fiche Excel ou créez un produit.
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
                        class="inline-flex h-8 items-center px-2 text-sm text-muted-foreground"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
