<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { CreditCard, Package, Plus, Upload } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Cartes', href: '/monetique/cartes/en-stock' },
    { title: 'Ajouter', href: '/monetique/cartes/ajouter' },
];

type ModeAjout = 'lot' | 'unique';
const mode = ref<ModeAjout>('lot');

const form = useForm({
    mode: 'lot' as ModeAjout,
    quantite: '' as number | '',
    premiere_carte: '',
    numero_carte: '',
    numero_lot: '',
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
    'h-9 rounded-md border-slate-300 bg-white text-slate-900 shadow-sm placeholder:text-slate-400 ' +
    'focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 ' +
    'dark:border-slate-600 dark:bg-card dark:text-foreground';

const fileTriggerClass =
    'flex h-9 w-full cursor-pointer items-center gap-2 rounded-md border border-dashed border-slate-300 bg-slate-50/80 px-3 text-sm text-slate-600 ' +
    'transition-colors hover:border-primary/40 hover:bg-primary/5 dark:border-slate-600 dark:bg-muted/40 dark:text-muted-foreground';

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
        if (!form.numero_lot.trim()) {
            form.setError('numero_lot', 'Le numéro de lot est obligatoire.');
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
        <div class="flex w-full flex-col gap-5 p-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="rounded-xl bg-primary/10 p-2.5 text-primary">
                        <CreditCard class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">Ajouter des cartes</h1>
                        <p class="mt-0.5 text-sm text-gray-600">{{ pageSubtitle }}</p>
                    </div>
                </div>
                <Button type="button" variant="outline" class="bg-white" @click="router.visit('/monetique/cartes/en-stock')">
                    Retour au stock
                </Button>
            </div>

            <form class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]" @submit.prevent="submit">
                <div class="min-w-0 space-y-5 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div
                        class="inline-flex w-full rounded-lg border border-slate-200 bg-slate-50 p-1 sm:w-auto"
                        role="tablist"
                    >
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="mode === 'lot'"
                            class="flex flex-1 items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-semibold transition-all sm:flex-none"
                            :class="mode === 'lot' ? 'bg-white text-primary shadow-sm ring-1 ring-primary/20' : 'text-muted-foreground hover:text-foreground'"
                            @click="mode = 'lot'"
                        >
                            <Package class="size-4" />
                            Lot de cartes
                        </button>
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="mode === 'unique'"
                            class="flex flex-1 items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-semibold transition-all sm:flex-none"
                            :class="mode === 'unique' ? 'bg-white text-primary shadow-sm ring-1 ring-primary/20' : 'text-muted-foreground hover:text-foreground'"
                            @click="mode = 'unique'"
                        >
                            <CreditCard class="size-4" />
                            Carte unique
                        </button>
                    </div>

                    <div class="space-y-3">
                        <p class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">Cartes</p>
                        <div v-if="mode === 'lot'" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div class="min-w-0 space-y-1.5">
                                <Label for="numero_lot" class="text-xs font-medium text-gray-600">Numéro de lot</Label>
                                <Input id="numero_lot" v-model="form.numero_lot" type="text" placeholder="Ex. LOT-2026-001" :class="inputClass" />
                                <InputError :message="form.errors.numero_lot" />
                            </div>
                            <div class="min-w-0 space-y-1.5">
                                <Label for="quantite" class="text-xs font-medium text-gray-600">Quantité</Label>
                                <Input id="quantite" v-model.number="form.quantite" type="number" min="1" placeholder="Ex. 10" :class="inputClass" />
                                <InputError :message="form.errors.quantite" />
                            </div>
                            <div class="min-w-0 space-y-1.5">
                                <Label for="premiere_carte" class="text-xs font-medium text-gray-600">Première carte</Label>
                                <Input
                                    id="premiere_carte"
                                    v-model="form.premiere_carte"
                                    type="text"
                                    placeholder="Ex. 00 12 52 25 95"
                                    class="font-mono"
                                    :class="inputClass"
                                />
                                <InputError :message="form.errors.premiere_carte" />
                            </div>
                        </div>
                        <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="min-w-0 space-y-1.5">
                                <Label for="numero_carte" class="text-xs font-medium text-gray-600">Numéro de la carte</Label>
                                <Input
                                    id="numero_carte"
                                    v-model="form.numero_carte"
                                    type="text"
                                    placeholder="Ex. 16 00 00 10 03"
                                    class="font-mono"
                                    :class="inputClass"
                                />
                                <InputError :message="form.errors.numero_carte" />
                            </div>
                            <div class="min-w-0 space-y-1.5">
                                <Label for="numero_lot_unique" class="text-xs font-medium text-gray-600">
                                    Numéro de lot
                                    <span class="font-normal text-muted-foreground">(optionnel)</span>
                                </Label>
                                <Input id="numero_lot_unique" v-model="form.numero_lot" type="text" placeholder="Ex. LOT-2026-001" :class="inputClass" />
                                <InputError :message="form.errors.numero_lot" />
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3 border-t border-gray-100 pt-4">
                        <p class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">Facture</p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="min-w-0 space-y-1.5">
                                <Label for="reference_facture" class="text-xs font-medium text-gray-600">Référence</Label>
                                <Input id="reference_facture" v-model="form.reference_facture" type="text" placeholder="Ex. DSDDGGD425" :class="inputClass" />
                                <InputError :message="form.errors.reference_facture" />
                            </div>
                            <div class="min-w-0 space-y-1.5">
                                <Label for="facture" class="text-xs font-medium text-gray-600">Fichier</Label>
                                <label :class="fileTriggerClass">
                                    <Upload class="size-4 shrink-0 text-primary" />
                                    <span class="min-w-0 flex-1 truncate">{{ form.facture?.name ?? 'Choisir un fichier…' }}</span>
                                    <input id="facture" type="file" accept=".pdf,image/*" class="sr-only" @change="onFactureChange" />
                                </label>
                                <InputError :message="form.errors.facture" />
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3 border-t border-gray-100 pt-4">
                        <p class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                            Bon de livraison
                            <span class="font-normal tracking-normal normal-case">(optionnel)</span>
                        </p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="min-w-0 space-y-1.5">
                                <Label for="reference_bon_livraison" class="text-xs font-medium text-gray-600">Référence</Label>
                                <Input
                                    id="reference_bon_livraison"
                                    v-model="form.reference_bon_livraison"
                                    type="text"
                                    placeholder="Ex. BL-2026-00123"
                                    :class="inputClass"
                                />
                                <InputError :message="form.errors.reference_bon_livraison" />
                            </div>
                            <div class="min-w-0 space-y-1.5">
                                <Label for="bon_livraison" class="text-xs font-medium text-gray-600">Fichier</Label>
                                <label :class="fileTriggerClass">
                                    <Upload class="size-4 shrink-0 text-primary" />
                                    <span class="min-w-0 flex-1 truncate">{{ form.bon_livraison?.name ?? 'Choisir un fichier…' }}</span>
                                    <input id="bon_livraison" type="file" accept=".pdf,image/*" class="sr-only" @change="onBonLivraisonChange" />
                                </label>
                                <InputError :message="form.errors.bon_livraison" />
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm xl:sticky xl:top-4">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Prix et dates</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Appliqués à chaque carte du lot.</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="prix_achat" class="text-xs font-medium text-gray-600">Prix d’achat (F CFA)</Label>
                        <Input id="prix_achat" v-model.number="form.prix_achat" type="number" min="0" step="1" placeholder="Ex. 3000" class="tabular-nums" :class="inputClass" />
                        <InputError :message="form.errors.prix_achat" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="prix_vente" class="text-xs font-medium text-gray-600">Prix de vente (F CFA)</Label>
                        <Input id="prix_vente" v-model.number="form.prix_vente" type="number" min="0" step="1" placeholder="Ex. 5000" class="tabular-nums" :class="inputClass" />
                        <InputError :message="form.errors.prix_vente" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="date_livraison" class="text-xs font-medium text-gray-600">Date de livraison</Label>
                        <Input id="date_livraison" v-model="form.date_livraison" type="date" :class="inputClass" />
                        <InputError :message="form.errors.date_livraison" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="date_expiration" class="text-xs font-medium text-gray-600">Date d’expiration</Label>
                        <Input id="date_expiration" v-model="form.date_expiration" type="date" :class="inputClass" />
                        <InputError :message="form.errors.date_expiration" />
                    </div>
                    <Button type="submit" class="w-full" :disabled="form.processing">
                        <Plus class="mr-2 size-4" />
                        {{ form.processing ? 'Enregistrement…' : 'Ajouter' }}
                    </Button>
                </aside>
            </form>
        </div>
    </AppLayout>
</template>
