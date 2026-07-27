<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { computed } from 'vue';
import {
    ArrowLeft,
    ClipboardList,
    FileText,
    Package,
    Plus,
    Send,
    Trash2,
} from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Approvisionnement', href: '/demandes-approvisionnement' },
    { title: 'Nouvelle demande', href: '#' },
];

const form = useForm({
    motif: '',
    items: [{ designation: '', quantite: 1 }],
});

const addItem = () => {
    form.items.push({ designation: '', quantite: 1 });
};

const removeItem = (index: number) => {
    form.items.splice(index, 1);
};

const itemsCount = computed(() => form.items.length);
const filledItems = computed(() => form.items.filter((i) => i.designation.trim()).length);
const totalQuantity = computed(() =>
    form.items.reduce((sum, i) => sum + (Number(i.quantite) || 0), 0),
);

const submit = () => {
    form.post('/demandes-approvisionnement');
};
</script>

<template>
    <Head title="Nouvelle Demande d'Approvisionnement" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-0 flex-1 flex-col gap-4 p-4 lg:p-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Approvisionnement
                    </p>
                    <h1 class="text-2xl font-semibold tracking-tight text-foreground lg:text-3xl">
                        Nouvelle demande d'approvisionnement
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Saisissez le motif puis les articles et quantités souhaités.
                    </p>
                </div>
                <Button as-child variant="outline" size="sm">
                    <Link href="/demandes-approvisionnement" class="inline-flex items-center gap-2">
                        <ArrowLeft class="size-4" />
                        Retour à la liste
                    </Link>
                </Button>
            </div>

            <form
                class="grid min-h-0 flex-1 gap-4 xl:grid-cols-[minmax(0,1.55fr)_minmax(300px,0.7fr)]"
                @submit.prevent="submit"
            >
                <!-- Colonne principale -->
                <div class="flex min-h-0 flex-col gap-4 overflow-y-auto">
                    <!-- Motif -->
                    <section class="rounded-xl border border-border bg-card p-4 shadow-sm lg:p-5">
                        <div class="mb-4 flex items-center gap-3 border-b border-border pb-3">
                            <div class="rounded-lg bg-slate-100 p-2 text-slate-700">
                                <ClipboardList class="size-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-semibold text-foreground">Motif de la demande</h2>
                                <p class="text-sm text-muted-foreground">Contexte et justification du besoin</p>
                            </div>
                        </div>
                        <Label for="motif" class="sr-only">Motif</Label>
                        <textarea
                            id="motif"
                            v-model="form.motif"
                            rows="4"
                            placeholder="Ex. : Besoin de fournitures pour le département IT, renouvellement de consommables…"
                            class="min-h-[7rem] w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        />
                        <InputError :message="form.errors.motif" />
                    </section>

                    <!-- Articles -->
                    <section class="rounded-xl border border-border bg-card p-4 shadow-sm lg:p-5">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-border pb-3">
                            <div class="flex items-center gap-3">
                                <div class="rounded-lg bg-slate-100 p-2 text-slate-700">
                                    <Package class="size-5" />
                                </div>
                                <div>
                                    <h2 class="text-base font-semibold text-foreground">Articles demandés</h2>
                                    <p class="text-sm text-muted-foreground">
                                        {{ itemsCount }} ligne{{ itemsCount > 1 ? 's' : '' }}
                                    </p>
                                </div>
                            </div>
                            <Button type="button" variant="outline" size="sm" @click="addItem">
                                <Plus class="mr-1.5 size-4" />
                                Ajouter une ligne
                            </Button>
                        </div>

                        <div
                            class="mb-2 hidden grid-cols-12 gap-3 px-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground lg:grid"
                        >
                            <span class="col-span-8">Désignation</span>
                            <span class="col-span-3">Quantité</span>
                            <span class="col-span-1" />
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="(item, index) in form.items"
                                :key="index"
                                class="rounded-lg border border-border bg-muted/20 p-3 lg:grid lg:grid-cols-12 lg:items-start lg:gap-3 lg:border-0 lg:bg-transparent lg:p-0"
                            >
                                <div class="lg:col-span-8">
                                    <Label class="mb-1.5 block text-xs text-muted-foreground lg:hidden">
                                        Désignation
                                    </Label>
                                    <Input
                                        v-model="item.designation"
                                        placeholder="Ex. : Papier A4, stylo bleu, ramette…"
                                        class="h-10"
                                    />
                                    <InputError
                                        :message="
                                            form.errors[
                                                `items.${index}.designation` as keyof typeof form.errors
                                            ]
                                        "
                                    />
                                </div>
                                <div class="mt-3 lg:col-span-3 lg:mt-0">
                                    <Label class="mb-1.5 block text-xs text-muted-foreground lg:hidden">
                                        Quantité
                                    </Label>
                                    <Input
                                        v-model.number="item.quantite"
                                        type="number"
                                        min="1"
                                        class="h-10 tabular-nums"
                                    />
                                    <InputError
                                        :message="
                                            form.errors[`items.${index}.quantite` as keyof typeof form.errors]
                                        "
                                    />
                                </div>
                                <div class="mt-2 flex justify-end lg:col-span-1 lg:mt-0 lg:justify-center lg:pt-1">
                                    <Button
                                        v-if="form.items.length > 1"
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="text-muted-foreground hover:bg-red-50 hover:text-red-600"
                                        title="Retirer la ligne"
                                        @click="removeItem(index)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Panneau latéral -->
                <aside class="xl:sticky xl:top-4 xl:self-start">
                    <div class="flex flex-col gap-4 rounded-xl border border-border bg-card p-4 shadow-sm lg:p-5">
                        <div class="flex items-start gap-3 border-b border-border pb-4">
                            <div class="rounded-lg bg-slate-100 p-2 text-slate-700">
                                <FileText class="size-5" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Récapitulatif
                                </p>
                                <h2 class="text-base font-semibold text-foreground">Avant soumission</h2>
                            </div>
                        </div>

                        <dl class="space-y-3 text-sm">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <dt class="text-muted-foreground">Lignes</dt>
                                    <dd class="font-medium text-foreground">{{ itemsCount }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">Renseignées</dt>
                                    <dd class="font-medium text-foreground">{{ filledItems }}</dd>
                                </div>
                                <div class="col-span-2">
                                    <dt class="text-muted-foreground">Quantité totale</dt>
                                    <dd class="font-medium tabular-nums text-foreground">{{ totalQuantity }}</dd>
                                </div>
                            </div>
                            <div v-if="form.motif" class="border-t border-border pt-3">
                                <dt class="text-muted-foreground">Motif</dt>
                                <dd class="mt-1 line-clamp-5 whitespace-pre-line text-foreground">
                                    {{ form.motif }}
                                </dd>
                            </div>
                            <div
                                v-if="filledItems > 0"
                                class="space-y-1.5 border-t border-border pt-3"
                            >
                                <dt class="text-muted-foreground">Aperçu des articles</dt>
                                <dd>
                                    <ul class="space-y-1">
                                        <li
                                            v-for="(item, index) in form.items.filter((i) => i.designation.trim())"
                                            :key="index"
                                            class="flex items-baseline justify-between gap-2 text-foreground"
                                        >
                                            <span class="truncate">{{ item.designation }}</span>
                                            <span class="shrink-0 tabular-nums text-muted-foreground">
                                                × {{ item.quantite }}
                                            </span>
                                        </li>
                                    </ul>
                                </dd>
                            </div>
                        </dl>

                        <div class="flex flex-col gap-2 border-t border-border pt-4">
                            <Button type="submit" class="w-full" :disabled="form.processing">
                                <Send class="mr-2 size-4" />
                                {{ form.processing ? 'Envoi…' : 'Soumettre la demande' }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                class="w-full"
                                @click="router.visit('/demandes-approvisionnement')"
                            >
                                Annuler
                            </Button>
                        </div>
                    </div>
                </aside>
            </form>
        </div>
    </AppLayout>
</template>
