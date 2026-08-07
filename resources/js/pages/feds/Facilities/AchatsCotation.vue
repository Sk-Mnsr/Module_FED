<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { computed, ref } from 'vue';
import {
    ArrowLeft,
    FileSpreadsheet,
    Paperclip,
    Plus,
    Save,
    Send,
    Trash2,
} from 'lucide-vue-next';
import SupplierCombobox from '@/components/SupplierCombobox.vue';

interface OffreAttachment {
    id: number;
    original_name: string;
    path: string;
}

interface FedFournisseurOffre {
    id?: number;
    fournisseur: string;
    fournisseur_id?: number | null;
    fed_item_id?: number | null;
    prix_unitaire?: number | null;
    delais_livraison?: string | null;
    garanties_offertes?: string | null;
    conformite_reglementaire?: string | null;
    acompte_requis?: string | null;
    pourcentage_acompte?: number | null;
    attachments?: OffreAttachment[];
}

interface Fournisseur {
    id: number;
    nom: string;
    type?: string | null;
    categorie?: string | null;
}

interface FedItem {
    id: number;
    label: string;
    quantity: number;
}

interface Fed {
    id: number;
    code: string;
    motive?: string | null;
    department?: string | null;
    demandeur?: string | null;
    status: string;
    items?: FedItem[];
    expert_opinion_offre_id?: number | null;
    facilities_comment?: string | null;
}

