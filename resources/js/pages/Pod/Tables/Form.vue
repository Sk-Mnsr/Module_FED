<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Plus, Trash2 } from 'lucide-vue-next';

type Column = {
    code: string;
    libelle: string;
    actif: boolean;
};

type TableData = {
    id?: number;
    code: string;
    libelle: string;
    description?: string | null;
    actif: boolean;
    columns: Column[];
};

const props = defineProps<{
    table: TableData | null;
}>();

const editing = computed(() => !!props.table?.id);

const breadcrumbs = computed(() => [
    { title: 'Produits divers', href: '/pod/produits' },
    { title: 'Tables sources', href: '/pod/tables' },
    {
        title: editing.value ? `Modifier ${props.table?.code}` : 'Nouvelle table',
        href: editing.value ? `/pod/tables/${props.table?.id}/edit` : '/pod/tables/create',
    },
]);

const emptyColumn = (): Column => ({ code: '', libelle: '', actif: true });

const form = useForm({
    code: props.table?.code ?? '',
    libelle: props.table?.libelle ?? '',
    description: props.table?.description ?? '',
    actif: props.table?.actif ?? true,
    columns: (props.table?.columns?.length
        ? props.table.columns.map((c) => ({ ...c }))
        : [emptyColumn(), emptyColumn()]) as Column[],
});

const submit = () => {
    if (editing.value) {
        form.put(`/pod/tables/${props.table!.id}`);
    } else {
        form.post('/pod/tables');
    }
};

const fieldError = (key: string) => (form.errors as Record<string, string>)[key];
</script>

<template>
    <Head :title="editing ? `Modifier ${table?.code}` : 'Nouvelle table source'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="mx-auto flex max-w-3xl flex-col gap-6 p-6" @submit.prevent="submit">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">
                        {{ editing ? 'Modifier la table' : 'Nouvelle table source' }}
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Définissez le code technique et les colonnes (ex. CODE_PRODUIT, LIBELLE_PRODUIT).
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button as-child type="button" variant="outline">
                        <Link href="/pod/tables">Annuler</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing">Enregistrer</Button>
                </div>
            </div>

            <section class="space-y-4 rounded-xl border border-border bg-card p-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label>Code *</Label>
                        <Input v-model="form.code" required class="font-mono uppercase" placeholder="T_PRODUITS" />
                        <p v-if="fieldError('code')" class="text-xs text-destructive">{{ fieldError('code') }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label>Libellé *</Label>
                        <Input v-model="form.libelle" required />
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <Label>Description</Label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.actif" type="checkbox" class="size-4 rounded border" />
                        Table active
                    </label>
                </div>
            </section>

            <section class="space-y-3 rounded-xl border border-border bg-card p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Colonnes</h2>
                    <Button type="button" size="sm" variant="outline" @click="form.columns.push(emptyColumn())">
                        <Plus class="mr-1 h-4 w-4" /> Colonne
                    </Button>
                </div>
                <p v-if="fieldError('columns')" class="text-xs text-destructive">{{ fieldError('columns') }}</p>
                <div
                    v-for="(c, i) in form.columns"
                    :key="i"
                    class="grid gap-2 sm:grid-cols-[1fr_1.4fr_auto_auto]"
                >
                    <Input v-model="c.code" placeholder="CODE" class="font-mono uppercase" />
                    <Input v-model="c.libelle" placeholder="Libellé" />
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="c.actif" type="checkbox" class="size-4 rounded border" />
                        Actif
                    </label>
                    <Button type="button" variant="ghost" size="icon" @click="form.columns.splice(i, 1)">
                        <Trash2 class="h-4 w-4 text-destructive" />
                    </Button>
                </div>
            </section>

            <div class="flex justify-end gap-2">
                <Button as-child type="button" variant="outline">
                    <Link href="/pod/tables">Annuler</Link>
                </Button>
                <Button type="submit" :disabled="form.processing">Enregistrer</Button>
            </div>
        </form>
    </AppLayout>
</template>
