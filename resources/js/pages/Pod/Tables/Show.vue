<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pencil, Plus, Trash2, Upload } from 'lucide-vue-next';

type Column = { id?: number; code: string; libelle: string; actif: boolean };
type Row = { id: number; data: Record<string, string>; actif: boolean };

const props = defineProps<{
    table: {
        id: number;
        code: string;
        libelle: string;
        description: string | null;
        actif: boolean;
        columns: Column[];
        rows: Row[];
        rows_total: number;
    };
}>();

const page = usePage();
const flashSuccess = computed(() => (page.props.flash as { success?: string } | undefined)?.success);
const flashWarning = computed(() => (page.props.flash as { warning?: string } | undefined)?.warning);

const breadcrumbs = [
    { title: 'Produits divers', href: '/pod/produits' },
    { title: 'Tables sources', href: '/pod/tables' },
    { title: props.table.code, href: `/pod/tables/${props.table.id}` },
];

const importForm = useForm({
    fichier: null as File | null,
    replace: true,
});

const rowDraft = reactive<Record<string, string>>({});
for (const c of props.table.columns) {
    rowDraft[c.code] = '';
}

const onFile = (e: Event) => {
    const input = e.target as HTMLInputElement;
    importForm.fichier = input.files?.[0] ?? null;
};

const submitImport = () => {
    importForm.post(`/pod/tables/${props.table.id}/import`, { forceFormData: true });
};

const addRow = () => {
    router.post(`/pod/tables/${props.table.id}/rows`, { data: { ...rowDraft }, actif: true }, {
        onSuccess: () => {
            for (const c of props.table.columns) rowDraft[c.code] = '';
        },
    });
};

const destroyRow = (id: number) => {
    if (!confirm('Supprimer cette ligne ?')) return;
    router.delete(`/pod/tables/${props.table.id}/rows/${id}`);
};

const destroyTable = () => {
    if (!confirm(`Supprimer la table ${props.table.code} ?`)) return;
    router.delete(`/pod/tables/${props.table.id}`);
};
</script>

<template>
    <Head :title="`Table ${table.code}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-6xl flex-col gap-6 p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-mono text-xs text-muted-foreground">{{ table.code }}</p>
                    <h1 class="text-2xl font-bold">{{ table.libelle }}</h1>
                    <p v-if="table.description" class="mt-1 text-sm text-muted-foreground">
                        {{ table.description }}
                    </p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ table.columns.length }} colonne(s) · {{ table.rows_total }} ligne(s)
                        <span v-if="table.rows.length < table.rows_total">
                            (aperçu {{ table.rows.length }})
                        </span>
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button as-child variant="outline">
                        <Link :href="`/pod/tables/${table.id}/edit`">
                            <Pencil class="mr-2 h-4 w-4" /> Modifier
                        </Link>
                    </Button>
                    <Button variant="destructive" @click="destroyTable">
                        <Trash2 class="mr-2 h-4 w-4" /> Supprimer
                    </Button>
                </div>
            </div>

            <div
                v-if="flashSuccess"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            >
                {{ flashSuccess }}
            </div>
            <div
                v-if="flashWarning"
                class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"
            >
                {{ flashWarning }}
            </div>

            <section class="rounded-xl border border-border bg-card p-5">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    Import Excel / CSV
                </h2>
                <p class="mb-3 text-sm text-muted-foreground">
                    Première ligne = en-têtes (codes colonnes). Les nouvelles colonnes sont créées automatiquement.
                </p>
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="submitImport">
                    <div class="space-y-1">
                        <Label>Fichier</Label>
                        <Input type="file" accept=".xlsx,.xls,.csv" @change="onFile" />
                    </div>
                    <label class="flex items-center gap-2 pb-2 text-sm">
                        <input v-model="importForm.replace" type="checkbox" class="size-4 rounded border" />
                        Remplacer les lignes existantes
                    </label>
                    <Button type="submit" :disabled="importForm.processing || !importForm.fichier">
                        <Upload class="mr-2 h-4 w-4" />
                        Importer
                    </Button>
                </form>
            </section>

            <section class="space-y-3 rounded-xl border border-border bg-card p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    Ajouter une ligne
                </h2>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="c in table.columns" :key="c.code" class="space-y-1">
                        <Label>{{ c.libelle }} <span class="font-mono text-xs text-muted-foreground">({{ c.code }})</span></Label>
                        <Input v-model="rowDraft[c.code]" />
                    </div>
                </div>
                <Button type="button" size="sm" @click="addRow" :disabled="!table.columns.length">
                    <Plus class="mr-1 h-4 w-4" /> Ajouter
                </Button>
            </section>

            <section class="overflow-hidden rounded-xl border border-border bg-card">
                <div class="border-b border-border px-5 py-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    Données
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th
                                    v-for="c in table.columns"
                                    :key="c.code"
                                    class="px-4 py-2 font-mono text-xs"
                                >
                                    {{ c.code }}
                                </th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in table.rows" :key="r.id" class="border-t border-border">
                                <td v-for="c in table.columns" :key="c.code" class="px-4 py-2">
                                    {{ r.data[c.code] ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <Button type="button" variant="ghost" size="icon" @click="destroyRow(r.id)">
                                        <Trash2 class="h-4 w-4 text-destructive" />
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="table.rows.length === 0">
                                <td
                                    :colspan="table.columns.length + 1"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    Aucune donnée — importez un fichier ou ajoutez une ligne.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
