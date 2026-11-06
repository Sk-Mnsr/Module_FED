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

const sectionShell = 'overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-900/5';
const sectionHead = 'flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-5 py-3.5';
</script>

<template>
    <Head :title="editing ? `Modifier ${table?.code}` : 'Nouvelle table source'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form
            class="flex min-h-full flex-1 flex-col bg-gradient-to-b from-slate-50 via-white to-white"
            @submit.prevent="submit"
        >
            <div class="flex w-full flex-1 flex-col gap-6 p-4 sm:p-6">
                <div
                    class="flex flex-col gap-4 rounded-2xl border border-slate-200/80 bg-white px-5 py-4 shadow-sm ring-1 ring-slate-900/5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p class="text-[11px] font-semibold tracking-[0.18em] text-primary uppercase">Tables sources</p>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">
                            {{ editing ? 'Modifier la table' : 'Nouvelle table' }}
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">
                            Code, libellé et colonnes du référentiel.
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Button as-child type="button" variant="outline" class="h-11 bg-white">
                            <Link href="/pod/tables">Annuler</Link>
                        </Button>
                        <Button type="submit" class="h-11 px-5" :disabled="form.processing">
                            {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
                        </Button>
                    </div>
                </div>

                <section :class="sectionShell">
                    <div :class="sectionHead">
                        <h2 class="text-sm font-semibold text-slate-900">Identité</h2>
                    </div>
                    <div class="grid gap-4 p-5 lg:grid-cols-4">
                        <div class="space-y-1.5">
                            <Label>Code *</Label>
                            <Input v-model="form.code" required class="h-11 font-mono uppercase" placeholder="T_PRODUITS" />
                            <p v-if="fieldError('code')" class="text-xs text-destructive">{{ fieldError('code') }}</p>
                        </div>
                        <div class="space-y-1.5 lg:col-span-2">
                            <Label>Libellé *</Label>
                            <Input v-model="form.libelle" required class="h-11" placeholder="Table client" />
                        </div>
                        <div class="flex items-end pb-2">
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input v-model="form.actif" type="checkbox" class="size-4 rounded border" />
                                Table active
                            </label>
                        </div>
                        <div class="space-y-1.5 lg:col-span-4">
                            <Label>Description</Label>
                            <textarea
                                v-model="form.description"
                                rows="3"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm outline-none focus:ring-2 focus:ring-primary/20"
                                placeholder="À quoi sert cette table"
                            />
                        </div>
                    </div>
                </section>

                <section :class="sectionShell">
                    <div :class="sectionHead">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">Colonnes</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Chaque colonne a un code technique et un libellé.</p>
                        </div>
                        <Button type="button" size="sm" @click="form.columns.push(emptyColumn())">
                            <Plus class="mr-1 h-4 w-4" /> Colonne
                        </Button>
                    </div>
                    <div class="space-y-3 p-5">
                        <p v-if="fieldError('columns')" class="text-xs text-destructive">{{ fieldError('columns') }}</p>
                        <div
                            v-if="form.columns.length"
                            class="hidden gap-3 px-1 text-[11px] font-semibold tracking-wide text-slate-500 uppercase lg:grid lg:grid-cols-[11rem_minmax(0,1fr)_5rem_2.5rem]"
                        >
                            <span>Code</span>
                            <span>Libellé</span>
                            <span>Actif</span>
                            <span />
                        </div>
                        <div
                            v-for="(c, i) in form.columns"
                            :key="i"
                            class="grid items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/40 p-3 lg:grid-cols-[11rem_minmax(0,1fr)_5rem_2.5rem]"
                        >
                            <Input v-model="c.code" placeholder="CODE" class="h-11 font-mono uppercase" />
                            <Input v-model="c.libelle" placeholder="Libellé affiché" class="h-11" />
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input v-model="c.actif" type="checkbox" class="size-4 rounded border" />
                                Actif
                            </label>
                            <Button type="button" variant="ghost" size="icon" @click="form.columns.splice(i, 1)">
                                <Trash2 class="h-4 w-4 text-destructive" />
                            </Button>
                        </div>
                        <p v-if="form.columns.length === 0" class="text-sm text-slate-500">
                            Aucune colonne. Ajoutez au moins le code et le libellé.
                        </p>
                    </div>
                </section>

                <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
                    <Button as-child type="button" variant="outline" class="h-11 bg-white">
                        <Link href="/pod/tables">Annuler</Link>
                    </Button>
                    <Button type="submit" class="h-11 px-5" :disabled="form.processing">
                        {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
                    </Button>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
