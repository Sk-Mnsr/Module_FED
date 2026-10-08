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
import { CreditCard, Eraser, Save } from 'lucide-vue-next';

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
        <div class="flex w-full flex-col gap-5 p-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="rounded-xl bg-violet-100 p-2.5 text-violet-700">
                        <CreditCard class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">Modifier / attribuer les lots</h1>
                        <p class="mt-0.5 text-sm text-gray-600">
                            Choisissez une facture, cochez les cartes, puis saisissez le numéro de lot.
                        </p>
                    </div>
                </div>
                <Button type="button" variant="outline" class="bg-white" @click="router.visit('/monetique/cartes/en-stock')">
                    Retour au stock
                </Button>
            </div>

            <div
                v-if="flash?.success"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            >
                {{ flash.success }}
            </div>

            <form class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]" @submit.prevent="submit">
                <div class="min-w-0 space-y-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div
                        v-if="references.length === 0"
                        class="rounded-lg border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                    >
                        Aucune carte en stock avec une référence de facture.
                    </div>

                    <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="reference_facture" class="text-xs font-medium text-gray-600">Facture</Label>
                            <select
                                id="reference_facture"
                                v-model="referenceSelection"
                                class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-900"
                                @change="onReferenceChange"
                            >
                                <option value="">— Choisir —</option>
                                <option v-for="r in references" :key="r.reference_facture" :value="r.reference_facture">
                                    {{ r.reference_facture }} ({{ r.cards_count }})
                                </option>
                            </select>
                            <InputError :message="form.errors.reference_facture" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="filtre_lot" class="text-xs font-medium text-gray-600">Lot actuel</Label>
                            <select
                                id="filtre_lot"
                                v-model="lotSelection"
                                class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-900 disabled:bg-gray-50 disabled:text-gray-400"
                                :disabled="!referenceCourante"
                                @change="onLotChange"
                            >
                                <option value="">— Toutes les cartes —</option>
                                <option v-for="l in lots" :key="l.value" :value="l.value">
                                    {{ l.label }} ({{ l.cards_count }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <div v-if="referenceCourante && cartesLot.length > 0" class="overflow-hidden rounded-lg border border-gray-200">
                        <div class="max-h-[min(70vh,720px)] overflow-auto">
                            <table class="min-w-full text-sm">
                                <thead class="sticky top-0 z-10 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                    <tr class="border-b border-gray-200">
                                        <th class="w-14 px-4 py-2.5">
                                            <input
                                                v-model="allSelected"
                                                type="checkbox"
                                                class="size-5 accent-violet-600"
                                                aria-label="Tout sélectionner"
                                            />
                                        </th>
                                        <th class="px-3 py-2.5">N° carte</th>
                                        <th class="px-3 py-2.5">Lot</th>
                                        <th class="px-3 py-2.5 text-right">Prix</th>
                                        <th class="px-3 py-2.5">Expire</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="c in cartesLot"
                                        :key="c.id"
                                        class="cursor-pointer border-b border-gray-100 last:border-0"
                                        :class="selectedIds.includes(c.id) ? 'bg-violet-100' : 'hover:bg-gray-50'"
                                        @click="toggleId(c.id)"
                                    >
                                        <td
                                            class="border-l-4 px-4 py-2.5"
                                            :class="selectedIds.includes(c.id) ? 'border-l-violet-600' : 'border-l-transparent'"
                                            @click.stop
                                        >
                                            <input
                                                type="checkbox"
                                                class="size-5 accent-violet-600"
                                                :checked="selectedIds.includes(c.id)"
                                                :aria-label="`Sélectionner la carte ${c.numero_carte}`"
                                                @change="toggleId(c.id)"
                                            />
                                        </td>
                                        <td class="px-3 py-2.5 font-mono font-medium whitespace-nowrap tabular-nums text-gray-900">
                                            {{ formatCardNumberDisplay(c.numero_carte) }}
                                        </td>
                                        <td class="px-3 py-2.5 whitespace-nowrap text-gray-600">
                                            {{ c.numero_lot || '—' }}
                                        </td>
                                        <td class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums text-gray-800">
                                            {{ formatCfa(c.prix_vente) }}
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <div class="w-40">
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
                    </div>

                    <div
                        v-else-if="referenceCourante && cartesLot.length === 0"
                        class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600"
                    >
                        Aucune carte pour ce filtre.
                    </div>
                    <p v-else-if="references.length > 0" class="text-sm text-gray-500">
                        Choisissez une facture pour afficher les cartes.
                    </p>
                    <InputError :message="form.errors.card_ids" />
                </div>

                <aside class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm xl:sticky xl:top-4">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Nouveau numéro de lot</h2>
                        <p class="mt-0.5 text-xs text-gray-500">
                            Appliqué aux cartes cochées. Laissez vide pour retirer le lot.
                        </p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="nouveau_lot" class="text-xs font-medium text-gray-600">Numéro de lot</Label>
                        <Input
                            id="nouveau_lot"
                            v-model="form.numero_lot"
                            type="text"
                            placeholder="Ex. LOT-2026-001"
                            class="border-gray-300"
                            :disabled="!hasCards"
                        />
                        <InputError :message="form.errors.numero_lot" />
                    </div>
                    <div
                        class="rounded-lg border px-3 py-2.5"
                        :class="selectedIds.length ? 'border-violet-200 bg-violet-50' : 'border-gray-200 bg-gray-50'"
                    >
                        <p class="text-xs font-medium tracking-wide uppercase" :class="selectedIds.length ? 'text-violet-800' : 'text-gray-500'">
                            Sélection
                        </p>
                        <p class="mt-0.5 text-sm font-semibold tabular-nums" :class="selectedIds.length ? 'text-violet-950' : 'text-gray-700'">
                            {{ selectedIds.length }} carte{{ selectedIds.length > 1 ? 's' : '' }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Button
                            type="submit"
                            class="w-full bg-violet-600 hover:bg-violet-700"
                            :disabled="form.processing || !hasCards || selectedIds.length === 0"
                        >
                            <Save class="mr-2 h-4 w-4" />
                            {{ form.processing ? 'Enregistrement…' : 'Appliquer le lot' }}
                        </Button>
                        <Button type="button" variant="outline" class="w-full bg-white" :disabled="!hasCards" @click="reset">
                            <Eraser class="mr-2 h-4 w-4" />
                            Effacer
                        </Button>
                    </div>
                </aside>
            </form>
        </div>
    </AppLayout>
</template>
