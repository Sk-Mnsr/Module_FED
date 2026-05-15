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
import { ArrowRightLeft, Eraser, FileText, Save, Trash2 } from 'lucide-vue-next';
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

type CarteLotRow = {
    id: number;
    numero_carte: string;
    reference_facture: string;
    prix_vente: number;
    expiration?: string | null;
    date_expiration?: string | null;
};

type SelectedTransfertCarte = {
    id: number;
    numero_carte: string;
    reference_facture: string;
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
    prix_actuel: c.prix_vente,
    prix_vente: c.prix_vente,
    expiration: c.expiration,
    date_expiration: c.date_expiration,
});

const props = withDefaults(
    defineProps<{
        references?: RefFactureRow[];
        cartesLot?: CarteLotRow[];
        referenceCourante: string | null;
        chefsReceveurs?: ChefReceveur[];
        supplyRequest?: SupplyRequestPayload;
    }>(),
    {
        references: () => [],
        cartesLot: () => [],
        chefsReceveurs: () => [],
        supplyRequest: null,
    },
);

const page = usePage();
const canResponsableMonetique = computed(() => page.props.auth.canResponsableMonetique === true);
const referenceSelection = ref(props.referenceCourante ?? '');
/** Cartes retenues pour le transfert (plusieurs factures possibles). */
const selection = ref<Map<number, SelectedTransfertCarte>>(new Map());

