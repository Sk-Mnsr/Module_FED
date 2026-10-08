<script setup lang="ts">
import ExpirationBar from '@/components/ExpirationBar.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCardNumberDisplay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowRightLeft, Eraser, Save, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Transferts', href: '/monetique/transferts/nouveau' },
    { title: 'Nouveau', href: '/monetique/transferts/nouveau' },
];

type ChefReceveur = {
    user_id: number;
    chef_nom: string;
    agence_nom: string;
    agence_code: string;
};

type SupplyRequestPayload = {
    id: number;
    quantite_demandee: number;
    quantite_livree: number;
    commentaire: string | null;
    chef_receveur_user_id: number;
} | null;

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
    reference_facture: string;
    prix_vente: number;
    expiration?: string | null;
    date_expiration?: string | null;
};

type SelectedTransfertCarte = {
    id: number;
    numero_carte: string;
    reference_facture: string;
    numero_lot?: string | null;
    /** Prix au moment de l’ajout à la sélection (affichage « prix actuel »). */
    prix_actuel: number;
    /** Prix envoyé au serveur (modifiable par le responsable monétique). */
    prix_vente: number;
    expiration?: string | null;
    date_expiration?: string | null;
};

const toSelectedRow = (c: CarteLotRow): SelectedTransfertCarte => ({
    id: c.id,
    numero_carte: c.numero_carte,
    reference_facture: c.reference_facture,
    numero_lot: c.numero_lot ?? null,
    prix_actuel: c.prix_vente,
    prix_vente: c.prix_vente,
    expiration: c.expiration,
    date_expiration: c.date_expiration,
});

const props = withDefaults(
    defineProps<{
        references?: RefFactureRow[];
        lots?: LotRow[];
        cartesLot?: CarteLotRow[];
        referenceCourante: string | null;
        lotCourant?: string | null;
        chefsReceveurs?: ChefReceveur[];
        supplyRequest?: SupplyRequestPayload;
    }>(),
    {
        references: () => [],
        lots: () => [],
        cartesLot: () => [],
        lotCourant: null,
        chefsReceveurs: () => [],
        supplyRequest: null,
    },
);

const page = usePage();
const canResponsableMonetique = computed(() => page.props.auth.canResponsableMonetique === true);
const referenceSelection = ref(props.referenceCourante ?? '');
const lotSelection = ref(props.lotCourant ?? '');
/** Cartes retenues pour le transfert (plusieurs factures possibles). */
const selection = ref<Map<number, SelectedTransfertCarte>>(new Map());

watch(
    () => props.referenceCourante,
    (v) => {
        referenceSelection.value = v ?? '';
    },
);

watch(
    () => props.lotCourant,
    (v) => {
        lotSelection.value = v ?? '';
    },
);

const form = useForm({
    receveur_user_id: '' as string | number,
    card_ids: [] as number[],
    commentaire: '' as string,
    supply_request_id: null as number | null,
    supply_request_completion: 'continue' as 'continue' | 'close',
});

watch(
    () => props.supplyRequest,
    (sr) => {
        if (sr) {
            form.supply_request_id = sr.id;
            form.supply_request_completion = 'continue';
            form.receveur_user_id = sr.chef_receveur_user_id;
            form.commentaire = sr.commentaire
                ? `Demande #${sr.id} (${sr.quantite_demandee} cartes) — ${sr.commentaire}`
                : `Demande #${sr.id} (${sr.quantite_demandee} cartes)`;
        }
    },
    { immediate: true },
);

const selectedList = computed(() =>
    Array.from(selection.value.values()).sort((a, b) => a.numero_carte.localeCompare(b.numero_carte)),
);

const selectedCount = computed(() => selectedList.value.length);

const resteTheoriqueDemande = computed(() => {
    if (!props.supplyRequest) {
        return 0;
    }
    return Math.max(0, props.supplyRequest.quantite_demandee - props.supplyRequest.quantite_livree);
});

