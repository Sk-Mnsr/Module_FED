<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { computed, ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import ValidationHistoryModal from '@/components/ValidationHistoryModal.vue';
import {
    ArrowLeft,
    CheckCircle2,
    ChevronDown,
    FileSpreadsheet,
    FileText,
    MessageSquareWarning,
    Paperclip,
    XCircle,
} from 'lucide-vue-next';

interface FedItem {
    id: number;
    label: string;
    quantity: number | null;
    description?: string | null;
    unit_price?: number | null;
    total_price?: number | null;
    budget_line?: BudgetLine | null;
}

interface FedAttachment {
    id: number;
    original_name: string;
    path: string;
}

interface FedRequester {
    name: string;
}

interface BudgetLine {
    id?: number;
    code?: string | null;
    label?: string | null;
}

interface Fed {
    id: number;
    code: string;
    date?: string | null;
    demandeur?: string | null;
    department?: string | null;
    fonction?: string | null;
    category?: string | null;
    subcategory?: string | null;
    beneficiaire?: string | null;
    motive?: string | null;
    estimated_total?: number | null;
    priority?: string | null;
    status: string;
    n1_avis?: string | null;
    n1_comment?: string | null;
    n1_action_at?: string | null;
    requester_signature?: string | null;
    n1_signature?: string | null;
    achats_comment?: string | null;
    achats_action_at?: string | null;
    achats_signature?: string | null;
    facilities_comment?: string | null;
    facilities_action_at?: string | null;
    facilities_signature?: string | null;
    offre_choisie_id?: number | null;
    items: FedItem[];
    attachments: FedAttachment[];
    requester?: FedRequester | null;
    budget_line?: BudgetLine | null;
    budget_lines?: BudgetLine[];
    expert_opinion_offre_id?: number | null;
    expert_opinion_comment?: string | null;
    expert_opinion_at?: string | null;
}

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

interface Props {
    fed: Fed & { fournisseur_offres?: FedFournisseurOffre[] };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Validations Facilities', href: '/feds/facilities' },
    { title: props.fed.code, href: '#' },
];

const formatAmount = (value?: number | string | null) => {
    if (value === null || value === undefined || value === '') return '—';
    const val = typeof value === 'string' ? parseFloat(value) : value;
    return `${new Intl.NumberFormat('fr-FR').format(val)} FCFA`;
};

const formatQuantity = (value?: number | string | null) => {
    if (value === null || value === undefined || value === '') return '—';
    const val = typeof value === 'string' ? parseFloat(value) : value;
    return Math.floor(val);
};

const formatDate = (value?: string | null) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
};

const formatBudgetLines = (fed: Fed) => {
    const uniqueCodes = [
        ...new Set(fed.items?.filter((item) => item.budget_line?.code).map((item) => item.budget_line!.code)),
    ];
    if (uniqueCodes.length > 0) {
        return uniqueCodes.join(' ; ');
    }
    return '—';
};

const comment = ref('');
const offreChoisieId = ref<number | null>(props.fed.offre_choisie_id ?? null);
const requestExpertOpinion = ref(false);
const showActionModal = ref(false);
const showComparatif = ref(false);
const pendingAction = ref<'approve' | 'reject' | 'needsInfo' | null>(null);

const openActionModal = (action: 'approve' | 'reject' | 'needsInfo') => {
    if (action === 'approve' && !offreChoisieId.value) {
        alert("Veuillez d'abord sélectionner l'offre retenue dans le tableau comparatif.");
        showComparatif.value = true;
        return;
    }
    pendingAction.value = action;
    showActionModal.value = true;
};

const canValidate = computed(() =>
    ['achats_approved', 'facilities_needs_info', 'expert_opinion_given'].includes(props.fed.status),
);
const isWaitingExpert = computed(() => props.fed.status === 'expert_opinion_pending');