watch(
    () => props.referenceCourante,
    (v) => {
        referenceSelection.value = v ?? '';
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

const reloadFactureQuery = () => {
    const q = referenceSelection.value.trim();
    const params: Record<string, string | number> = {};
    if (q) {
        params.reference_facture = q;
    }
    if (props.supplyRequest) {
        params.supply_request_id = props.supplyRequest.id;
    }
    router.get('/monetique/transferts/nouveau', params, {
        preserveState: true,
        replace: true,
        only: ['references', 'cartesLot', 'referenceCourante', 'chefsReceveurs', 'supplyRequest'],
    });
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
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center gap-3">
                <div class="p-3 bg-violet-100 text-violet-700 rounded-xl">
                    <ArrowRightLeft class="h-6 w-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Transférer des cartes</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Choisissez une facture, cochez les cartes, puis une autre facture si besoin — la sélection se cumule.
                    </p>
                </div>
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

            <form @submit.prevent="submit" class="space-y-6">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
                    <div class="flex items-start gap-2">
                        <FileText class="h-5 w-5 text-violet-600 mt-0.5 shrink-0" />
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Étape 1 — Par facture</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Un lot correspond aux cartes disponibles au siège pour cette référence (hors transfert en attente).
                            </p>
                        </div>
                    </div>

                    <div v-if="references.length === 0" class="text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-4 py-3">
                        Aucune carte éligible avec une référence de facture.
                    </div>

                    <div v-else class="space-y-2 max-w-xl">
                        <Label for="reference_facture" class="text-xs font-medium text-gray-600">Référence facture</Label>
                        <select
                            id="reference_facture"
                            v-model="referenceSelection"
                            class="flex h-10 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900"
                            @change="reloadFactureQuery"
                        >
                            <option value="">— Choisir une facture pour afficher les cartes —</option>
                            <option v-for="r in references" :key="r.reference_facture" :value="r.reference_facture">
                                {{ r.reference_facture }} ({{ r.cards_count }} carte(s) éligible(s))
                            </option>
                        </select>
                    </div>

                    <div v-if="referenceCourante && cartesLot.length" class="space-y-2">
                        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 pb-2">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input
                                    v-model="allLotSelected"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-violet-600 focus:ring-violet-500"
                                />
                                Tout prendre pour cette facture ({{ cartesLot.length }})
                            </label>
                        </div>
                        <div class="overflow-x-auto rounded-lg border border-gray-200 max-h-[320px] overflow-y-auto">
                            <table class="min-w-full text-sm">
                                <thead class="sticky top-0 bg-gray-50 border-b border-gray-200 text-left text-xs font-semibold uppercase text-gray-600">
                                    <tr>
                                        <th class="w-10 px-3 py-2"></th>
                                        <th class="px-3 py-2">Numéro</th>
                                        <th class="px-3 py-2 text-right">Prix</th>
                                        <th class="px-3 py-2 min-w-[190px]">Expiration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="c in cartesLot" :key="c.id" class="border-b border-gray-100 hover:bg-gray-50/80">
                                        <td class="px-3 py-2">
                                            <input
                                                type="checkbox"
                                                class="rounded border-gray-300 text-violet-600 focus:ring-violet-500"
                                                :checked="selection.has(c.id)"
                                                @change="toggleLotCard(c, ($event.target as HTMLInputElement).checked)"
                                            />
                                        </td>
                                        <td class="px-3 py-2 font-mono tabular-nums text-gray-900">
                                            {{ formatCardNumberDisplay(c.numero_carte) }}
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-600 whitespace-nowrap">
                                            {{ formatCfa(c.prix_vente) }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <ExpirationBar
                                                :expiration="c.expiration ?? '—'"
                                                :date-expiration="c.date_expiration ?? ''"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-xs text-gray-500">
                            Changez de facture ci-dessus pour ajouter d’autres cartes à la sélection ci-contre.
                        </p>
                    </div>

                    <div
                        v-else-if="referenceCourante && cartesLot.length === 0"
                        class="text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3"
                    >
                        Aucune carte éligible pour cette référence.
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-violet-200/80 shadow-sm p-6 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-gray-800">Sélection du transfert</p>
                        <span class="text-xs font-medium text-violet-700 bg-violet-50 border border-violet-100 rounded-full px-2.5 py-0.5">
                            {{ selectedCount }} carte(s)
                        </span>
                    </div>
                    <p
                        v-if="canResponsableMonetique && selectedCount > 0"
                        class="text-xs text-gray-600 bg-violet-50/80 border border-violet-100 rounded-md px-3 py-2"
                    >
                        Responsable monétique : le « prix actuel » est celui à la sélection ; la colonne « modifier le prix » est appliquée à l’enregistrement du transfert.
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
                                    <th class="px-3 py-2 min-w-[200px]">Expiration</th>
                                    <th class="px-3 py-2 text-right whitespace-nowrap">Prix actuel</th>
                                    <th v-if="canResponsableMonetique" class="px-3 py-2 text-right whitespace-nowrap">
                                        Modifier le prix
                                    </th>
                                    <th class="px-3 py-2 w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in selectedList" :key="c.id" class="border-b border-gray-100">
                                    <td class="px-3 py-2 font-mono text-xs text-gray-800">{{ c.reference_facture }}</td>
                                    <td class="px-3 py-2 font-mono tabular-nums">{{ formatCardNumberDisplay(c.numero_carte) }}</td>
                                    <td class="px-3 py-2">
                                        <ExpirationBar
                                            :expiration="c.expiration ?? '—'"
                                            :date-expiration="c.date_expiration ?? ''"
                                        />
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

                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <div class="space-y-5">
                        <div class="space-y-2">
                            <Label for="receveur_user_id" class="text-xs font-medium text-gray-600">
                                Chef d'agence receveur
                            </Label>
                            <select
                                id="receveur_user_id"
                                v-model="form.receveur_user_id"
                                class="mt-1.5 flex h-10 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900"
                                :class="form.errors.receveur_user_id ? 'border-rose-500 ring-1 ring-rose-500' : ''"
                            >
                                <option value="">-- Sélectionner --</option>
                                <option v-for="c in chefsReceveurs" :key="c.user_id" :value="c.user_id">
                                    {{ c.chef_nom }} — {{ c.agence_nom }} ({{ c.agence_code }})
                                </option>
                            </select>
                            <p
                                v-if="chefsReceveurs.length === 0"
                                class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2"
                            >
                                Aucun chef d'agence désigné. Paramétrez les entités (Configuration) en assignant un chef d'agence à chaque
                                agence concernée.
                            </p>
                            <InputError :message="form.errors.receveur_user_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="commentaire" class="text-xs font-medium text-gray-600">Commentaire</Label>
                            <textarea
                                id="commentaire"
                                v-model="form.commentaire"
                                rows="5"
                                class="mt-1.5 w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <Button type="button" variant="outline" @click="reset">
                        <Eraser class="h-4 w-4 mr-2" />
                        Effacer
                    </Button>
                    <Button
                        type="submit"
                        class="bg-violet-600 hover:bg-violet-700"
                        :disabled="form.processing || chefsReceveurs.length === 0 || selectedCount === 0"
                    >
                        <Save class="h-4 w-4 mr-2" />
                        Enregistrer le transfert
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