interface Props {
    fed: Fed & { fournisseur_offres?: FedFournisseurOffre[] };
    fournisseurs: Fournisseur[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Demandes en cours', href: '/feds/achats' },
    { title: props.fed.code, href: `/feds/achats/${props.fed.id}` },
    { title: 'Tableau comparatif', href: '#' },
];

const canEdit = computed(() =>
    ['n1_approved', 'achats_needs_info', 'facilities_needs_info'].includes(props.fed.status),
);
const isFacilitiesHold = computed(() => props.fed.status === 'facilities_needs_info');
const items = computed(() => props.fed.items ?? []);

interface FournisseurOffreGroup {
    fournisseur: string;
    fournisseur_id: number | null;
    delais_livraison: string;
    garanties_offertes: string;
    conformite_reglementaire: string | null;
    acompte_requis: string | null;
    pourcentage_acompte: number | null;
    _file: File | null;
    prices: Record<number, number | null>;
    attachments?: OffreAttachment[];
    id?: number;
}

const makeEmptyGroup = (): FournisseurOffreGroup => ({
    fournisseur: '',
    fournisseur_id: null,
    delais_livraison: '',
    garanties_offertes: '',
    conformite_reglementaire: null,
    acompte_requis: null,
    pourcentage_acompte: null,
    _file: null,
    prices: {},
});

const initFournisseurOffres = () => {
    const existing = props.fed.fournisseur_offres ?? [];
    if (existing.length === 0) return [makeEmptyGroup()];

    const groups: Record<string, FournisseurOffreGroup> = {};

    existing.forEach((o) => {
        const key = o.fournisseur_id ? `id_${o.fournisseur_id}` : `name_${o.fournisseur}`;
        if (!groups[key]) {
            groups[key] = {
                fournisseur: o.fournisseur,
                fournisseur_id: o.fournisseur_id ?? null,
                delais_livraison: o.delais_livraison ?? '',
                garanties_offertes: o.garanties_offertes ?? '',
                conformite_reglementaire: o.conformite_reglementaire ?? null,
                acompte_requis: o.acompte_requis ?? null,
                pourcentage_acompte: o.pourcentage_acompte ?? null,
                _file: null,
                prices: {},
                attachments: o.attachments,
            };
        }
        if (o.fed_item_id) {
            groups[key].prices[o.fed_item_id] = o.prix_unitaire ?? null;
        } else {
            groups[key].prices[0] = o.prix_unitaire ?? null;
        }
    });

    return Object.values(groups).map((g) => {
        const representative = existing.find(
            (o) =>
                (o.fournisseur_id === g.fournisseur_id && g.fournisseur_id) ||
                (o.fournisseur === g.fournisseur && !g.fournisseur_id),
        );
        return { ...g, id: representative?.id };
    });
};

const internalFournisseurOffres = ref(initFournisseurOffres());
const fournisseurOffres = computed(() => internalFournisseurOffres.value);

const filledFournisseurs = computed(
    () => fournisseurOffres.value.filter((g) => g.fournisseur_id || g.fournisseur.trim()).length,
);

const formatAmount = (value?: number | string | null) => {
    if (value === null || value === undefined || value === '') return '—';
    const val = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('fr-FR').format(val);
};

const formatQuantity = (value?: number | string | null) => {
    if (value === null || value === undefined || value === '') return '—';
    const val = typeof value === 'string' ? parseFloat(value) : value;
    return Math.floor(val);
};

const groupTotal = (group: FournisseurOffreGroup) => {
    if (items.value.length === 0) return group.prices[0] ?? 0;
    return items.value.reduce((sum, item) => sum + (group.prices[item.id] ?? 0) * item.quantity, 0);
};

const addFournisseur = () => {
    fournisseurOffres.value.push(makeEmptyGroup());
};

const removeFournisseur = (index: number) => {
    if (fournisseurOffres.value.length > 1) {
        fournisseurOffres.value.splice(index, 1);
    }
};

const onFournisseurSelect = (index: number, id: number | null) => {
    const group = fournisseurOffres.value[index];
    group.fournisseur_id = id;
    const found = id ? props.fournisseurs.find((f) => f.id === id) : null;
    group.fournisseur = found?.nom ?? '';
};

const triggerFileInput = (index: number) => {
    (document.getElementById(`file-${index}`) as HTMLInputElement | null)?.click();
};

const onFileSelect = (index: number, event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (file) fournisseurOffres.value[index]._file = file;
    input.value = '';
};

const saveOffres = () => {
    const validGroups = fournisseurOffres.value.filter((g) => g.fournisseur_id || g.fournisseur.trim());
    if (validGroups.length === 0) {
        alert('Ajoutez au moins un fournisseur.');
        return;
    }

    const flatOffres: any[] = [];
    const files: { index: number; file: File }[] = [];

    validGroups.forEach((group) => {
        if (items.value.length > 0) {
            items.value.forEach((item) => {
                flatOffres.push({
                    fournisseur: group.fournisseur,
                    fournisseur_id: group.fournisseur_id,
                    fed_item_id: item.id,
                    prix_unitaire: group.prices[item.id] ?? null,
                    delais_livraison: group.delais_livraison || null,
                    garanties_offertes: group.garanties_offertes || null,
                    conformite_reglementaire: group.conformite_reglementaire,
                    acompte_requis: group.acompte_requis,
                    pourcentage_acompte: group.pourcentage_acompte,
                });
            });
        } else {
            flatOffres.push({
                fournisseur: group.fournisseur,
                fournisseur_id: group.fournisseur_id,
                fed_item_id: null,
                prix_unitaire: group.prices[0] ?? null,
                delais_livraison: group.delais_livraison || null,
                garanties_offertes: group.garanties_offertes || null,
                conformite_reglementaire: group.conformite_reglementaire,
                acompte_requis: group.acompte_requis,
                pourcentage_acompte: group.pourcentage_acompte,
            });
        }

        if (group._file) {
            files.push({ index: flatOffres.length - (items.value.length || 1), file: group._file });
        }
    });

    const formData = new FormData();
    formData.append('offres', JSON.stringify(flatOffres));
    files.forEach((f) => {
        formData.append(`file_${f.index}`, f.file);
    });

    router.post(`/feds/achats/${props.fed.id}/offres`, formData, {
        preserveScroll: true,
        forceFormData: true,
    });
};

const hasOffresEnregistrees = computed(() => (props.fed.fournisseur_offres?.length ?? 0) >= 1);
const transmitComment = ref('');

const transmitToFacilities = () => {
    if (!hasOffresEnregistrees.value) {
        alert("Enregistrez d'abord le tableau comparatif avec au moins un fournisseur.");
        return;
    }
    if (!confirm('Confirmer la transmission au responsable Facilities ?')) return;
    router.post(
        `/feds/achats/${props.fed.id}/transmit`,
        { comment: transmitComment.value },
        { preserveScroll: true },
    );
};

const inputClass =
    'h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 disabled:opacity-60 dark:border-slate-600 dark:bg-card dark:text-foreground';
const selectClass =
    'h-10 w-full rounded-md border border-slate-300 bg-white px-2 text-sm text-slate-900 shadow-sm focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 disabled:opacity-60 dark:border-slate-600 dark:bg-card dark:text-foreground';
</script>

<template>
    <Head :title="`Tableau comparatif - ${props.fed.code}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-0 flex-1 flex-col gap-4 p-4 lg:p-6">
            <div
                class="flex flex-wrap items-end justify-between gap-3 rounded-2xl border border-border/80 bg-card px-5 py-4 shadow-sm sm:px-6"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <Button as-child variant="outline" size="sm" class="border-slate-300">
                        <Link :href="`/feds/achats/${props.fed.id}`" class="inline-flex items-center gap-1.5">
                            <ArrowLeft class="size-4" />
                            Retour à la demande
                        </Link>
                    </Button>
                    <span
                        class="inline-flex rounded-full border border-primary/25 bg-primary/5 px-3 py-1 text-sm font-medium text-primary"
                    >
                        FED {{ props.fed.code }}
                    </span>
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-primary">
                        Achats & consultations
                    </p>
                    <h1 class="text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
                        Tableau comparatif
                    </h1>
                    <p v-if="props.fed.motive" class="mt-0.5 truncate text-sm text-muted-foreground">
                        Motif :
                        <span class="font-medium uppercase text-foreground">{{ props.fed.motive }}</span>
                    </p>
                </div>
            </div>

