<script setup lang="ts">
import { ref } from 'vue';
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
</script>

<template>
    <Head title="Tables sources — Produits divers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-5xl flex-col gap-6 p-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">Tables sources</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Référentiels importés pour alimenter les champs de type Table.
                    </p>
                </div>
                <Button as-child>
                    <Link href="/pod/tables/create">
                        <Plus class="mr-2 h-4 w-4" /> Nouvelle table
                    </Link>
                </Button>
            </div>

            <form class="flex gap-2" @submit.prevent="applyFilters">
                <div class="relative flex-1">
                    <Search class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                    <Input v-model="q" class="pl-9" placeholder="Rechercher code ou libellé…" />
                </div>
                <Button type="submit" variant="outline">Filtrer</Button>
            </form>

            <div class="overflow-hidden rounded-xl border border-border bg-card">
                <table class="min-w-full text-sm">
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-3">Code</th>
                            <th class="px-4 py-3">Libellé</th>
                            <th class="px-4 py-3">Colonnes</th>
                            <th class="px-4 py-3">Lignes</th>
                            <th class="px-4 py-3">Statut</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in tables.data" :key="t.id" class="border-t border-border">
                            <td class="px-4 py-3 font-mono text-xs font-semibold">{{ t.code }}</td>
                            <td class="px-4 py-3">{{ t.libelle }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ t.columns_count }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ t.rows_count }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="t.actif ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                                >
                                    {{ t.actif ? 'Actif' : 'Inactif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button as-child size="sm" variant="outline">
                                    <Link :href="t.show_url">Ouvrir</Link>
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="tables.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">
                                Aucune table — créez-en une ou importez un fichier Excel/CSV.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
