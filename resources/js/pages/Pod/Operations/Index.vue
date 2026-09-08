<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Plus, Search, Eye } from 'lucide-vue-next';

type Op = {
    id: number;
    reference: string | null;
    compte_client: string;
    nom_client: string | null;
    produit_code: string | null;
    produit_libelle: string | null;
    frais_ht: string | number;
    montant_taf: string | number;
    total_client: string | number;
    devise: string;
    statut: string;
    user_name: string | null;
    created_at: string | null;
    show_url: string;
};

const props = defineProps<{
    operations: {
        data: Op[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: Record<string, string>;
}>();

const breadcrumbs = [
    { title: 'POD', href: '/pod/produits' },
    { title: 'Opérations', href: '/pod/operations' },
];

const q = ref(props.filters.q ?? '');
const statut = ref(props.filters.statut ?? '');

const applyFilters = () => {
    router.get(
        '/pod/operations',
        { q: q.value || undefined, statut: statut.value || undefined },
        { preserveState: true, replace: true },
    );
};

const fmt = (v: string | number | null | undefined) => {
    if (v === null || v === undefined || v === '') return '—';
    return Number(v).toLocaleString('fr-FR');
};

const statutClass = (s: string) => {
    if (s === 'valide') return 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200';
    if (s === 'annule') return 'bg-slate-100 text-slate-600 ring-1 ring-slate-200';
    return 'bg-amber-50 text-amber-900 ring-1 ring-amber-200';
};

const fieldClass =
    'h-11 border-slate-300 bg-white text-slate-900 shadow-sm placeholder:text-slate-400 focus-visible:border-primary focus-visible:ring-primary/30';
</script>

<template>
    <Head title="Opérations POD" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-[calc(100vh-4rem)] bg-gradient-to-b from-slate-50 via-white to-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div class="flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.2em] text-primary uppercase">POD</p>
                        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">Opérations</h1>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ operations.total }} opération(s) enregistrée(s)
                        </p>
                    </div>
                    <Button as-child class="h-11">
                        <Link href="/pod/operations/create">
                            <Plus class="mr-2 h-4 w-4" /> Nouvelle opération
                        </Link>
                    </Button>
                </div>

                <div
                    class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end"
                >
                    <div class="flex-1 space-y-1.5">
                        <Label class="text-slate-700">Recherche</Label>
                        <div class="relative">
                            <Search class="absolute top-3 left-3 h-4 w-4 text-slate-400" />
                            <Input
                                v-model="q"
                                :class="fieldClass + ' pl-9'"
                                placeholder="Compte, produit, référence, client…"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                    </div>
                    <div class="w-full space-y-1.5 sm:w-44">
                        <Label class="text-slate-700">Statut</Label>
                        <select
                            v-model="statut"
                            class="h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm"
                        >
                            <option value="">Tous</option>
                            <option value="brouillon">brouillon</option>
                            <option value="valide">valide</option>
                            <option value="annule">annule</option>
                        </select>
                    </div>
                    <Button type="button" class="h-11" @click="applyFilters">Filtrer</Button>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th class="px-5 py-3 font-medium">Produit</th>
                                <th class="px-5 py-3 font-medium">Client</th>
                                <th class="px-5 py-3 font-medium text-right">Frais HT</th>
                                <th class="px-5 py-3 font-medium text-right">TAF</th>
                                <th class="px-5 py-3 font-medium text-right">Total</th>
                                <th class="px-5 py-3 font-medium">Statut</th>
                                <th class="px-5 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="op in operations.data"
                                :key="op.id"
                                class="border-t border-slate-100 transition hover:bg-slate-50/80"
                            >
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-slate-900">
                                        <span class="text-primary">{{ op.produit_code }}</span>
                                    </div>
                                    <div class="text-xs text-slate-500">{{ op.produit_libelle }}</div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-medium tabular-nums text-slate-900">{{ op.compte_client }}</div>
                                    <div class="text-xs text-slate-500">{{ op.nom_client || '—' }}</div>
                                </td>
                                <td class="px-5 py-4 text-right tabular-nums text-slate-700">{{ fmt(op.frais_ht) }}</td>
                                <td class="px-5 py-4 text-right tabular-nums text-slate-700">{{ fmt(op.montant_taf) }}</td>
                                <td class="px-5 py-4 text-right">
                                    <span class="font-semibold tabular-nums text-slate-900">{{ fmt(op.total_client) }}</span>
                                    <span class="ml-1 text-xs text-slate-500">{{ op.devise }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="statutClass(op.statut)"
                                    >
                                        {{ op.statut }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <Link
                                        :href="op.show_url"
                                        class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                                    >
                                        <Eye class="h-3.5 w-3.5" /> Voir
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="operations.data.length === 0">
                                <td colspan="7" class="px-5 py-16 text-center">
                                    <p class="text-slate-500">Aucune opération pour le moment.</p>
                                    <Button as-child class="mt-4">
                                        <Link href="/pod/operations/create">Créer une opération</Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="operations.last_page > 1" class="flex flex-wrap gap-2">
                    <template v-for="(link, i) in operations.links" :key="i">
                        <Button
                            v-if="link.url"
                            as-child
                            size="sm"
                            :variant="link.active ? 'default' : 'outline'"
                        >
                            <Link :href="link.url" v-html="link.label" />
                        </Button>
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