const updateSelectionPrix = (id: number, raw: string | number) => {
    const row = selection.value.get(id);
    if (!row) {
        return;
    }
    const n = typeof raw === 'string' ? parseInt(raw, 10) : raw;
    const prix = Number.isFinite(n) ? Math.max(0, Math.round(n)) : row.prix_vente;
    const next = new Map(selection.value);
    next.set(id, { ...row, prix_vente: prix }); // prix_actuel inchangé
    selection.value = next;
};

const formatCfa = (n: number) => `${n.toLocaleString('fr-FR')} F CFA`;

const reloadFactureQuery = (resetLot = false) => {
    const q = referenceSelection.value.trim();
    const params: Record<string, string | number> = {};
    if (q) {
        params.reference_facture = q;
    }
    if (!resetLot && lotSelection.value) {
        params.numero_lot = lotSelection.value;
    }
    if (resetLot) {
        lotSelection.value = '';
    }
    if (props.supplyRequest) {
        params.supply_request_id = props.supplyRequest.id;
    }
    router.get('/monetique/transferts/nouveau', params, {
        preserveState: true,
        replace: true,
        only: ['references', 'lots', 'cartesLot', 'referenceCourante', 'lotCourant', 'chefsReceveurs', 'supplyRequest'],
    });
};

const onFactureChange = () => {
    reloadFactureQuery(true);
};

const onLotChange = () => {
    reloadFactureQuery(false);
};

const toggleLotCard = (c: CarteLotRow, checked: boolean) => {
    const next = new Map(selection.value);
    if (checked) {
        next.set(c.id, toSelectedRow(c));
    } else {
        next.delete(c.id);
    }
    selection.value = next;
};

const removeFromSelection = (id: number) => {
    const next = new Map(selection.value);
    next.delete(id);
    selection.value = next;
};

const allLotIds = computed(() => props.cartesLot.map((c) => c.id));

const allLotSelected = computed({
    get() {
        if (!props.cartesLot.length) {
            return false;
        }
        return props.cartesLot.every((c) => selection.value.has(c.id));
    },
    set(on: boolean) {
        const next = new Map(selection.value);
        if (on) {
            for (const c of props.cartesLot) {
                next.set(c.id, toSelectedRow(c));
            }
        } else {
            for (const c of props.cartesLot) {
                next.delete(c.id);
            }
        }
        selection.value = next;
    },
});

const reset = () => {
    selection.value = new Map();
    form.commentaire = '';
    form.supply_request_id = props.supplyRequest?.id ?? null;
    form.supply_request_completion = 'continue';
    form.receveur_user_id = '';
    if (props.supplyRequest) {
        form.receveur_user_id = props.supplyRequest.chef_receveur_user_id;
        form.commentaire = props.supplyRequest.commentaire
            ? `Demande #${props.supplyRequest.id} (${props.supplyRequest.quantite_demandee} cartes) — ${props.supplyRequest.commentaire}`
            : `Demande #${props.supplyRequest.id} (${props.supplyRequest.quantite_demandee} cartes)`;
    }
    form.clearErrors();
};

