<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ExpirationBar from '@/components/ExpirationBar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCardNumberDisplay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
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

type LotRow = {
    value: string;
    label: string;
    cards_count: number;
};

type CarteLotRow = {
    id: number;
    numero_carte: string;
    numero_lot?: string | null;
    prix_vente: number;
    expiration?: string | null;
    date_expiration?: string | null;
};

const props = withDefaults(
    defineProps<{
        references?: RefFactureRow[];
        lots?: LotRow[];
        cartesLot?: CarteLotRow[];
        referenceCourante: string | null;
        lotCourant?: string | null;
    }>(),
    {
        references: () => [],
        lots: () => [],
        cartesLot: () => [],
        lotCourant: null,
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Cartes', href: '/monetique/cartes/en-stock' },
    { title: 'Modifier lots', href: '/monetique/cartes/modifier-lots' },
];

const referenceSelection = ref(props.referenceCourante ?? '');
const lotSelection = ref(props.lotCourant ?? '');
const selectedIds = ref<number[]>([]);

const selectClass =
    'flex h-11 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-900 shadow-none ' +
    'focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30';

const inputClass =
    'h-11 rounded-lg border-gray-200 bg-white shadow-none ' +
    'focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30';

watch(
    () => props.referenceCourante,
    (v) => {
        referenceSelection.value = v ?? '';
        selectedIds.value = [];
    },
);

watch(
    () => props.lotCourant,
    (v) => {
        lotSelection.value = v ?? '';
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

const reloadQuery = (resetLot = false) => {
    const q = referenceSelection.value.trim();
    const params: Record<string, string> = {};
    if (q) {
        params.reference_facture = q;
    }
    if (!resetLot && lotSelection.value) {
        params.numero_lot = lotSelection.value;
    }
    if (resetLot) {
        lotSelection.value = '';
    }
    router.get('/monetique/cartes/modifier-lots', params, {
        preserveState: true,
        replace: true,
        only: ['references', 'lots', 'cartesLot', 'referenceCourante', 'lotCourant'],
    });
};

const onReferenceChange = () => {
    reloadQuery(true);
};

const onLotChange = () => {
    reloadQuery(false);
};

const form = useForm({
    reference_facture: '',
    card_ids: [] as number[],
    numero_lot: '',
});

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);

const reset = () => {
    form.numero_lot = '';
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
        form.setError('card_ids', 'Sélectionnez au moins une carte.');
        return;
    }

    form.reference_facture = refFacture;
    form.card_ids = [...selectedIds.value];
    form.put('/monetique/cartes/lots', {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            form.numero_lot = '';
        },
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

const hasCards = computed(() => Boolean(props.referenceCourante && props.cartesLot.length > 0));
</script>

<template>
    <Head title="Monétique — Cartes — Modifier lots" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-[calc(100vh-4rem)] bg-gradient-to-b from-slate-50/80 via-white to-violet-50/40">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
                <header class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-start gap-4">
                        <div
                            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-violet-700 text-white shadow-lg shadow-violet-700/20"
                        >
                            <Layers class="h-7 w-7" />
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold tracking-tight text-gray-900">Modifier / attribuer les lots</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-relaxed text-gray-600">
                                Pour les cartes déjà enregistrées sans lot (ou à reclasser) : filtrez par facture, cochez les
                                cartes, puis saisissez le numéro de lot.
                            </p>
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-11 shrink-0 border-gray-200 bg-white/90 shadow-sm hover:bg-white"
                        @click="router.visit('/monetique/cartes/en-stock')"
                    >
                        <ArrowLeft class="mr-2 h-4 w-4 text-violet-700" />
                        Retour au stock
                    </Button>
                </header>

                <div
                    v-if="flash?.success"
                    class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
                >
                    {{ flash.success }}
                </div>

                <form class="space-y-6" @submit.prevent="submit">
                    <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-md shadow-gray-200/30">
                        <div class="flex items-start gap-4 border-b border-gray-100 bg-gray-50/70 px-5 py-4 sm:px-6">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-sm font-bold text-violet-800"
                                >1</span
                            >
                            <div class="flex min-w-0 flex-1 items-start gap-3 pt-0.5">
                                <FileText class="mt-0.5 h-5 w-5 shrink-0 text-violet-700" />
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-gray-500">Facture et filtre</p>
                                    <p class="mt-0.5 text-sm text-gray-600">
                                        Choisissez une facture, puis éventuellement « Sans numéro de lot » pour cibler les cartes
                                        à mettre à jour.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-4 p-5 sm:p-6">
                            <div
                                v-if="references.length === 0"
                                class="rounded-xl border border-amber-200/80 bg-amber-50/90 px-4 py-3 text-sm text-amber-950"
                            >
                                Aucune carte en stock avec une référence de facture dans votre périmètre.
                            </div>

                            <div v-else class="max-w-3xl space-y-4">
                                <div class="space-y-2">
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

                                <div
                                    class="space-y-2 rounded-xl border border-violet-200 bg-violet-50/60 p-4"
                                    :class="!referenceCourante ? 'opacity-60' : ''"
                                >
                                    <Label for="filtre_lot" class="inline-flex items-center gap-2 text-sm font-semibold text-violet-950">
                                        <Layers class="size-4 text-violet-700" />
                                        Filtrer les cartes affichées
                                    </Label>
                                    <select
                                        id="filtre_lot"
                                        v-model="lotSelection"
                                        :class="selectClass"
                                        :disabled="!referenceCourante"
                                        @change="onLotChange"
                                    >
                                        <option value="">— Toutes les cartes de la facture —</option>
                                        <option v-for="l in lots" :key="l.value" :value="l.value">
                                            {{ l.label }} ({{ l.cards_count }})
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

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
                                <ListChecks class="mt-0.5 h-5 w-5 shrink-0 text-violet-700" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-gray-500">Cartes à mettre à jour</p>
                                    <p class="mt-0.5 break-words text-sm text-gray-600">
                                        Référence
                                        <span class="rounded-md bg-gray-100 px-1.5 py-0.5 font-mono text-xs font-semibold text-gray-800">{{
                                            referenceCourante
                                        }}</span>
                                        — cochez les lignes concernées.
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
                                        class="h-4 w-4 rounded border-gray-300 text-violet-700 focus:ring-violet-500"
                                    />
                                    Tout sélectionner
                                    <span class="font-normal text-gray-500">({{ cartesLot.length }})</span>
                                </label>
                                <span
                                    class="inline-flex w-fit items-center rounded-full border border-violet-200 bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-800"
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
                                        <th class="px-4 py-3">Lot actuel</th>
                                        <th class="px-4 py-3 text-right">Prix</th>
                                        <th class="px-4 py-3 lg:w-[220px]">Expiration</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr
                                        v-for="c in cartesLot"
                                        :key="c.id"
                                        class="bg-white transition-colors hover:bg-violet-50/60"
                                        :class="selectedIds.includes(c.id) ? 'bg-violet-50/80' : ''"
                                    >
                                        <td class="px-4 py-3 align-middle">
                                            <input
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-gray-300 text-violet-700 focus:ring-violet-500"
                                                :checked="selectedIds.includes(c.id)"
                                                @change="toggleId(c.id)"
                                            />
                                        </td>
                                        <td class="px-4 py-3 font-mono text-sm tabular-nums text-gray-900">
                                            {{ formatCardNumberDisplay(c.numero_carte) }}
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-600">
                                            {{ c.numero_lot || '—' }}
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
                        Aucune carte pour ce filtre.
                    </div>

                    <div
                        class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-md shadow-gray-200/30"
                        :class="!hasCards ? 'opacity-60' : ''"
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
                                        <p class="text-xs font-bold uppercase tracking-[0.12em] text-gray-500">Nouveau numéro de lot</p>
                                        <p class="mt-0.5 text-sm text-gray-600">
                                            Appliqué aux cartes cochées. Laissez vide pour retirer le lot.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col gap-6 p-5 sm:flex-row sm:items-end sm:justify-between sm:p-6">
                            <div class="w-full max-w-md space-y-2">
                                <Label for="nouveau_lot" class="text-sm font-medium text-gray-700">Numéro de lot</Label>
                                <Input
                                    id="nouveau_lot"
                                    v-model="form.numero_lot"
                                    type="text"
                                    placeholder="Ex : LOT-2026-001"
                                    :disabled="!hasCards"
                                    :class="inputClass"
                                />
                                <InputError :message="form.errors.numero_lot" />
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="h-11 border-gray-200 bg-white"
                                    :disabled="form.processing || !hasCards"
                                    @click="reset"
                                >
                                    <Eraser class="mr-2 h-4 w-4" />
                                    Effacer
                                </Button>
                                <Button
                                    type="submit"
                                    class="h-11 bg-violet-700 text-white hover:bg-violet-800"
                                    :disabled="form.processing || !hasCards"
                                >
                                    <Save class="mr-2 h-4 w-4" />
                                    {{ form.processing ? 'Enregistrement…' : 'Appliquer le lot' }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