            <div class="grid min-h-0 flex-1 gap-4 lg:grid-cols-[minmax(0,1.5fr)_minmax(280px,0.65fr)]">
                <!-- Offres -->
                <div class="min-h-0 space-y-4 overflow-y-auto">
                    <div
                        v-for="(group, gIdx) in fournisseurOffres"
                        :key="gIdx"
                        class="overflow-hidden rounded-2xl border shadow-sm transition-colors"
                        :class="
                            group.id === props.fed.expert_opinion_offre_id
                                ? 'border-amber-300 bg-amber-50/30 ring-1 ring-amber-200'
                                : 'border-border/80 bg-card'
                        "
                    >
                        <div
                            class="flex items-center justify-between gap-3 border-b border-border/80 bg-gradient-to-r from-primary/5 via-transparent to-transparent px-4 py-3 sm:px-5"
                        >
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <span
                                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground"
                                >
                                    {{ gIdx + 1 }}
                                </span>
                                <div class="min-w-0 flex-1 max-w-md">
                                    <template v-if="canEdit">
                                        <SupplierCombobox
                                            :suppliers="props.fournisseurs"
                                            :model-value="group.fournisseur_id"
                                            placeholder="Rechercher un fournisseur…"
                                            @update:model-value="onFournisseurSelect(gIdx, $event)"
                                        />
                                    </template>
                                    <span v-else class="block truncate text-base font-semibold uppercase text-foreground">
                                        {{ group.fournisseur || 'Fournisseur non défini' }}
                                    </span>
                                </div>
                                <span
                                    v-if="group.id === props.fed.expert_opinion_offre_id"
                                    class="hidden shrink-0 rounded border border-amber-200 bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800 sm:inline-flex"
                                >
                                    Choix expert
                                </span>
                            </div>
                            <Button
                                v-if="canEdit && fournisseurOffres.length > 1"
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="shrink-0 text-red-600 hover:bg-red-50 hover:text-red-700"
                                @click="removeFournisseur(gIdx)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>

                        <div class="grid gap-5 p-4 sm:p-5 lg:grid-cols-[minmax(0,1fr)_minmax(240px,280px)]">
                            <div>
                                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Prix unitaires
                                </h3>

