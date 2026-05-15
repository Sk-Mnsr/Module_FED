<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ExpirationBar from '@/components/ExpirationBar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCardNumberDisplay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    ArrowLeft,
    CreditCard,
    Eraser,
    FileText,
    Layers,
    ListChecks,
    Save,
} from 'lucide-vue-next';

type RefFactureRow = {
    reference_facture: string;
    cards_count: number;
};

type CarteLotRow = {
    id: number;
    numero_carte: string;
    prix_vente: number;
    expiration?: string | null;
    date_expiration?: string | null;
};

const props = defineProps<{
    references: RefFactureRow[];
    cartesLot: CarteLotRow[];
    referenceCourante: string | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Cartes', href: '/monetique/cartes/en-stock' },
    { title: 'Modifier prix', href: '/monetique/cartes/modifier-prix' },
];

const referenceSelection = ref(props.referenceCourante ?? '');
const selectedIds = ref<number[]>([]);

const selectClass =
    'flex h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-900 shadow-none ' +
    'focus-visible:outline-none focus-visible:border-violet-500 focus-visible:ring-2 focus-visible:ring-violet-500/20';

const inputClass =
    'h-11 rounded-lg border-gray-200 bg-white shadow-none tabular-nums ' +
    'focus-visible:border-violet-500 focus-visible:ring-2 focus-visible:ring-violet-500/20';

watch(
    () => props.referenceCourante,
    (v) => {
        referenceSelection.value = v ?? '';
        selectedIds.value = [];
    },
);

watch(
    () => props.cartesLot,
    () => {
        selectedIds.value = [];
    },
    { deep: true },
);

const allLotIds = computed(() => props.cartesLot.map((c) => c.id));

const allSelected = computed({
    get() {
        if (allLotIds.value.length === 0) {
            return false;
        }
        return allLotIds.value.every((id) => selectedIds.value.includes(id));
    },
    set(on: boolean) {
        selectedIds.value = on ? [...allLotIds.value] : [];
    },
});

const formatCfa = (n: number) => `${n.toLocaleString('fr-FR')} F CFA`;

const onReferenceChange = () => {
    const q = referenceSelection.value.trim();
    router.get(
        '/monetique/cartes/modifier-prix',
        q ? { reference_facture: q } : {},
        { preserveState: true, replace: true, only: ['references', 'cartesLot', 'referenceCourante'] },
    );
};

const form = useForm({
    reference_facture: '',
    card_ids: [] as number[],
    prix_vente: '' as number | '',
});

const reset = () => {
    form.prix_vente = '';
    form.clearErrors();
    selectedIds.value = [];
};

const submit = () => {
    form.clearErrors();

    const refFacture = props.referenceCourante?.trim() ?? '';
    if (!refFacture) {
        form.setError('reference_facture', 'Choisissez d’abord une référence de facture.');
        return;
    }

    if (selectedIds.value.length === 0) {
        form.setError('card_ids', 'Sélectionnez au moins une carte du lot.');
        return;
    }

    if (form.prix_vente === '' || Number(form.prix_vente) < 0) {
        form.setError('prix_vente', 'Veuillez saisir un prix valide.');
        return;
    }

    form.reference_facture = refFacture;
    form.card_ids = [...selectedIds.value];
    form.put('/monetique/cartes/prix', {
        preserveScroll: true,
    });
};

const toggleId = (id: number) => {
    const set = new Set(selectedIds.value);
    if (set.has(id)) {
        set.delete(id);
    } else {
        set.add(id);
    }
    selectedIds.value = Array.from(set);
};

const hasLot = computed(() => Boolean(props.referenceCourante && props.cartesLot.length > 0));
</script>