const submit = () => {
    form.clearErrors();
    const ids = selectedList.value.map((c) => c.id);
    if (ids.length === 0) {
        form.setError('card_ids', 'Sélectionnez au moins une carte (vous pouvez cumuler plusieurs factures).');
        return;
    }
    form.card_ids = ids;
    form.transform((data) => {
        const base: Record<string, unknown> = {
            ...data,
            receveur_user_id:
                data.receveur_user_id === '' || data.receveur_user_id === null
                    ? null
                    : Number(data.receveur_user_id),
            supply_request_id: data.supply_request_id || null,
            supply_request_completion: data.supply_request_id ? data.supply_request_completion : null,
            card_ids: ids,
        };
        if (canResponsableMonetique.value) {
            const prix_par_carte: Record<string, number> = {};
            for (const c of selectedList.value) {
                prix_par_carte[String(c.id)] = Math.max(0, Math.round(Number(c.prix_vente)));
            }
            base.prix_par_carte = prix_par_carte;
        }
        return base;
    }).post('/monetique/transferts', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Monétique - Transferts - Nouveau" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex w-full flex-col gap-5 p-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="rounded-xl bg-violet-100 p-2.5 text-violet-700">
                        <ArrowRightLeft class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">Transférer des cartes</h1>
                        <p class="mt-0.5 text-sm text-gray-600">
                            Cochez les cartes d’une facture. Vous pouvez changer de facture : la sélection se cumule.
                        </p>
                    </div>
                </div>
                <p class="text-sm text-gray-500">{{ selectedCount }} carte{{ selectedCount > 1 ? 's' : '' }} sélectionnée{{ selectedCount > 1 ? 's' : '' }}</p>
            </div>

            <div
                v-if="supplyRequest"
                class="rounded-xl border border-violet-200 bg-violet-50/70 p-5 space-y-3 text-sm text-gray-800 shadow-sm"
            >
                <p class="font-semibold text-gray-900">Transfert lié à la demande #{{ supplyRequest.id }}</p>
                <p>
                    Déjà livré (réceptions validées) :
                    <strong class="tabular-nums">{{ supplyRequest.quantite_livree }}</strong>
                    /
                    <span class="tabular-nums">{{ supplyRequest.quantite_demandee }}</span>
                    .
                </p>
                <p v-if="resteTheoriqueDemande > 0">
                    Écart maximal à combler pour la demande :
                    <strong class="tabular-nums">{{ resteTheoriqueDemande }}</strong>
                    carte(s).
                </p>
                <p v-else class="text-emerald-800">La quantité demandée est déjà couverte par les réceptions enregistrées.</p>
                <div class="space-y-2.5 pt-1 border-t border-violet-100">
                    <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Après réception de ce transfert</p>
                    <label class="flex gap-3 cursor-pointer items-start rounded-lg border border-transparent hover:border-violet-100 px-1 py-0.5 -mx-1">
                        <input v-model="form.supply_request_completion" type="radio" value="continue" class="mt-1" />
                        <span>
                            <span class="font-medium text-gray-900">Poursuivre la demande</span>
                            <span class="text-gray-600">
                                — si la quantité réceptionnée ne suffit pas, vous pourrez lancer un autre transfert.
                            </span>
                        </span>
                    </label>
                    <label class="flex gap-3 cursor-pointer items-start rounded-lg border border-transparent hover:border-violet-100 px-1 py-0.5 -mx-1">
                        <input v-model="form.supply_request_completion" type="radio" value="close" class="mt-1" />
                        <span>
                            <span class="font-medium text-gray-900">Clôturer la demande</span>
                            <span class="text-gray-600">
                                — après cette réception, la demande sera classée comme traitée même s’il reste un reliquat.
                            </span>
                        </span>
                    </label>
                </div>
                <p
                    v-if="form.supply_request_completion === 'continue' && selectedCount > 0 && resteTheoriqueDemande > 0 && selectedCount < resteTheoriqueDemande"
                    class="text-xs text-amber-900 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 leading-relaxed"
                >
                    Vous envoyez
                    <strong class="tabular-nums">{{ selectedCount }}</strong>
                    carte(s) alors qu’il reste
                    <strong class="tabular-nums">{{ resteTheoriqueDemande }}</strong>
                    unité(s) « théoriques » par rapport à la demande : tant que vous choisissez « Poursuivre », un prochain
                    transfert pourra combler l’écart.
                </p>
                <InputError :message="form.errors.supply_request_completion" />
            </div>

            <form @submit.prevent="submit" class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="min-w-0 space-y-5">
                <div class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Cartes au siège</p>
                            <p class="mt-0.5 text-xs text-gray-500">Hors transfert déjà en attente.</p>
                        </div>
                    </div>

                    <div v-if="references.length === 0" class="rounded-lg border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Aucune carte éligible avec une référence de facture.
                    </div>

                    <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="reference_facture" class="text-xs font-medium text-gray-600">Facture</Label>
                            <select
                                id="reference_facture"
                                v-model="referenceSelection"
                                class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-900"
                                @change="onFactureChange"
                            >
                                <option value="">— Choisir —</option>
                                <option v-for="r in references" :key="r.reference_facture" :value="r.reference_facture">
                                    {{ r.reference_facture }} ({{ r.cards_count }})
                                </option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="numero_lot" class="text-xs font-medium text-gray-600">Lot</Label>
                            <select
                                id="numero_lot"
                                v-model="lotSelection"
                                class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-900 disabled:bg-gray-50 disabled:text-gray-400"
                                :disabled="!referenceCourante || lots.length === 0"
                                @change="onLotChange"
                            >
                                <option value="">— Tous —</option>
                                <option v-for="l in lots" :key="l.value" :value="l.value">
                                    {{ l.label }} ({{ l.cards_count }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <div v-if="referenceCourante && cartesLot.length" class="overflow-hidden rounded-lg border border-gray-200">
                        <div class="max-h-[min(52vh,520px)] overflow-auto">
                            <table class="min-w-full text-sm">
                                <thead class="sticky top-0 z-10 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                    <tr class="border-b border-gray-200">
                                        <th class="w-14 px-4 py-2.5">
                                            <input
                                                v-model="allLotSelected"
                                                type="checkbox"
                                                class="size-5 accent-violet-600"
                                                :aria-label="lotCourant ? 'Tout prendre pour ce lot' : 'Tout prendre pour cette facture'"
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
                                        :class="selection.has(c.id) ? 'bg-violet-100' : 'hover:bg-gray-50'"
                                        @click="toggleLotCard(c, !selection.has(c.id))"
                                    >
                                        <td
                                            class="border-l-4 px-4 py-2.5"
                                            :class="selection.has(c.id) ? 'border-l-violet-600' : 'border-l-transparent'"
                                            @click.stop
                                        >
                                            <input
                                                type="checkbox"
                                                class="size-5 accent-violet-600"
                                                :checked="selection.has(c.id)"
                                                :aria-label="`Sélectionner la carte ${c.numero_carte}`"
                                                @change="toggleLotCard(c, ($event.target as HTMLInputElement).checked)"
                                            />
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 font-mono font-medium tabular-nums text-gray-900">
                                            {{ formatCardNumberDisplay(c.numero_carte) }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-gray-600">
                                            {{ c.numero_lot || '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-gray-700">
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
                        Aucune carte éligible pour cette référence.
                    </div>
                </div>

                <div class="space-y-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-gray-800">Sélection du transfert</p>
                        <span class="text-xs font-medium text-violet-700 bg-violet-50 border border-violet-100 rounded-full px-2.5 py-0.5">
                            {{ selectedCount }} carte(s)
                        </span>
                    </div>
                    <p
                        v-if="canResponsableMonetique && selectedCount > 0"
                        class="text-xs text-gray-600"
                    >
                        Le prix saisi dans « Nouveau prix » est celui enregistré sur le transfert.
                    </p>
                    <div v-if="selectedCount === 0" class="text-sm text-gray-500 italic py-2">
                        Aucune carte sélectionnée pour l’instant.
                    </div>
                    <div v-else class="overflow-x-auto rounded-lg border border-gray-200 max-h-[280px] overflow-y-auto">
                        <table class="min-w-full text-sm">
                            <thead class="sticky top-0 bg-gray-50 border-b border-gray-200 text-left text-xs font-semibold uppercase text-gray-600">
                                <tr>
                                    <th class="px-3 py-2">Facture</th>
                                    <th class="px-3 py-2">Numéro</th>
                                    <th class="px-3 py-2">Expire</th>
                                    <th class="px-3 py-2 text-right whitespace-nowrap">Prix</th>
                                    <th v-if="canResponsableMonetique" class="px-3 py-2 text-right whitespace-nowrap">
                                        Nouveau prix
                                    </th>
                                    <th class="px-3 py-2 w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in selectedList" :key="c.id" class="border-b border-gray-100">
                                    <td class="px-3 py-2 font-mono text-xs text-gray-800">{{ c.reference_facture }}</td>
                                    <td class="px-3 py-2 font-mono tabular-nums">{{ formatCardNumberDisplay(c.numero_carte) }}</td>
                                    <td class="px-3 py-2">
                                        <div class="w-40">
                                            <ExpirationBar
                                                :expiration="c.expiration ?? '—'"
                                                :date-expiration="c.date_expiration ?? ''"
                                            />
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-700 whitespace-nowrap">
                                        {{ formatCfa(c.prix_actuel) }}
                                    </td>
                                    <td v-if="canResponsableMonetique" class="px-3 py-2 text-right">
                                        <Input
                                            type="number"
                                            min="0"
                                            step="1"
                                            class="ml-auto w-28 h-9 border-gray-300 tabular-nums text-right"
                                            :model-value="c.prix_vente"
                                            @update:model-value="(v) => updateSelectionPrix(c.id, v as string | number)"
                                        />
                                    </td>
                                    <td class="px-3 py-2">
                                        <button
                                            type="button"
                                            class="text-gray-400 hover:text-rose-600 p-1 rounded"
                                            title="Retirer"
                                            @click="removeFromSelection(c.id)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <InputError :message="form.errors.card_ids" />
                    <InputError :message="form.errors.prix_par_carte" />
                </div>
                </div>

                <aside class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm xl:sticky xl:top-4">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Destinataire</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Chef d’agence qui réceptionne le transfert.</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="receveur_user_id" class="text-xs font-medium text-gray-600">Chef d’agence</Label>
                        <select
                            id="receveur_user_id"
                            v-model="form.receveur_user_id"
                            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-900"
                            :class="form.errors.receveur_user_id ? 'border-rose-500 ring-1 ring-rose-500' : ''"
                        >
                            <option value="">— Sélectionner —</option>
                            <option v-for="c in chefsReceveurs" :key="c.user_id" :value="c.user_id">
                                {{ c.chef_nom }} — {{ c.agence_nom }} ({{ c.agence_code }})
                            </option>
                        </select>
                        <p
                            v-if="chefsReceveurs.length === 0"
                            class="rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-xs text-amber-900"
                        >
                            Aucun chef d’agence désigné.
                        </p>
                        <InputError :message="form.errors.receveur_user_id" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="commentaire" class="text-xs font-medium text-gray-600">Commentaire</Label>
                        <textarea
                            id="commentaire"
                            v-model="form.commentaire"
                            rows="4"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-500"
                        />
                    </div>
                    <div
                        class="rounded-lg border px-3 py-2.5"
                        :class="selectedCount ? 'border-violet-200 bg-violet-50' : 'border-gray-200 bg-gray-50'"
                    >
                        <p class="text-xs font-medium uppercase tracking-wide" :class="selectedCount ? 'text-violet-800' : 'text-gray-500'">
                            Sélection
                        </p>
                        <p class="mt-0.5 text-sm font-semibold tabular-nums" :class="selectedCount ? 'text-violet-950' : 'text-gray-700'">
                            {{ selectedCount }} carte{{ selectedCount > 1 ? 's' : '' }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Button
                            type="submit"
                            class="w-full bg-violet-600 hover:bg-violet-700"
                            :disabled="form.processing || chefsReceveurs.length === 0 || selectedCount === 0"
                        >
                            <Save class="mr-2 h-4 w-4" />
                            Enregistrer
                        </Button>
                        <Button type="button" variant="outline" class="w-full bg-white" @click="reset">
                            <Eraser class="mr-2 h-4 w-4" />
                            Effacer
                        </Button>
                    </div>
                </aside>
            </form>
        </div>
    </AppLayout>
</template>
