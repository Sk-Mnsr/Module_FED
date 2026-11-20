<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { AlertTriangle, ChevronRight, Eye, Pencil, Plus, Search, Settings2, Upload } from 'lucide-vue-next';
import { labelModeFrais, labelStatutProduit, statutProduitClass } from '@/lib/podLabels';

type Produit = {
    id: number;
    code: string;
    libelle: string;
    type_operation: string | null;
    compte_produit: string | null;
    mode_frais: string;
    frais_fixe: string | number | null;
    taux_frais: string | number | null;
    taux_taf_effectif: number;
    taxe_champ: string | null;
    bareme_champ: string | null;
    statut: string;
    actif: boolean;
    tranches_count: number;
    ecrans_count: number;
    champs_count: number;
    operations_count: number;
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
    compteurs: Record<string, number>;
    tauxTafDefaut: number;
    statuts: string[];
}>();

const breadcrumbs = [
    { title: 'Produits divers', href: '/pod/produits' },
    { title: 'Produits', href: '/pod/produits' },
];

const q = ref(props.filters.q ?? '');
const statut = ref(props.filters.statut ?? '');

const appliquer = () => {
    router.get(
        '/pod/produits',
        { q: q.value.trim() || undefined, statut: statut.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let timer: ReturnType<typeof setTimeout> | null = null;
watch(q, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(appliquer, 350);
});

const choisirStatut = (s: string) => {
    statut.value = s;
    appliquer();
};

const ongletsStatut = computed(() => [
    { valeur: '', label: 'Tous', total: props.compteurs.tous ?? 0 },
    { valeur: 'production', label: 'Production', total: props.compteurs.production ?? 0 },
    { valeur: 'valide', label: 'Validés', total: props.compteurs.valide ?? 0 },
    { valeur: 'brouillon', label: 'Brouillons', total: props.compteurs.brouillon ?? 0 },
]);

const tauxForm = useForm({ taux_taf_defaut: props.tauxTafDefaut });
watch(
    () => props.tauxTafDefaut,
    (v) => {
        tauxForm.taux_taf_defaut = v;
    },
);
const reglageOuvert = ref(false);
const saveTaux = () => {
    tauxForm.post('/pod/parametres/taux-taf', { preserveScroll: true, onSuccess: () => (reglageOuvert.value = false) });
};

const fmt = (v: string | number | null | undefined) =>
    v === null || v === undefined || v === '' ? '—' : Number(v).toLocaleString('fr-FR');

const detailFrais = (p: Produit) => {
    switch (p.mode_frais) {
        case 'fixe':
            return `${fmt(p.frais_fixe)} XOF`;
        case 'taux':
            return `${fmt(p.taux_frais)} %`;
        case 'mixte':
            return `${fmt(p.frais_fixe)} + ${fmt(p.taux_frais)} %`;
        case 'tranche':
            return `${p.tranches_count} tranche${p.tranches_count > 1 ? 's' : ''}${p.bareme_champ ? ' · plusieurs barèmes' : ''}`;
        case 'manuel':
            return 'Saisis par l’agent';
        default:
            return '';
    }
};

const taxe = (p: Produit) => {
    if (p.taxe_champ) return 'Au choix';
    const n = Number(p.taux_taf_effectif ?? 0);
    if (p.mode_frais === 'gratuit' || n === 0) return 'Aucune';
    if (n === 10) return 'TAF 10 %';
    if (n === 18) return 'TVA 18 %';
    return `${n} %`;
};

const compteManquant = (p: Produit) => !p.compte_produit && p.mode_frais !== 'gratuit';

const totalLabel = computed(() => {
    const n = props.produits.total;
    return n > 1 ? `${n} produits` : `${n} produit`;
});
</script>

<template>
    <Head title="Produits divers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-full flex-1 flex-col bg-gradient-to-b from-slate-50 via-white to-white">
            <div class="flex w-full flex-1 flex-col gap-5 p-4 sm:p-6">
                <!-- En-tête -->
                <div
                    class="flex flex-col gap-4 rounded-2xl border border-slate-200/80 bg-white px-5 py-4 shadow-sm ring-1 ring-slate-900/5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p class="text-[11px] font-semibold tracking-[0.18em] text-primary uppercase">Produits divers</p>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Produits</h1>
                        <p class="mt-1 text-sm text-slate-500">Paramétrage des produits : écrans, frais, taxe et écritures.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" class="h-11 bg-white" @click="reglageOuvert = !reglageOuvert">
                            <Settings2 class="mr-2 h-4 w-4" /> Réglages
                        </Button>
                        <Button as-child variant="outline" class="h-11 bg-white">
                            <Link href="/pod/import"><Upload class="mr-2 h-4 w-4" /> Importer</Link>
                        </Button>
                        <Button as-child class="h-11 px-5">
                            <Link href="/pod/produits/create"><Plus class="mr-2 h-4 w-4" /> Nouveau produit</Link>
                        </Button>
                    </div>
                </div>

                <!-- Réglages du module -->
                <form
                    v-if="reglageOuvert"
                    class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200/80 bg-white px-5 py-4 shadow-sm ring-1 ring-slate-900/5"
                    @submit.prevent="saveTaux"
                >
                    <div class="space-y-1.5">
                        <label class="text-sm font-medium text-slate-700">TAF par défaut (%)</label>
                        <Input v-model="tauxForm.taux_taf_defaut" type="number" step="0.01" min="0" class="h-10 w-28" />
                    </div>
                    <Button type="submit" class="h-10" :disabled="tauxForm.processing">Enregistrer</Button>
                    <p class="pb-2.5 text-xs text-slate-500">
                        Appliquée aux produits qui n’ont pas fixé leur propre taxe.
                    </p>
                </form>

                <!-- Filtres -->
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="inline-flex w-fit flex-wrap gap-1 rounded-xl bg-slate-100/80 p-1">
                        <button
                            v-for="o in ongletsStatut"
                            :key="o.valeur"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition"
                            :class="statut === o.valeur ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:text-slate-800'"
                            @click="choisirStatut(o.valeur)"
                        >
                            {{ o.label }}
                            <span
                                class="rounded-full px-1.5 text-xs"
                                :class="statut === o.valeur ? 'bg-slate-900 text-white' : 'bg-slate-200 text-slate-600'"
                            >
                                {{ o.total }}
                            </span>
                        </button>
                    </div>
                    <div class="relative w-full lg:w-80">
                        <Search class="absolute top-3 left-3 h-4 w-4 text-slate-400" />
                        <Input v-model="q" class="h-10 bg-white pl-9" placeholder="Rechercher un code ou un libellé…" />
                    </div>
                </div>

                <!-- Liste -->
                <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-900/5">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th class="px-5 py-3">Produit</th>
                                <th class="px-5 py-3">Frais</th>
                                <th class="px-5 py-3">Taxe</th>
                                <th class="px-5 py-3">Écrans</th>
                                <th class="px-5 py-3 text-right">Opérations</th>
                                <th class="px-5 py-3">Statut</th>
                                <th class="px-5 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="p in produits.data"
                                :key="p.id"
                                class="group cursor-pointer border-t border-slate-100 transition hover:bg-slate-50/80"
                                @click="router.visit(p.show_url)"
                            >
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <span class="rounded-md bg-slate-900 px-2 py-0.5 font-mono text-xs font-semibold text-white">{{ p.code }}</span>
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-slate-900">{{ p.libelle }}</p>
                                            <p v-if="p.type_operation" class="truncate text-xs text-slate-500">{{ p.type_operation }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-medium text-slate-800">{{ labelModeFrais(p.mode_frais) }}</p>
                                    <p v-if="detailFrais(p)" class="text-xs text-slate-500">{{ detailFrais(p) }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-600">{{ taxe(p) }}</td>
                                <td class="px-5 py-4 text-slate-600">
                                    <template v-if="p.ecrans_count">
                                        {{ p.ecrans_count }} écran{{ p.ecrans_count > 1 ? 's' : '' }}
                                        <span class="text-slate-400">· {{ p.champs_count }} champ{{ p.champs_count > 1 ? 's' : '' }}</span>
                                    </template>
                                    <span v-else class="text-slate-400">Saisie simple</span>
                                </td>
                                <td class="px-5 py-4 text-right tabular-nums text-slate-700">{{ p.operations_count }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-col items-start gap-1">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statutProduitClass(p.statut)">
                                            {{ labelStatutProduit(p.statut) }}
                                        </span>
                                        <span v-if="!p.actif" class="text-xs text-slate-400">Inactif</span>
                                        <span v-if="compteManquant(p)" class="inline-flex items-center gap-1 text-xs text-amber-700">
                                            <AlertTriangle class="h-3 w-3" /> Compte produit manquant
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-4" @click.stop>
                                    <div class="flex items-center justify-end gap-1">
                                        <Button as-child variant="ghost" size="icon" class="h-8 w-8" title="Voir">
                                            <Link :href="p.show_url"><Eye class="h-4 w-4" /></Link>
                                        </Button>
                                        <Button as-child variant="ghost" size="icon" class="h-8 w-8" title="Modifier">
                                            <Link :href="p.edit_url"><Pencil class="h-4 w-4" /></Link>
                                        </Button>
                                        <ChevronRight class="h-4 w-4 text-slate-300 transition group-hover:text-slate-500" />
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="produits.data.length === 0">
                                <td colspan="7" class="px-5 py-16 text-center text-slate-500">
                                    Aucun produit pour ces filtres.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
                        <span>{{ totalLabel }}</span>
                        <div v-if="produits.last_page > 1" class="flex flex-wrap gap-1">
                            <template v-for="(link, i) in produits.links" :key="i">
                                <Button v-if="link.url" as-child size="sm" :variant="link.active ? 'default' : 'outline'" class="h-8">
                                    <Link :href="link.url" preserve-scroll v-html="link.label" />
                                </Button>
                                <span v-else class="inline-flex h-8 items-center px-2 text-slate-400" v-html="link.label" />
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
