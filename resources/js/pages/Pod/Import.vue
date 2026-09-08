<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Upload } from 'lucide-vue-next';

defineProps<{
    templateHint: string;
}>();

const breadcrumbs = [
    { title: 'POD', href: '/pod/produits' },
    { title: 'Import', href: '/pod/import' },
];

const form = useForm({
    fichier: null as File | null,
});

const onFile = (e: Event) => {
    const input = e.target as HTMLInputElement;
    form.fichier = input.files?.[0] ?? null;
};

const submit = () => {
    form.post('/pod/import', { forceFormData: true });
};
</script>

<template>
    <Head title="Import fiche POD" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-2xl flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold">Importer la fiche de paramétrage</h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Chargez le fichier Excel (ex. « FICHE PARAMETRAGE APPLICATION DE FORMULAIRE »). Les
                    produits sont créés ou mis à jour par code, avec tranches et schéma comptable par
                    défaut.
                </p>
            </div>

            <div class="rounded-xl border border-border bg-card p-5 text-sm text-muted-foreground">
                {{ templateHint }}
            </div>

            <form class="space-y-4 rounded-xl border border-border bg-card p-5" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label for="fichier">Fichier Excel (.xlsx)</Label>
                    <Input id="fichier" type="file" accept=".xlsx,.xls,.csv" @change="onFile" />
                    <p v-if="form.errors.fichier" class="text-sm text-destructive">{{ form.errors.fichier }}</p>
                </div>
                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing || !form.fichier">
                        <Upload class="mr-2 h-4 w-4" />
                        Importer
                    </Button>
                    <Button as-child type="button" variant="outline">
                        <Link href="/pod/produits">Annuler</Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