const submitDecision = () => {
    const action = pendingAction.value;
    const c = comment.value?.trim();
    if (!c) {
        alert('Veuillez saisir votre commentaire avant de continuer.');
        return;
    }

    showActionModal.value = false;

    if (action === 'approve') {
        if (!confirm('Confirmer la validation de cette offre ?')) return;
        router.post(
            `/feds/facilities/${props.fed.id}/approve`,
            {
                comment: c,
                offre_choisie_id: offreChoisieId.value,
                request_expert_opinion: requestExpertOpinion.value,
            },
            { preserveScroll: true },
        );
    } else if (action === 'reject') {
        if (!confirm('Confirmer le rejet ?')) return;
        router.post(`/feds/facilities/${props.fed.id}/reject`, { comment: c }, { preserveScroll: true });
    } else if (action === 'needsInfo') {
        router.post(`/feds/facilities/${props.fed.id}/needs-info`, { comment: c }, { preserveScroll: true });
    }
};

const getArticleQuantity = (itemId?: number | null) => {
    if (!itemId) return 1;
    const item = props.fed.items?.find((i) => i.id == itemId);
    return item ? (item.quantity ?? 1) : 1;
};

const expandedGroups = ref<Record<number, boolean>>({});

const toggleDetails = (id: number) => {
    expandedGroups.value[id] = !expandedGroups.value[id];
};

interface SupplierGroup {
    id: number;
    fournisseur: string;
    fournisseur_id: number | null;
    total: number;
    offres: FedFournisseurOffre[];
    representative_id: number;
    delais: string;
    garantie: string;
    conformite: string;
    acompte: string;
    attachments: OffreAttachment[];
}

const groupedOffres = computed(() => {
    const existing = props.fed.fournisseur_offres ?? [];
    const groups: Record<string, SupplierGroup> = {};

    existing.forEach((o) => {
        const key = o.fournisseur_id ? `id_${o.fournisseur_id}` : `name_${o.fournisseur}`;
        if (!groups[key]) {
            groups[key] = {
                id: o.id ?? Math.random(),
                fournisseur: o.fournisseur,
                fournisseur_id: o.fournisseur_id ?? null,
                total: 0,
                offres: [],
                representative_id: o.id ?? 0,
                delais: o.delais_livraison ?? '—',
                garantie: o.garanties_offertes ?? '—',
                conformite: o.conformite_reglementaire ?? '—',
                acompte:
                    o.acompte_requis === 'OUI'
                        ? `${o.pourcentage_acompte ?? 0}%`
                        : o.acompte_requis || '—',
                attachments: o.attachments ?? [],
            };
        }
        groups[key].offres.push(o);
        const qty = getArticleQuantity(o.fed_item_id);
        groups[key].total += (o.prix_unitaire ?? 0) * qty;
    });

    return Object.values(groups);
});

const selectedGroup = computed(() =>
    groupedOffres.value.find((g) => g.representative_id === offreChoisieId.value) ?? null,
);

const isSelectedOffer = (group: SupplierGroup) =>
    offreChoisieId.value === group.representative_id ||
    group.offres.some((o) => o.id === offreChoisieId.value || o.id === props.fed.offre_choisie_id);

const statusBadge = (s: string) => {
    const badges: Record<string, string> = {
        achats_approved: 'bg-blue-100 text-blue-700 border border-blue-200',
        expert_opinion_pending: 'bg-amber-100 text-amber-800 border border-amber-200',
        expert_opinion_given: 'bg-green-100 text-green-700 border border-green-200',
        facilities_needs_info: 'bg-orange-100 text-orange-700 border border-orange-200',
        facilities_approved: 'bg-green-100 text-green-700 border border-green-200',
        facilities_rejected: 'bg-red-100 text-red-700 border border-red-200',
    };
    return badges[s] ?? 'bg-gray-100 text-gray-700 border border-gray-200';
};

const statusLabel = (s: string) => {
    const labels: Record<string, string> = {
        achats_approved: 'En attente Facilities',
        expert_opinion_pending: 'En attente retour métier',
        expert_opinion_given: 'Avis expert reçu',
        facilities_needs_info: 'Complément demandé',
        facilities_approved: 'Approuvée Facilities',
        facilities_rejected: 'Rejetée Facilities',
    };
    return labels[s] ?? s;
};
</script>

