<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { CreditCard, Package, Plus, Upload } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Cartes', href: '/monetique/cartes/ajouter' },
    { title: 'Ajouter', href: '/monetique/cartes/ajouter' },
];

type ModeAjout = 'lot' | 'unique';
const mode = ref<ModeAjout>('lot');

const form = useForm({
    mode: 'lot' as ModeAjout,
    quantite: '' as number | '',
    premiere_carte: '',
    numero_carte: '',
    reference_facture: '',
    reference_bon_livraison: '',
    facture: null as File | null,
    bon_livraison: null as File | null,
    prix_vente: '' as number | '',
    prix_achat: '' as number | '',
    date_livraison: '',
    date_expiration: '',
});

const inputClass =
    'h-11 rounded-lg border-gray-200 bg-white shadow-none transition-[box-shadow,border-color] dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100 ' +
    'focus-visible:border-violet-500 focus-visible:ring-2 focus-visible:ring-violet-500/20';

const fileTriggerClass =
    'flex h-11 w-full cursor-pointer items-center gap-2 rounded-lg border border-dashed border-gray-300 bg-gray-50/80 px-3 text-sm text-gray-600 dark:border-neutral-600 dark:bg-neutral-900/60 dark:text-neutral-300 ' +
    'transition-colors hover:border-violet-300 hover:bg-violet-50/40 dark:hover:border-violet-600 dark:hover:bg-violet-950/30';

const pageSubtitle = computed(() => {
    return mode.value === 'lot' ? 'Ajout d’un lot de cartes' : 'Ajout d’une seule carte';
});

const normalizeCardNumber = (value: string) => (value || '').replace(/\s+/g, ' ').trim();

watch(mode, (m) => {
    form.mode = m;
    if (m === 'lot') {
        form.numero_carte = '';
    } else {
        form.quantite = '';
        form.premiere_carte = '';
    }
    form.clearErrors();
});

const onFactureChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    form.facture = input.files?.[0] ?? null;
    form.clearErrors('facture');
};

const onBonLivraisonChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    form.bon_livraison = input.files?.[0] ?? null;
    form.clearErrors('bon_livraison');
};