                                <template v-if="items.length > 0">
                                    <div class="overflow-x-auto rounded-lg border border-border">
                                        <table class="w-full text-sm">
                                            <thead class="bg-muted/60 text-xs font-semibold uppercase text-muted-foreground">
                                                <tr>
                                                    <th class="px-3 py-2.5 text-left">Article</th>
                                                    <th class="w-20 px-3 py-2.5 text-center">Qté</th>
                                                    <th class="w-36 px-3 py-2.5 text-right">P.U. *</th>
                                                    <th class="w-32 px-3 py-2.5 text-right">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-border">
                                                <tr
                                                    v-for="item in items"
                                                    :key="item.id"
                                                    class="hover:bg-muted/30"
                                                >
                                                    <td class="px-3 py-2.5 font-medium text-foreground">
                                                        {{ item.label }}
                                                    </td>
                                                    <td class="px-3 py-2.5 text-center tabular-nums text-muted-foreground">
                                                        {{ formatQuantity(item.quantity) }}
                                                    </td>
                                                    <td class="px-3 py-2.5">
                                                        <div class="relative">
                                                            <input
                                                                v-model.number="group.prices[item.id]"
                                                                type="number"
                                                                placeholder="0"
                                                                :class="[inputClass, 'pr-7 text-right']"
                                                                :readonly="!canEdit"
                                                            />
                                                            <span
                                                                class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-semibold text-muted-foreground"
                                                            >
                                                                F
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td class="px-3 py-2.5 text-right font-semibold tabular-nums text-foreground">
                                                        {{
                                                            group.prices[item.id]
                                                                ? formatAmount(group.prices[item.id]! * item.quantity)
                                                                : '0'
                                                        }}
                                                    </td>
                                                </tr>
                                            </tbody>
                                            <tfoot class="border-t border-border bg-muted/40">
                                                <tr>
                                                    <td
                                                        colspan="3"
                                                        class="px-3 py-2.5 text-right text-xs font-semibold uppercase text-muted-foreground"
                                                    >
                                                        Total offre
                                                    </td>
                                                    <td class="px-3 py-2.5 text-right text-base font-semibold tabular-nums text-foreground">
                                                        {{ formatAmount(groupTotal(group)) }}
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </template>
                                <div
                                    v-else
                                    class="rounded-lg border border-dashed border-border p-6 text-center"
                                >
                                    <p class="mb-2 text-sm font-medium text-foreground">Prix global</p>
                                    <input
                                        v-model.number="group.prices[0]"
                                        type="number"
                                        placeholder="Montant"
                                        :class="[inputClass, 'mx-auto max-w-[200px] text-center text-base font-semibold']"
                                        :readonly="!canEdit"
                                    />
                                </div>
                            </div>

                            <div class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-border dark:bg-muted/20">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Conditions & documents
                                </h3>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase text-muted-foreground">
                                        Délai livraison
                                    </label>
                                    <input
                                        v-model="group.delais_livraison"
                                        placeholder="ex: 7 jours"
                                        :class="inputClass"
                                        :readonly="!canEdit"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase text-muted-foreground">
                                        Garantie
                                    </label>
                                    <input
                                        v-model="group.garanties_offertes"
                                        placeholder="ex: 12 mois"
                                        :class="inputClass"
                                        :readonly="!canEdit"
                                    />
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="mb-1 block text-[11px] font-semibold uppercase text-muted-foreground">
                                            Conformité
                                        </label>
                                        <select
                                            v-model="group.conformite_reglementaire"
                                            :class="selectClass"
                                            :disabled="!canEdit"
                                        >
                                            <option :value="null">—</option>
                                            <option value="OUI">OUI</option>
                                            <option value="NON">NON</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-[11px] font-semibold uppercase text-muted-foreground">
                                            Acompte
                                        </label>
                                        <select
                                            v-model="group.acompte_requis"
                                            :class="selectClass"
                                            :disabled="!canEdit"
                                        >
                                            <option :value="null">—</option>
                                            <option value="OUI">OUI</option>
                                            <option value="NON">NON</option>
                                        </select>
                                    </div>
                                </div>
                                <div v-if="group.acompte_requis === 'OUI'">
                                    <label class="mb-1 block text-[11px] font-semibold uppercase text-muted-foreground">
                                        % Acompte
                                    </label>
                                    <input
                                        v-model.number="group.pourcentage_acompte"
                                        type="number"
                                        min="0"
                                        max="100"
                                        :class="inputClass"
                                        :readonly="!canEdit"
                                    />
                                </div>

