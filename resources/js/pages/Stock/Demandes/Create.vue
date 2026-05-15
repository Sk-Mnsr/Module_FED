<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { Plus, Trash2, Package, ArrowLeft } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Approvisionnement',
        href: '/demandes-approvisionnement',
    },
    {
        title: 'Nouvelle demande',
        href: '#',
    },
];

const form = useForm({
    motif: '',
    items: [
        { designation: '', quantite: 1 }
    ],
});

const addItem = () => {
    form.items.push({ designation: '', quantite: 1 });
};

const removeItem = (index: number) => {
    form.items.splice(index, 1);
};

const submit = () => {
    form.post('/demandes-approvisionnement');
};
</script>

<template>
    <Head title="Nouvelle Demande d'Approvisionnement" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-0 w-full max-w-none flex-1 flex-col gap-6 px-4 pb-8 pt-3 sm:px-6 lg:px-8">
            <!-- En-tête pleine largeur -->
            <header class="flex shrink-0 flex-col gap-4 border-b border-gray-200 pb-6 dark:border-neutral-800 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex min-w-0 flex-1 items-start gap-4">
                    <div class="shrink-0 rounded-2xl bg-blue-600 p-3 text-white shadow-md shadow-blue-600/25 dark:bg-blue-600 dark:shadow-blue-900/40">
                        <Package class="h-7 w-7" />
                    </div>
                    <div class="min-w-0 space-y-1">
                        <p class="text-xs font-semibold uppercase tracking-wider text-blue-600 dark:text-blue-400">Approvisionnement</p>
                        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-neutral-50 sm:text-3xl">
                            Nouvelle demande d'approvisionnement
                        </h1>
                        <p class="max-w-3xl text-sm text-gray-500 dark:text-neutral-400 lg:max-w-none">
                            Saisissez le motif puis les articles et quantités souhaités. Vous pouvez ajouter autant de lignes que nécessaire.
                        </p>
                    </div>
                </div>
                <Button
                    as-child
                    variant="outline"
                    class="shrink-0 border-gray-200 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:bg-neutral-800"
                >
                    <Link href="/demandes-approvisionnement" class="inline-flex items-center gap-2">
                        <ArrowLeft class="h-4 w-4" />
                        Retour à la liste
                    </Link>
                </Button>
            </header>

            <form @submit.prevent="submit" class="flex min-h-0 flex-1 flex-col gap-6">
                <!-- Motif -->
                <div class="shrink-0 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900 sm:p-6">
                    <Label for="motif" class="mb-3 block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-neutral-400">
                        Motif de la demande
                    </Label>
                    <textarea
                        id="motif"
                        v-model="form.motif"
                        rows="4"
                        placeholder="Ex. : Besoin de fournitures pour le département IT, renouvellement de consommables…"
                        class="min-h-[7.5rem] w-full resize-y rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100 dark:placeholder:text-neutral-500"
                    />
                    <InputError :message="form.errors.motif" />
                </div>

                <!-- Articles -->
                <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="flex shrink-0 flex-col gap-3 border-b border-gray-100 p-5 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                        <Label class="text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-neutral-400">
                            Articles demandés
                        </Label>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="shrink-0 gap-2 border-blue-200 text-blue-700 hover:bg-blue-50 dark:border-blue-800 dark:text-blue-300 dark:hover:bg-blue-950/50"
                            @click="addItem"
                        >
                            <Plus class="h-4 w-4" />
                            Ajouter une ligne
                        </Button>
                    </div>

                    <!-- En-têtes colonnes (desktop) -->
                    <div class="hidden border-b border-gray-100 bg-gray-50/90 px-5 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-neutral-800 dark:bg-neutral-950/80 dark:text-neutral-400 lg:grid lg:grid-cols-12 lg:gap-4 lg:px-6">
                        <span class="lg:col-span-8">Désignation</span>
                        <span class="lg:col-span-3">Quantité</span>
                        <span class="lg:col-span-1 text-right"> </span>
                    </div>

                    <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-5 sm:p-6">
                        <div
                            v-for="(item, index) in form.items"
                            :key="index"
                            class="rounded-xl border border-gray-100 bg-gray-50/80 p-4 transition-colors dark:border-neutral-800 dark:bg-neutral-950/50 lg:grid lg:grid-cols-12 lg:items-end lg:gap-4 lg:border-0 lg:bg-transparent lg:p-0"
                        >
                            <div class="lg:col-span-8">
                                <Label class="mb-1.5 block text-xs font-medium text-gray-500 lg:hidden dark:text-neutral-400">
                                    Désignation de l'article
                                </Label>
                                <Input
                                    v-model="item.designation"
                                    placeholder="Ex. : Papier A4, stylo bleu, ramette…"
                                    class="h-10 w-full border-gray-200 text-sm dark:border-neutral-700 dark:bg-neutral-950"
                                />
                                <InputError :message="form.errors[`items.${index}.designation`]" />
                            </div>
                            <div class="mt-4 lg:col-span-3 lg:mt-0">
                                <Label class="mb-1.5 block text-xs font-medium text-gray-500 lg:hidden dark:text-neutral-400">
                                    Quantité
                                </Label>
                                <Input
                                    v-model.number="item.quantite"
                                    type="number"
                                    min="1"
                                    class="h-10 w-full max-w-full border-gray-200 text-sm tabular-nums dark:border-neutral-700 dark:bg-neutral-950 lg:max-w-none"
                                />
                                <InputError :message="form.errors[`items.${index}.quantite`]" />
                            </div>
                            <div class="mt-3 flex justify-end lg:col-span-1 lg:mt-0 lg:justify-center">
                                <Button
                                    v-if="form.items.length > 1"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40 dark:hover:text-red-400"
                                    @click="removeItem(index)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="sticky bottom-0 z-10 -mx-4 mt-auto flex shrink-0 flex-col-reverse items-stretch gap-3 border-t border-gray-200 bg-background/95 px-4 py-4 backdrop-blur supports-[backdrop-filter]:bg-background/80 dark:border-neutral-800 sm:mx-0 sm:flex-row sm:justify-end sm:px-0 sm:py-0 sm:pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="border-gray-200 px-6 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:bg-neutral-800"
                        @click="router.visit('/demandes-approvisionnement')"
                    >
                        Annuler
                    </Button>
                    <Button
                        type="submit"
                        class="bg-blue-600 px-8 text-white shadow-md shadow-blue-600/20 hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500 dark:shadow-blue-900/40"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Envoi…' : 'Soumettre la demande' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