const submit = () => {
    form.clearErrors();

    if (mode.value === 'lot') {
        if (!form.quantite || Number(form.quantite) <= 0) {
            form.setError('quantite', 'La quantité est obligatoire.');
        }
        if (!normalizeCardNumber(form.premiere_carte)) {
            form.setError('premiere_carte', 'Le numéro de la première carte est obligatoire.');
        }
    } else {
        if (!normalizeCardNumber(form.numero_carte)) {
            form.setError('numero_carte', 'Le numéro de la carte est obligatoire.');
        }
    }

    if (!normalizeCardNumber(form.reference_facture)) {
        form.setError('reference_facture', 'La référence de la facture est obligatoire.');
    }
    if (!form.facture) {
        form.setError('facture', 'Veuillez joindre la facture.');
    }
    if (!form.bon_livraison) {
        form.setError('bon_livraison', 'Veuillez joindre le bon de livraison.');
    }
    if (form.prix_vente === '' || Number(form.prix_vente) < 0) {
        form.setError('prix_vente', 'Le prix de vente est obligatoire.');
    }
    if (form.prix_achat === '' || Number(form.prix_achat) < 0) {
        form.setError('prix_achat', 'Le prix d’achat est obligatoire.');
    }
    if (!form.date_livraison) {
        form.setError('date_livraison', 'La date de livraison est obligatoire.');
    }
    if (!form.date_expiration) {
        form.setError('date_expiration', 'La date d’expiration est obligatoire.');
    }

    if (Object.keys(form.errors).length > 0) return;

    form.post('/monetique/cartes', {
        forceFormData: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Monétique — Cartes — Ajouter" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex min-h-0 w-full max-w-none flex-1 flex-col bg-gradient-to-b from-slate-50/80 via-white to-violet-50/20 px-4 pb-8 pt-3 dark:from-neutral-950 dark:via-neutral-950 dark:to-violet-950/20 sm:px-6 lg:px-8"
        >
            <header class="mb-6 flex shrink-0 flex-col gap-4 border-b border-gray-200 pb-6 dark:border-neutral-800">
                <div class="flex min-w-0 items-start gap-4">
                    <div
                        class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-violet-600 text-white shadow-lg shadow-violet-600/25 dark:shadow-violet-900/40"
                    >
                        <CreditCard class="h-7 w-7" />
                    </div>
                    <div class="min-w-0 space-y-1">
                        <p class="text-xs font-semibold uppercase tracking-wider text-violet-600 dark:text-violet-400">Monétique</p>
                        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-neutral-50 sm:text-3xl">Ajouter des cartes</h1>
                        <p class="text-sm text-gray-600 dark:text-neutral-400">{{ pageSubtitle }}</p>
                    </div>
                </div>
            </header>

            <form @submit.prevent="submit" class="min-w-0">
                <div
                    class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-md shadow-gray-200/30 dark:border-neutral-800 dark:bg-neutral-900 dark:shadow-none"
                >
                    <div
                        class="flex flex-col gap-4 border-b border-gray-100 bg-gray-50/70 px-5 py-4 dark:border-neutral-800 dark:bg-neutral-950/80 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8"
                    >
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-[0.14em] text-gray-500 dark:text-neutral-400">
                                Information des cartes
                            </p>
                            <p class="mt-0.5 text-sm text-gray-600 dark:text-neutral-400">
                                Renseignez les pièces et les montants liés au stock.
                            </p>
                        </div>
                        <Button
                            type="submit"
                            class="h-11 shrink-0 bg-violet-600 px-5 text-white shadow-md shadow-violet-600/20 hover:bg-violet-700 dark:hover:bg-violet-500"
                            :disabled="form.processing"
                        >
                            <Plus class="mr-2 h-4 w-4" />
                            {{ form.processing ? 'Enregistrement…' : 'Ajouter' }}
                        </Button>
                    </div>

                    <div class="space-y-8 p-5 sm:p-6 lg:p-8">
                        <div
                            class="inline-flex w-full max-w-lg rounded-xl border border-gray-200 bg-gray-50/90 p-1 dark:border-neutral-700 dark:bg-neutral-900/80 sm:w-auto"
                            role="tablist"
                        >
                                <button
                                    type="button"
                                    role="tab"
                                    :aria-selected="mode === 'lot'"
                                    class="flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition-all"
                                    :class="
                                        mode === 'lot'
                                            ? 'bg-white text-violet-700 shadow-sm ring-1 ring-gray-200/80 dark:bg-neutral-950 dark:text-violet-400 dark:ring-neutral-700'
                                            : 'text-gray-600 hover:text-gray-900 dark:text-neutral-400 dark:hover:text-neutral-100'
                                    "
                                    @click="mode = 'lot'"
                                >
                                    <Package class="h-4 w-4 opacity-80" />
                                    Lot de cartes
                                </button>
                                <button
                                    type="button"
                                    role="tab"
                                    :aria-selected="mode === 'unique'"
                                    class="flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition-all"
                                    :class="
                                        mode === 'unique'
                                            ? 'bg-white text-violet-700 shadow-sm ring-1 ring-gray-200/80 dark:bg-neutral-950 dark:text-violet-400 dark:ring-neutral-700'
                                            : 'text-gray-600 hover:text-gray-900 dark:text-neutral-400 dark:hover:text-neutral-100'
                                    "
                                    @click="mode = 'unique'"
                                >
                                    <CreditCard class="h-4 w-4 opacity-80" />
                                    Carte unique
                                </button>
                            </div>

                            <!-- Grille principale -->
                            <div class="grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-2 xl:grid-cols-4 2xl:gap-x-8">
                                <template v-if="mode === 'lot'">
                                    <div class="space-y-2">
                                        <Label for="quantite" class="text-sm font-medium text-gray-700">Quantité</Label>
                                        <Input
                                            id="quantite"
                                            v-model.number="form.quantite"
                                            type="number"
                                            min="1"
                                            placeholder="Ex : 10"
                                            :class="inputClass"
                                        />
                                        <InputError :message="form.errors.quantite" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="premiere_carte" class="text-sm font-medium text-gray-700">
                                            Numéro de la première carte
                                        </Label>
                                        <Input
                                            id="premiere_carte"
                                            v-model="form.premiere_carte"
                                            type="text"
                                            placeholder="Ex : 00 12 52 25 95"
                                            class="font-mono tracking-wide"
                                            :class="inputClass"
                                        />
                                        <InputError :message="form.errors.premiere_carte" />
                                    </div>
                                </template>

                                <div v-else class="space-y-2 sm:col-span-2 xl:col-span-2">
                                    <Label for="numero_carte" class="text-sm font-medium text-gray-700">Numéro de la carte</Label>
                                    <Input
                                        id="numero_carte"
                                        v-model="form.numero_carte"
                                        type="text"
                                        placeholder="Ex : 16 00 00 10 03"
                                        class="font-mono tracking-wide"
                                        :class="inputClass"
                                    />
                                    <InputError :message="form.errors.numero_carte" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="reference_facture" class="text-sm font-medium text-gray-700">
                                        Référence de la facture
                                    </Label>
                                    <Input
                                        id="reference_facture"
                                        v-model="form.reference_facture"
                                        type="text"
                                        placeholder="Ex : DSDDGGD425"
                                        :class="inputClass"
                                    />
                                    <InputError :message="form.errors.reference_facture" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="facture" class="text-sm font-medium text-gray-700">Joindre la facture</Label>
                                    <label :class="fileTriggerClass">
                                        <Upload class="h-4 w-4 shrink-0 text-violet-600" />
                                        <span class="min-w-0 flex-1 truncate">
                                            {{ form.facture?.name ?? 'Choisir un fichier…' }}
                                        </span>
                                        <input
                                            id="facture"
                                            type="file"
                                            accept=".pdf,image/*"
                                            class="sr-only"
                                            @change="onFactureChange"
                                        />
                                    </label>
                                    <InputError :message="form.errors.facture" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="reference_bon_livraison" class="text-sm font-medium text-gray-700">
                                        Référence du bon de livraison <span class="font-normal text-gray-500">(optionnel)</span>
                                    </Label>
                                    <Input
                                        id="reference_bon_livraison"
                                        v-model="form.reference_bon_livraison"
                                        type="text"
                                        placeholder="Ex : BL-2026-00123"
                                        :class="inputClass"
                                    />
                                    <InputError :message="form.errors.reference_bon_livraison" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="bon_livraison" class="text-sm font-medium text-gray-700">
                                        Joindre le bon de livraison
                                    </Label>
                                    <label :class="fileTriggerClass">
                                        <Upload class="h-4 w-4 shrink-0 text-violet-600" />
                                        <span class="min-w-0 flex-1 truncate">
                                            {{ form.bon_livraison?.name ?? 'Choisir un fichier…' }}
                                        </span>
                                        <input
                                            id="bon_livraison"
                                            type="file"
                                            accept=".pdf,image/*"
                                            class="sr-only"
                                            @change="onBonLivraisonChange"
                                        />
                                    </label>
                                    <InputError :message="form.errors.bon_livraison" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="prix_achat" class="text-sm font-medium text-gray-700">Prix d’achat (F CFA)</Label>
                                    <Input
                                        id="prix_achat"
                                        v-model.number="form.prix_achat"
                                        type="number"
                                        min="0"
                                        step="1"
                                        placeholder="Ex : 3000"
                                        class="tabular-nums"
                                        :class="inputClass"
                                    />
                                    <InputError :message="form.errors.prix_achat" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="prix_vente" class="text-sm font-medium text-gray-700">Prix de vente (F CFA)</Label>
                                    <Input
                                        id="prix_vente"
                                        v-model.number="form.prix_vente"
                                        type="number"
                                        min="0"
                                        step="1"
                                        placeholder="Ex : 5000"
                                        class="tabular-nums"
                                        :class="inputClass"
                                    />
                                    <InputError :message="form.errors.prix_vente" />
                                </div>
                            </div>

                            <!-- Dates -->
                            <div class="border-t border-gray-100 pt-8 dark:border-neutral-800">
                                <p class="mb-4 text-xs font-bold uppercase tracking-[0.14em] text-gray-500 dark:text-neutral-400">Dates</p>
                                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                    <div class="space-y-2 sm:col-span-1 lg:col-span-2">
                                        <Label for="date_livraison" class="text-sm font-medium text-gray-700">
                                            Date de livraison
                                        </Label>
                                        <Input id="date_livraison" v-model="form.date_livraison" type="date" :class="inputClass" />
                                        <InputError :message="form.errors.date_livraison" />
                                    </div>
                                    <div class="space-y-2 sm:col-span-1 lg:col-span-2">
                                        <Label for="date_expiration" class="text-sm font-medium text-gray-700">
                                            Date d’expiration
                                        </Label>
                                        <Input id="date_expiration" v-model="form.date_expiration" type="date" :class="inputClass" />
                                        <InputError :message="form.errors.date_expiration" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pied (mobile / rappel action) -->
                        <div class="flex justify-end border-t border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-neutral-800 dark:bg-neutral-950/50 sm:hidden lg:px-8">
                            <Button
                                type="submit"
                                class="h-11 w-full bg-violet-600 text-white hover:bg-violet-700"
                                :disabled="form.processing"
                            >
                                <Plus class="mr-2 h-4 w-4" />
                                {{ form.processing ? 'Enregistrement…' : 'Ajouter' }}
                            </Button>
                        </div>
                    </div>
                </form>
        </div>
    </AppLayout>
</template>