                                <div class="border-t border-border pt-3">
                                    <label class="mb-2 block text-[11px] font-semibold uppercase text-muted-foreground">
                                        Offre / devis
                                    </label>
                                    <div v-if="canEdit" class="space-y-2">
                                        <input
                                            :id="`file-${gIdx}`"
                                            type="file"
                                            class="hidden"
                                            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                            @change="onFileSelect(gIdx, $event)"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="w-full border-slate-300"
                                            @click="triggerFileInput(gIdx)"
                                        >
                                            <Paperclip class="mr-2 size-4" />
                                            {{ group._file ? 'Changer le fichier' : 'Joindre le devis' }}
                                        </Button>
                                        <div
                                            v-if="group._file"
                                            class="flex items-center justify-between gap-2 rounded-md border border-border bg-background px-2 py-1.5"
                                        >
                                            <span class="truncate text-xs text-muted-foreground">
                                                {{ group._file.name }}
                                            </span>
                                            <button
                                                type="button"
                                                class="shrink-0 text-[10px] font-semibold text-red-600 hover:underline"
                                                @click="group._file = null"
                                            >
                                                Effacer
                                            </button>
                                        </div>
                                    </div>
                                    <div v-else-if="group.attachments?.length" class="space-y-1">
                                        <a
                                            v-for="att in group.attachments"
                                            :key="att.id"
                                            :href="`/storage/${att.path}`"
                                            target="_blank"
                                            class="flex items-center gap-2 rounded-md border border-slate-200 bg-white p-2 text-xs text-primary hover:bg-primary/5"
                                        >
                                            <Paperclip class="size-3 shrink-0" />
                                            <span class="truncate">{{ att.original_name }}</span>
                                        </a>
                                    </div>
                                    <p v-else class="text-center text-xs italic text-muted-foreground">
                                        Aucun document
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <Button
                        v-if="canEdit"
                        type="button"
                        variant="outline"
                        class="h-11 w-full border-dashed border-primary/40 text-primary hover:bg-primary/5 hover:text-primary"
                        @click="addFournisseur"
                    >
                        <Plus class="mr-2 size-4" />
                        Ajouter un fournisseur
                    </Button>
                </div>

                <!-- Panneau actions -->
                <aside class="lg:sticky lg:top-4 lg:self-start">
                    <div
                        class="flex flex-col gap-4 overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm"
                    >
                        <div
                            class="flex items-start gap-3 border-b border-border/80 bg-gradient-to-r from-primary/5 via-transparent to-transparent px-4 py-4 lg:px-5"
                        >
                            <div
                                class="rounded-lg bg-primary p-2 text-primary-foreground shadow-sm"
                            >
                                <FileSpreadsheet class="size-5" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-primary">
                                    Cotation
                                </p>
                                <h2 class="text-base font-semibold text-foreground">{{ props.fed.code }}</h2>
                                <p class="mt-0.5 text-sm text-muted-foreground">
                                    {{ filledFournisseurs }} fournisseur{{ filledFournisseurs > 1 ? 's' : '' }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-col gap-4 px-4 pb-4 lg:px-5 lg:pb-5">

                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-muted-foreground">Articles</dt>
                                <dd class="font-medium tabular-nums text-foreground">{{ items.length }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Offres</dt>
                                <dd class="font-medium tabular-nums text-foreground">
                                    {{ fournisseurOffres.length }}
                                </dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-muted-foreground">Statut enregistrement</dt>
                                <dd class="mt-0.5 font-medium text-foreground">
                                    {{ hasOffresEnregistrees ? 'Tableau enregistré' : 'Non enregistré' }}
                                </dd>
                            </div>
                        </dl>

                        <template v-if="canEdit">
                            <div
                                v-if="isFacilitiesHold && props.fed.facilities_comment"
                                class="rounded-lg border border-orange-300 bg-orange-50 p-3"
                            >
                                <p class="mb-1 text-xs font-semibold uppercase text-orange-800">
                                    Demande de Facilities
                                </p>
                                <p class="whitespace-pre-line text-sm text-orange-900">
                                    {{ props.fed.facilities_comment }}
                                </p>
                            </div>

                            <div class="border-t border-border pt-4">
                                <label class="mb-1.5 block text-sm font-medium text-foreground">
                                    Commentaire Facilities
                                    <span class="text-xs font-normal text-muted-foreground">(optionnel)</span>
                                </label>
                                <textarea
                                    v-model="transmitComment"
                                    rows="3"
                                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground"
                                    placeholder="Infos pour le responsable Facilities…"
                                />
                            </div>

                            <div class="flex flex-col gap-2">
                                <Button type="button" class="w-full" @click="saveOffres">
                                    <Save class="mr-2 size-4" />
                                    Enregistrer le tableau
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="w-full border-primary/30 text-primary hover:bg-primary/5 hover:text-primary"
                                    :disabled="!hasOffresEnregistrees"
                                    @click="transmitToFacilities"
                                >
                                    <Send class="mr-2 size-4" />
                                    {{ isFacilitiesHold ? 'Renvoyer à Facilities' : 'Envoyer à Facilities' }}
                                </Button>
                            </div>
                        </template>
                        <p
                            v-else
                            class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-muted-foreground dark:border-border dark:bg-muted/40"
                        >
                            Lecture seule — cette cotation n’est plus modifiable.
                        </p>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