<template>
    <Head title="Monétique — Cartes — Modifier prix" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-[calc(100vh-4rem)] bg-gradient-to-b from-slate-50/80 via-white to-violet-50/20">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
                <header class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-start gap-4">
                        <div
                            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-violet-600 text-white shadow-lg shadow-violet-600/25"
                        >
                            <CreditCard class="h-7 w-7" />
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold tracking-tight text-gray-900">Modifier les prix par lot</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-relaxed text-gray-600">
                                Choisissez la référence de facture, cochez les cartes concernées, puis appliquez le nouveau prix de vente.
                            </p>
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-11 shrink-0 border-gray-200 bg-white/90 shadow-sm hover:bg-white"
                        @click="router.visit('/monetique/cartes/en-stock')"
                    >
                        <ArrowLeft class="mr-2 h-4 w-4 text-violet-600" />
                        Retour au stock
                    </Button>
                </header>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Étape 1 -->
                    <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-md shadow-gray-200/30">
                        <div class="flex items-start gap-4 border-b border-gray-100 bg-gray-50/70 px-5 py-4 sm:px-6">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-sm font-bold text-violet-800"
                                >1</span
                            >
                            <div class="flex min-w-0 flex-1 items-start gap-3 pt-0.5">
                                <FileText class="mt-0.5 h-5 w-5 shrink-0 text-violet-600" />
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-gray-500">Référence de facture</p>
                                    <p class="mt-0.5 text-sm text-gray-600">
                                        Un lot regroupe toutes les cartes <strong class="font-medium text-gray-800">en stock</strong> avec la même
                                        référence.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-4 p-5 sm:p-6">
                            <div
                                v-if="references.length === 0"
                                class="rounded-xl border border-amber-200/80 bg-amber-50/90 px-4 py-3 text-sm text-amber-950"
                            >
                                Aucune carte en stock avec une référence de facture renseignée dans votre périmètre.
                            </div>

                            <div v-else class="max-w-xl space-y-2">
                                <Label for="reference_facture" class="text-sm font-medium text-gray-700">Référence facture</Label>
                                <select
                                    id="reference_facture"
                                    v-model="referenceSelection"
                                    :class="selectClass"
                                    @change="onReferenceChange"
                                >
                                    <option value="">— Choisir une référence —</option>
                                    <option v-for="r in references" :key="r.reference_facture" :value="r.reference_facture">
                                        {{ r.reference_facture }} ({{ r.cards_count }} carte(s))
                                    </option>
                                </select>
                                <InputError :message="form.errors.reference_facture" />
                            </div>
                        </div>
                    </div>

                    <!-- Étape 2 -->
                    <div
                        v-if="referenceCourante && cartesLot.length > 0"
                        class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-md shadow-gray-200/30"
                    >
                        <div class="flex flex-wrap items-start gap-4 border-b border-gray-100 bg-gray-50/70 px-5 py-4 sm:px-6">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-sm font-bold text-violet-800"
                                >2</span
                            >
                            <div class="flex min-w-0 flex-1 items-start gap-3 pt-0.5">
                                <ListChecks class="mt-0.5 h-5 w-5 shrink-0 text-violet-600" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-gray-500">Cartes concernées</p>
                                    <p class="mt-0.5 break-words text-sm text-gray-600">
                                        Référence
                                        <span class="rounded-md bg-gray-100 px-1.5 py-0.5 font-mono text-xs font-semibold text-gray-800">{{
                                            referenceCourante
                                        }}</span>
                                        — cochez une ou plusieurs lignes.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="border-b border-gray-100 bg-white px-4 py-3 sm:px-6">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <label class="inline-flex cursor-pointer items-center gap-2.5 text-sm font-medium text-gray-800">
                                    <input
                                        v-model="allSelected"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500"
                                    />
                                    Tout sélectionner
                                    <span class="font-normal text-gray-500">({{ cartesLot.length }})</span>
                                </label>
                                <span
                                    class="inline-flex w-fit items-center rounded-full border border-violet-200 bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-900"
                                >
                                    {{ selectedIds.length }} sélectionnée(s)
                                </span>
                            </div>
                        </div>

                        <div class="max-h-[min(420px,55vh)] overflow-auto">
                            <table class="min-w-full text-sm">
                                <thead class="sticky top-0 z-10 border-b border-gray-200 bg-gray-50/95 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 backdrop-blur-sm">
                                    <tr>
                                        <th class="w-12 px-4 py-3"></th>
                                        <th class="px-4 py-3">Numéro</th>
                                        <th class="px-4 py-3 text-right">Prix actuel</th>
                                        <th class="px-4 py-3 lg:w-[220px]">Expiration</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr
                                        v-for="c in cartesLot"
                                        :key="c.id"
                                        class="bg-white transition-colors hover:bg-violet-50/40"
                                        :class="selectedIds.includes(c.id) ? 'bg-violet-50/60' : ''"
                                    >
                                        <td class="px-4 py-3 align-middle">
                                            <input
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500"
                                                :checked="selectedIds.includes(c.id)"
                                                @change="toggleId(c.id)"
                                            />
                                        </td>
                                        <td class="px-4 py-3 font-mono text-sm tabular-nums text-gray-900">
                                            {{ formatCardNumberDisplay(c.numero_carte) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm font-medium tabular-nums text-gray-800 whitespace-nowrap">
                                            {{ formatCfa(c.prix_vente) }}
                                        </td>
                                        <td class="px-4 py-3 align-middle">
                                            <div class="max-w-[200px]">
                                                <ExpirationBar
                                                    :expiration="c.expiration ?? '—'"
                                                    :date-expiration="c.date_expiration ?? ''"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="border-t border-gray-100 px-5 py-3 sm:px-6">
                            <InputError :message="form.errors.card_ids" />
                        </div>
                    </div>

                    <div
                        v-else-if="referenceCourante && cartesLot.length === 0"
                        class="rounded-2xl border border-gray-200 bg-gray-50/80 px-5 py-4 text-sm text-gray-700"
                    >
                        Aucune carte en stock pour cette référence dans votre périmètre.
                    </div>

                    <!-- Étape 3 -->
                    <div
                        class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-md shadow-gray-200/30"
                        :class="!hasLot ? 'opacity-60' : ''"
                    >
                        <div class="flex flex-col gap-4 border-b border-gray-100 bg-gray-50/70 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="flex items-start gap-4">
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-sm font-bold text-emerald-800"
                                    >3</span
                                >
                                <div class="flex items-start gap-3 pt-0.5">
                                    <Layers class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-[0.12em] text-gray-500">Nouveau prix de vente</p>
                                        <p class="mt-0.5 text-sm text-gray-600">S’applique uniquement aux cartes cochées à l’étape 2.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col gap-6 p-5 sm:flex-row sm:items-end sm:justify-between sm:p-6">
                            <div class="w-full max-w-xs space-y-2">
                                <Label for="prix_vente" class="text-sm font-medium text-gray-700">Prix de vente (F CFA)</Label>
                                <Input
                                    id="prix_vente"
                                    v-model.number="form.prix_vente"
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="Ex. 5000"
                                    :class="inputClass"
                                    :disabled="!hasLot"
                                />
                                <InputError :message="form.errors.prix_vente" />
                            </div>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <Button type="button" variant="outline" class="h-11 border-gray-200" :disabled="!hasLot" @click="reset">
                                    <Eraser class="mr-2 h-4 w-4" />
                                    Effacer saisie
                                </Button>
                                <Button
                                    type="submit"
                                    class="h-11 bg-violet-600 text-white shadow-md shadow-violet-600/20 hover:bg-violet-700"
                                    :disabled="form.processing || !hasLot || selectedIds.length === 0"
                                >
                                    <Save class="mr-2 h-4 w-4" />
                                    {{ form.processing ? 'Enregistrement…' : 'Enregistrer les prix' }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