<template>
    <Head :title="`Validation Facilities - ${props.fed.code}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-0 flex-1 flex-col gap-4 p-4 lg:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
                <div class="flex flex-wrap items-center gap-3">
                    <Button as-child variant="outline" size="sm">
                        <Link href="/feds/facilities" class="inline-flex items-center gap-1.5">
                            <ArrowLeft class="size-4" />
                            Retour à la liste
                        </Link>
                    </Button>
                    <span
                        :class="[
                            'inline-flex rounded-full px-3 py-1 text-sm font-medium',
                            statusBadge(props.fed.status),
                        ]"
                    >
                        {{ statusLabel(props.fed.status) }}
                    </span>
                </div>
            </div>

            <div
                v-if="isWaitingExpert"
                class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 print:hidden"
            >
                <p class="font-semibold">En attente du retour métier (avis expert)</p>
                <p class="mt-0.5 text-amber-800">
                    Le manager N+1 doit donner sa recommandation avant que vous puissiez valider.
                </p>
            </div>

            <div class="grid min-h-0 flex-1 gap-4 lg:grid-cols-[minmax(0,1.45fr)_minmax(300px,0.7fr)]">
                <!-- Gauche : fiche officielle -->
                <div class="order-2 flex min-h-0 flex-col gap-4 overflow-y-auto lg:order-1">
                    <div class="rounded-xl border border-border bg-muted/30 p-3 lg:p-4">
                        <div class="rounded-lg border-2 border-gray-900 bg-white p-5 shadow-sm lg:p-6">
                            <div class="mb-6 flex items-start justify-between gap-4 border-b border-gray-300 pb-4">
                                <img src="/logo_Cofina.png" alt="Cofina" class="h-12 object-contain lg:h-14" />
                                <div class="text-right">
                                    <h1 class="text-lg font-bold uppercase text-gray-900 lg:text-xl">
                                        Fiche d'Engagement de dépense
                                    </h1>
                                    <p class="mt-1 text-sm font-medium">
                                        Réf. : FED n°
                                        <span class="inline-block min-w-[120px] border-b border-gray-400 font-semibold">
                                            {{ props.fed.code }}
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <div class="mb-6 border-2 border-gray-900 p-4">
                                <h2 class="mb-4 text-base font-bold uppercase">Demande</h2>
                                <div class="grid gap-3 text-sm">
                                    <div class="grid grid-cols-[140px_1fr] gap-2 sm:grid-cols-[170px_1fr]">
                                        <span class="font-medium text-gray-600">Date :</span>
                                        <span>{{ formatDate(props.fed.date) }}</span>
                                    </div>
                                    <div class="grid grid-cols-[140px_1fr] gap-2 sm:grid-cols-[170px_1fr]">
                                        <span class="font-medium text-gray-600">Demandeur :</span>
                                        <span class="font-semibold uppercase">
                                            {{ props.fed.demandeur || props.fed.requester?.name || '—' }}
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-[140px_1fr] gap-2 sm:grid-cols-[170px_1fr]">
                                        <span class="font-medium text-gray-600">Fonction :</span>
                                        <span class="uppercase">{{ props.fed.fonction || '—' }}</span>
                                    </div>
                                    <div class="grid grid-cols-[140px_1fr] gap-2 sm:grid-cols-[170px_1fr]">
                                        <span class="font-medium text-gray-600">Département :</span>
                                        <span>{{ props.fed.department || '—' }}</span>
                                    </div>
                                    <div
                                        class="grid grid-cols-[140px_1fr] gap-2 rounded border-l-4 border-red-500 bg-red-50/80 px-2 py-1.5 sm:grid-cols-[170px_1fr]"
                                    >
                                        <span class="font-medium text-red-800">Ligne(s) budgétaire(s) :</span>
                                        <span class="font-medium uppercase text-red-900">
                                            {{ formatBudgetLines(props.fed) }}
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-[140px_1fr] gap-2 sm:grid-cols-[170px_1fr]">
                                        <span class="font-medium text-gray-600">Motif :</span>
                                        <span class="font-medium uppercase">{{ props.fed.motive || '—' }}</span>
                                    </div>
                                    <div class="grid grid-cols-[140px_1fr] gap-2 sm:grid-cols-[170px_1fr]">
                                        <span class="font-medium text-gray-600">Bénéficiaire(s) :</span>
                                        <span>{{ props.fed.beneficiaire || '—' }}</span>
                                    </div>

                                    <div class="mt-6 grid grid-cols-1 gap-6 border-t border-gray-300 pt-6 sm:grid-cols-2">
                                        <div class="flex flex-col items-center">
                                            <div
                                                class="mb-2 flex h-20 w-full max-w-[200px] items-center justify-center border-2 border-gray-500 bg-gray-50 p-2"
                                            >
                                                <img
                                                    v-if="props.fed.requester_signature"
                                                    :src="props.fed.requester_signature"
                                                    alt="Signature demandeur"
                                                    class="max-h-full max-w-full object-contain"
                                                />
                                            </div>
                                            <span class="text-xs font-medium">Demandeur</span>
                                        </div>
                                        <div class="flex flex-col items-center">
                                            <div
                                                class="mb-2 flex h-20 w-full max-w-[200px] items-center justify-center border-2 border-gray-500 bg-gray-50 p-2"
                                            >
                                                <img
                                                    v-if="props.fed.n1_signature"
                                                    :src="props.fed.n1_signature"
                                                    alt="Signature Manager"
                                                    class="max-h-full max-w-full object-contain"
                                                />
                                            </div>
                                            <span class="text-xs font-medium">Manager (N+1)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="props.fed.items?.length" class="mb-6 border border-gray-400 p-4">
                                <h2 class="mb-3 text-sm font-bold uppercase">Articles / Services</h2>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr
                                                class="border-b border-gray-400 bg-gray-100 text-[11px] uppercase text-gray-700"
                                            >
                                                <th class="px-2 py-2 text-left font-bold">Ligne(s) Budgétaire(s)</th>
                                                <th class="px-2 py-2 text-left font-bold">Intitulé</th>
                                                <th class="px-2 py-2 text-center font-bold">Quantité</th>
                                                <th class="px-2 py-2 text-center font-bold">Description</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="item in props.fed.items"
                                                :key="item.id"
                                                class="border-b border-gray-200"
                                            >
                                                <td class="px-2 py-2 font-medium uppercase text-red-700">
                                                    {{ item.budget_line?.code || '—' }}
                                                </td>
                                                <td class="px-2 py-2 uppercase">{{ item.label }}</td>
                                                <td class="px-2 py-2 text-center">
                                                    {{ formatQuantity(item.quantity) }}
                                                </td>
                                                <td class="px-2 py-2 text-center text-xs italic text-gray-600">
                                                    {{ item.description || '—' }}
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr class="bg-gray-100 font-medium">
                                                <td colspan="3" class="px-2 py-2">Montant total estimé</td>
                                                <td class="px-2 py-2 text-right">
                                                    {{ formatAmount(props.fed.estimated_total) }}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <div class="border border-gray-400 p-4">
                                <h2 class="mb-3 flex items-center gap-2 text-sm font-bold uppercase">
                                    <Paperclip class="size-4" />
                                    Pièces jointes
                                </h2>
                                <div v-if="props.fed.attachments?.length" class="space-y-1 text-sm">
                                    <a
                                        v-for="attachment in props.fed.attachments"
                                        :key="attachment.id"
                                        :href="`/storage/${attachment.path}`"
                                        target="_blank"
                                        class="block text-blue-600 hover:underline"
                                    >
                                        {{ attachment.original_name }}
                                    </a>
                                </div>
                                <p v-else class="text-sm text-muted-foreground">Aucune pièce jointe.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Droite : actions Facilities -->
                <aside class="order-1 print:hidden lg:sticky lg:top-4 lg:order-2 lg:self-start">
                    <div class="flex flex-col gap-4 rounded-xl border border-border bg-card p-4 shadow-sm lg:p-5">
                        <div class="flex items-start gap-3 border-b border-border pb-4">
                            <div class="rounded-lg bg-slate-100 p-2 text-slate-700">
                                <FileText class="size-5" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Validation Facilities
                                </p>
                                <h2 class="truncate text-base font-semibold text-foreground">{{ props.fed.code }}</h2>
                                <p class="mt-0.5 text-sm text-muted-foreground">
                                    {{ props.fed.demandeur || props.fed.requester?.name || 'Demandeur' }}
                                </p>
                            </div>
                        </div>

                        <dl class="grid grid-cols-1 gap-2 text-sm">
                            <div>
                                <dt class="text-muted-foreground">Statut</dt>
                                <dd class="mt-1">
                                    <span
                                        :class="[
                                            'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                                            statusBadge(props.fed.status),
                                        ]"
                                    >
                                        {{ statusLabel(props.fed.status) }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Montant estimé</dt>
                                <dd class="mt-0.5 font-semibold tabular-nums text-foreground">
                                    {{ formatAmount(props.fed.estimated_total) }}
                                </dd>
                            </div>
                            <div v-if="selectedGroup">
                                <dt class="text-muted-foreground">Offre retenue</dt>
                                <dd class="mt-0.5">
                                    <p class="font-semibold uppercase text-foreground">
                                        {{ selectedGroup.fournisseur }}
                                    </p>
                                    <p class="tabular-nums text-sm text-muted-foreground">
                                        {{ formatAmount(selectedGroup.total) }}
                                    </p>
                                </dd>
                            </div>
                            <div v-else-if="canValidate">
                                <dt class="text-muted-foreground">Offre retenue</dt>
                                <dd class="mt-0.5 text-sm text-amber-700">Aucune sélection</dd>
                            </div>
                        </dl>

                        <div
                            v-if="props.fed.expert_opinion_comment"
                            class="rounded-lg border border-border bg-muted/40 p-3 text-sm"
                        >
                            <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">
                                Avis expert métier
                            </p>
                            <p class="whitespace-pre-line text-foreground">
                                {{ props.fed.expert_opinion_comment }}
                            </p>
                        </div>

                        <div
                            v-if="props.fed.achats_comment"
                            class="rounded-lg border border-border bg-muted/40 p-3 text-sm"
                        >
                            <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">
                                Commentaire Achats
                            </p>
                            <p class="whitespace-pre-line text-foreground">{{ props.fed.achats_comment }}</p>
                        </div>

                        <Button
                            v-if="groupedOffres.length"
                            type="button"
                            variant="outline"
                            class="w-full"
                            @click="showComparatif = true"
                        >
                            <FileSpreadsheet class="mr-2 size-4" />
                            {{
                                canValidate
                                    ? 'Choisir / voir le tableau comparatif'
                                    : 'Voir le tableau comparatif'
                            }}
                        </Button>
                        <p
                            v-else
                            class="rounded-lg border border-dashed border-border p-3 text-center text-sm text-muted-foreground"
                        >
                            Aucune offre saisie pour cette demande.
                        </p>

                        <div v-if="canValidate" class="space-y-3 border-t border-border pt-4">
                            <h3 class="text-sm font-semibold text-foreground">Votre décision</h3>

                            <label
                                class="flex cursor-pointer items-start gap-3 rounded-lg border border-border bg-muted/20 p-3"
                            >
                                <input
                                    v-model="requestExpertOpinion"
                                    type="checkbox"
                                    class="mt-0.5 size-4 rounded border-input"
                                />
                                <span>
                                    <span class="block text-sm font-medium text-foreground">
                                        Solliciter l'avis expert (N+1)
                                    </span>
                                    <span class="mt-0.5 block text-xs text-muted-foreground">
                                        Le manager recevra la FED avant votre validation finale.
                                    </span>
                                </span>
                            </label>

                            <div class="flex flex-col gap-2">
                                <Button
                                    type="button"
                                    class="w-full bg-green-600 text-white hover:bg-green-700"
                                    @click="openActionModal('approve')"
                                >
                                    <CheckCircle2 class="mr-2 size-4" />
                                    Valider l'offre retenue
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="w-full border-orange-200 text-orange-700 hover:bg-orange-50"
                                    @click="openActionModal('needsInfo')"
                                >
                                    <MessageSquareWarning class="mr-2 size-4" />
                                    Demander un complément
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="w-full border-red-200 text-red-700 hover:bg-red-50"
                                    @click="openActionModal('reject')"
                                >
                                    <XCircle class="mr-2 size-4" />
                                    Rejeter
                                </Button>
                            </div>
                        </div>

                        <div
                            v-else-if="props.fed.facilities_comment"
                            class="rounded-lg border border-border bg-muted/40 p-3 text-sm"
                        >
                            <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">
                                Avis Facilities
                            </p>
                            <p class="whitespace-pre-line text-foreground">{{ props.fed.facilities_comment }}</p>
                            <p v-if="props.fed.facilities_action_at" class="mt-2 text-xs text-muted-foreground">
                                {{ new Date(props.fed.facilities_action_at).toLocaleString('fr-FR') }}
                            </p>
                        </div>

                        <p
                            v-else-if="!canValidate && !isWaitingExpert"
                            class="rounded-lg border border-border bg-muted/40 p-3 text-sm text-muted-foreground"
                        >
                            Cette FED n’est plus en attente de validation Facilities.
                        </p>

                        <div class="border-t border-border pt-4">
                            <ValidationHistoryModal :fed="props.fed" />
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        <!-- Modale tableau comparatif -->
        <Dialog :open="showComparatif" @update:open="showComparatif = $event">
            <DialogContent class="max-h-[90vh] w-full max-w-3xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <FileSpreadsheet class="size-5" />
                        Tableau comparatif des offres
                    </DialogTitle>
                    <DialogDescription>
                        {{ groupedOffres.length }} fournisseur(s) —
                        {{ canValidate ? 'sélectionnez l’offre retenue' : 'consultation' }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-3 py-2">
                    <div
                        v-for="(group, gIdx) in groupedOffres"
                        :key="gIdx"
                        class="overflow-hidden rounded-xl border-2 shadow-sm transition-all"
                        :class="
                            isSelectedOffer(group)
                                ? 'border-green-500 bg-green-50/20 ring-1 ring-green-200'
                                : 'border-border bg-background'
                        "
                    >
                        <div
                            class="flex w-full items-center gap-3 px-4 py-3"
                            :class="
                                isSelectedOffer(group)
                                    ? 'border-b border-green-100 bg-green-50'
                                    : 'border-b border-border bg-muted/30'
                            "
                        >
                            <div v-if="canValidate" class="shrink-0">
                                <input
                                    v-model="offreChoisieId"
                                    type="radio"
                                    :name="`offre-${props.fed.id}`"
                                    :value="group.representative_id"
                                    class="size-4 cursor-pointer text-green-600 focus:ring-green-500"
                                />
                            </div>
                            <span
                                v-else-if="isSelectedOffer(group)"
                                class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-green-600 text-xs font-bold text-white"
                            >
                                ✓
                            </span>
                            <span v-else class="size-6 shrink-0" />

                            <button
                                type="button"
                                class="min-w-0 flex-1 text-left"
                                @click="toggleDetails(group.id)"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-base font-bold uppercase text-foreground">
                                            {{ group.fournisseur }}
                                        </h3>
                                        <span
                                            v-if="
                                                group.offres.some(
                                                    (o) => o.id === props.fed.expert_opinion_offre_id,
                                                )
                                            "
                                            class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-800"
                                        >
                                            Choix expert
                                        </span>
                                        <ChevronDown
                                            class="size-4 text-muted-foreground transition-transform"
                                            :class="{ 'rotate-180': expandedGroups[group.id] }"
                                        />
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[10px] font-semibold uppercase text-muted-foreground">
                                            Montant total TTC
                                        </p>
                                        <p class="text-lg font-bold tabular-nums text-foreground">
                                            {{ formatAmount(group.total) }}
                                        </p>
                                    </div>
                                </div>
                            </button>
                        </div>

                        <div
                            class="flex flex-wrap items-center gap-x-4 gap-y-1 border-b border-border bg-background px-4 py-2 text-xs"
                        >
                            <span>
                                <span class="font-semibold uppercase text-muted-foreground">Délai :</span>
                                {{ group.delais }}
                            </span>
                            <span>
                                <span class="font-semibold uppercase text-muted-foreground">Garantie :</span>
                                {{ group.garantie }}
                            </span>
                            <span>
                                <span class="font-semibold uppercase text-muted-foreground">Conformité :</span>
                                <span
                                    :class="
                                        group.conformite === 'OUI'
                                            ? 'font-semibold text-green-700'
                                            : 'font-semibold text-orange-700'
                                    "
                                >
                                    {{ group.conformite }}
                                </span>
                            </span>
                            <span>
                                <span class="font-semibold uppercase text-muted-foreground">Acompte :</span>
                                {{ group.acompte }}
                            </span>
                        </div>

                        <div v-show="expandedGroups[group.id]" class="border-b border-border bg-muted/20 p-3">
                            <table class="w-full text-xs">
                                <thead class="bg-muted text-[10px] font-semibold uppercase text-muted-foreground">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Article</th>
                                        <th class="w-16 px-3 py-2 text-center">Qté</th>
                                        <th class="w-28 px-3 py-2 text-right">P.U.</th>
                                        <th class="w-28 px-3 py-2 text-right">Total TTC</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr v-for="o in group.offres" :key="o.id">
                                        <td class="px-3 py-2 font-medium text-foreground">
                                            {{
                                                props.fed.items?.find((i) => i.id === o.fed_item_id)?.label ||
                                                'Montant global'
                                            }}
                                        </td>
                                        <td class="px-3 py-2 text-center text-muted-foreground">
                                            {{ formatQuantity(getArticleQuantity(o.fed_item_id)) }}
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            {{ formatAmount(o.prix_unitaire) }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-semibold">
                                            {{
                                                formatAmount(
                                                    (o.prix_unitaire ?? 0) * getArticleQuantity(o.fed_item_id),
                                                )
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 bg-muted/30 px-4 py-2">
                            <template v-if="group.attachments.length">
                                <a
                                    v-for="att in group.attachments"
                                    :key="att.id"
                                    :href="`/storage/${att.path}`"
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 rounded-md border border-border bg-background px-2.5 py-1 text-[11px] font-medium text-blue-600 hover:bg-muted"
                                >
                                    <Paperclip class="size-3" />
                                    {{ att.original_name }}
                                </a>
                            </template>
                            <span v-else class="text-[11px] italic text-muted-foreground">
                                Aucun document joint
                            </span>
                        </div>
                    </div>
                </div>

                <DialogFooter v-if="canValidate">
                    <Button type="button" @click="showComparatif = false">
                        {{ offreChoisieId ? 'Confirmer la sélection' : 'Fermer' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Modale de décision -->
        <Dialog v-model:open="showActionModal">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            pendingAction === 'approve'
                                ? "Validation de l'offre"
                                : pendingAction === 'reject'
                                  ? 'Rejet de la demande'
                                  : 'Demande de complément'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        Veuillez accompagner votre action d'un commentaire explicatif.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-2">
                    <div v-if="pendingAction === 'approve' && selectedGroup" class="rounded-lg bg-muted/40 p-3 text-sm">
                        <p class="text-muted-foreground">Offre retenue</p>
                        <p class="font-semibold uppercase">{{ selectedGroup.fournisseur }}</p>
                        <p class="tabular-nums">{{ formatAmount(selectedGroup.total) }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">
                            Commentaire / Avis <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            v-model="comment"
                            rows="4"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            placeholder="Saisissez votre commentaire obligatoire…"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showActionModal = false">Annuler</Button>
                    <Button
                        :class="
                            pendingAction === 'approve'
                                ? 'bg-green-600 text-white hover:bg-green-700'
                                : pendingAction === 'reject'
                                  ? 'bg-red-600 text-white hover:bg-red-700'
                                  : 'bg-orange-600 text-white hover:bg-orange-700'
                        "
                        @click="submitDecision"
                    >
                        Confirmer
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
