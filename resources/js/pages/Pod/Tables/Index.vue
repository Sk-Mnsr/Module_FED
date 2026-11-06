<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Plus, Search } from 'lucide-vue-next';

type TableRow = {
    id: number;
    code: string;
    libelle: string;
    actif: boolean;
    columns_count: number;
    rows_count: number;
    show_url: string;
    edit_url: string;
};

const props = defineProps<{
    tables: {
        data: TableRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    filters: { q?: string };
}>();

const breadcrumbs = [
    { title: 'Produits divers', href: '/pod/produits' },
    { title: 'Tables sources', href: '/pod/tables' },
];

const q = ref(props.filters.q ?? '');

const applyFilters = () => {
    router.get('/pod/tables', { q: q.value || undefined }, { preserveState: true, replace: true });
};

const totalLabel = computed(() => (props.tables.total > 1 ? `${props.tables.total} tables` : `${props.tables.total} table`));
</script>

<template>
    <Head title="Tables sources — Produits divers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-full flex-1 flex-col bg-gradient-to-b from-slate-50 via-white to-white">
            <div class="flex w-full flex-1 flex-col gap-6 p-4 sm:p-6">
                <div
                    class="flex flex-col gap-4 rounded-2xl border border-slate-200/80 bg-white px-5 py-4 shadow-sm ring-1 ring-slate-900/5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p class="text-[11px] font-semibold tracking-[0.18em] text-primary uppercase">Produits divers</p>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Tables sources</h1>
                        <p class="mt-1 text-sm text-slate-500">{{ totalLabel }}</p>
                    </div>
                    <Button as-child class="h-11 px-5">
                        <Link href="/pod/tables/create">
                            <Plus class="mr-2 h-4 w-4" /> Nouvelle table
                        </Link>
                    </Button>
                </div>

                <form
                    class="flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:flex-row sm:items-end"
                    @submit.prevent="applyFilters"
                >
                    <div class="min-w-0 flex-1 space-y-1.5">
                        <label class="text-sm font-medium text-slate-700">Recherche</label>
                        <div class="relative">
                            <Search class="absolute top-3.5 left-3 h-4 w-4 text-slate-400" />
                            <Input v-model="q" class="h-11 pl-9" placeholder="Code ou libellé…" />
                        </div>
                    </div>
                    <Button type="submit" class="h-11">Filtrer</Button>
                </form>

                <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-900/5">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th class="px-5 py-3">Table</th>
                                <th class="px-5 py-3">Colonnes</th>
                                <th class="px-5 py-3">Lignes</th>
                                <th class="px-5 py-3">Statut</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="t in tables.data"
                                :key="t.id"
                                class="border-t border-slate-100 transition hover:bg-slate-50/80"
                            >
                                <td class="px-5 py-4">
                                    <span class="rounded-md bg-slate-900 px-2 py-0.5 font-mono text-xs font-semibold text-white">
                                        {{ t.code }}
                                    </span>
                                    <p class="mt-1.5 font-medium text-slate-900">{{ t.libelle }}</p>
                                </td>
                                <td class="px-5 py-4 tabular-nums text-slate-700">{{ t.columns_count }}</td>
                                <td class="px-5 py-4 tabular-nums text-slate-700">{{ t.rows_count }}</td>
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1"
                                        :class="
                                            t.actif
                                                ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                                                : 'bg-slate-100 text-slate-500 ring-slate-200'
                                        "
                                    >
                                        {{ t.actif ? 'Actif' : 'Inactif' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <Link :href="t.show_url" class="text-sm font-medium text-primary hover:underline">
                                        Ouvrir
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="tables.data.length === 0">
                                <td colspan="5" class="px-5 py-16 text-center text-slate-500">
                                    Aucune table pour cette recherche.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="tables.links.length > 3" class="flex flex-wrap gap-2">
                    <template v-for="(link, i) in tables.links" :key="i">
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
