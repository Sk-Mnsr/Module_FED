<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { computed, ref } from 'vue';
import {
    AlertCircle,
    ArrowLeft,
    ArrowRightLeft,
    Calculator,
    CheckCircle2,
    FileText,
    History,
    Paperclip,
    XCircle,
} from 'lucide-vue-next';
import ValidationHistoryModal from '@/components/ValidationHistoryModal.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface FedItem {
    id: number;
    label: string;
    quantity: number | null;
    description?: string | null;
    unit_price?: number | null;
    total_price?: number | null;
    budget_line_id?: number | null;
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
    id: number;
    code?: string | null;
    label?: string | null;
    montant_estime?: number | null;
    is_reclassified?: boolean;
}

interface OffreAttachment {
    id: number;
    original_name: string;
    path: string;
}

interface FedFournisseurOffre {
    id?: number;
    fournisseur: string;
    fed_item_id?: number | null;
    prix_unitaire?: number | null;
    delais_livraison?: string | null;
    garanties_offertes?: string | null;
    conformite_reglementaire?: string | null;
    attachments?: OffreAttachment[];
}

interface BudgetLineHistory {
    id: number;
    action: string;
    montant_transfere?: number | null;
    from_montant_before?: number | null;
    from_montant_after?: number | null;
    to_montant_before?: number | null;
    to_montant_after?: number | null;
    note?: string | null;
    status?: string | null;
    created_at: string;
    user?: { name: string } | null;
    fromLine?: BudgetLine | null;
    toLine?: BudgetLine | null;
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
    daf_comment?: string | null;
    cg_budget_status?: string | null;
    cg_comment?: string | null;
    cg_action_at?: string | null;
    offre_choisie_id?: number | null;
    items: FedItem[];
    attachments: FedAttachment[];
    requester?: FedRequester | null;
    budget_line_id?: number | null;
    budget_line?: BudgetLine | null;
    budget_lines?: BudgetLine[];
    fournisseur_offres?: FedFournisseurOffre[];
    budget_line_histories?: BudgetLineHistory[];
}

interface Props {
    fed: Fed;
    budgetLines: BudgetLine[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Contrôle de Gestion', href: '/feds/cg' },
    { title: props.fed.code, href: '#' },
];

const formatAmount = (value?: number | null) => {
    if (value === null || value === undefined) return '—';
    const formattedNum = new Intl.NumberFormat('fr-FR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
    return `${formattedNum} FCFA`;
};

const formatQuantity = (value?: number | null) => {
    if (value === null || value === undefined) return '—';
    return Math.round(value).toString();
};

const formatDate = (value?: string | null) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
};

const formatDateTime = (value?: string | null) => {
    if (!value) return '—';
    return new Date(value).toLocaleString('fr-FR');
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

const statusLabel = (status: string) => {
    const labels: Record<string, string> = {
        facilities_approved: 'En attente CG',
        waiting_daf_reclass_approval: 'Attente reclassement DAF',
        cg_treated: 'Traité CG → DAF',
        daf_approved: 'Validée DAF',
        daf_rejected: 'Rejetée DAF',
        bon_de_commande: 'Bon de commande',
    };
    return labels[status] ?? status;
};

const statusBadge = (status: string) => {
    const badges: Record<string, string> = {
        facilities_approved: 'bg-cyan-100 text-cyan-800 border border-cyan-200',
        waiting_daf_reclass_approval: 'bg-amber-100 text-amber-800 border border-amber-200',
        cg_treated: 'bg-green-100 text-green-800 border border-green-200',
        daf_approved: 'bg-green-100 text-green-800 border border-green-200',
        daf_rejected: 'bg-red-100 text-red-700 border border-red-200',
        bon_de_commande: 'bg-emerald-100 text-emerald-800 border border-emerald-200',
    };
    return badges[status] ?? 'bg-gray-100 text-gray-700 border border-gray-200';
};

const canTreat = computed(() => props.fed.status === 'facilities_approved');
const isVerificationModalOpen = ref(false);
const showBudgetHistory = ref(false);

const itemBudgetLines = ref<Record<number, number>>({});

props.fed.items.forEach((item) => {
    itemBudgetLines.value[item.id] = item.budget_line_id || props.fed.budget_line_id || 0;
});

const offreRetenue = computed(() =>
    props.fed.fournisseur_offres?.find((o) => o.id === props.fed.offre_choisie_id) ?? null,
);

const itemRetainedAmount = (item: FedItem) => {
    if (offreRetenue.value?.prix_unitaire != null) {
        return (offreRetenue.value.prix_unitaire ?? 0) * (item.quantity ?? 1);
    }
    return item.total_price ?? 0;
};

const groupedItemsByLine = computed(() => {
    const groups: Record<number, { line: BudgetLine; items: FedItem[]; total: number }> = {};

    props.fed.items.forEach((item) => {
        const currentLineId = itemBudgetLines.value[item.id];
        const line = currentLineId
            ? props.budgetLines.find((l) => l.id === currentLineId)
            : item.budget_line || props.fed.budget_line;

        if (!line) return;
        if (!groups[line.id]) {
            groups[line.id] = { line, items: [], total: 0 };
        }
        groups[line.id].items.push(item);
        groups[line.id].total += itemRetainedAmount(item);
    });
    return Object.values(groups);
});

const isOutOfBudget = computed(() => {
    if (props.fed.status !== 'facilities_approved') {
        return props.fed.cg_budget_status === 'out_of_budget';
    }
    return groupedItemsByLine.value.some((group) => group.total > (group.line.montant_estime ?? 0));
});

const hasOverrunLine = computed(() =>
    groupedItemsByLine.value.some((group) => group.total > (group.line.montant_estime ?? 0)),
);

const totalRetenu = computed(() => groupedItemsByLine.value.reduce((sum, g) => sum + g.total, 0));

const comment = ref('');

const submitDecision = (forceOutOfBudget = false) => {
    const c = comment.value?.trim();
    const finalStatus = forceOutOfBudget || isOutOfBudget.value ? 'out_of_budget' : 'in_budget';

    if (!confirm('Confirmer la vérification budgétaire ?')) return;

    router.post(
        `/feds/cg/${props.fed.id}/treat`,
        {
            cg_budget_status: finalStatus,
            comment: c,
            item_budget_lines: itemBudgetLines.value,
        },
        { preserveScroll: true },
    );
};

const actionLabel = (action: string) => {
    const map: Record<string, string> = {
        change_line: 'Changement de ligne',
        transfer_amount: 'Transfert de montant',
    };
    return map[action] ?? action;
};

const hasBudgetHistory = computed(
    () => (props.fed.budget_line_histories?.length ?? 0) > 0,
);
</script>

<template>
    <Head :title="`Vérification CG - ${props.fed.code}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-0 flex-1 flex-col gap-4 p-4 lg:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
                <div class="flex flex-wrap items-center gap-3">
                    <Button as-child variant="outline" size="sm">
                        <Link href="/feds/cg" class="inline-flex items-center gap-1.5">
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
                    <span
                        v-if="props.fed.cg_budget_status"
                        :class="[
                            'inline-flex rounded-full px-3 py-1 text-sm font-medium',
                            props.fed.cg_budget_status === 'in_budget'
                                ? 'bg-emerald-100 text-emerald-800 border border-emerald-200'
                                : 'bg-red-100 text-red-700 border border-red-200',
                        ]"
                    >
                        {{
                            props.fed.cg_budget_status === 'in_budget' ? 'Budget OK' : 'Hors budget'
                        }}
                    </span>
                </div>
            </div>

            <div class="grid min-h-0 flex-1 gap-4 lg:grid-cols-[minmax(0,1.45fr)_minmax(300px,0.7fr)]">
                <!-- Gauche : fiche + analyse budgétaire -->
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
                                    <div class="grid grid-cols-[140px_1fr] gap-2 sm:grid-cols-[170px_1fr]">
                                        <span class="font-medium text-gray-600">Offre retenue :</span>
                                        <span class="font-semibold text-foreground">
                                            <template v-if="offreRetenue">
                                                {{ offreRetenue.fournisseur }}
                                                (PU :
                                                {{ formatAmount(offreRetenue.prix_unitaire) }})
                                            </template>
                                            <template v-else>Non statué</template>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="props.fed.attachments?.length"
                                class="border border-gray-400 p-4"
                            >
                                <h2 class="mb-3 flex items-center gap-2 text-sm font-bold uppercase">
                                    <Paperclip class="size-4" />
                                    Pièces jointes
                                </h2>
                                <div class="space-y-1 text-sm">
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
                            </div>
                        </div>
                    </div>

                    <!-- Cartes lignes budgétaires -->
                    <div class="space-y-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-foreground">
                            Analyse budgétaire
                        </h2>

                        <div
                            v-for="(group, idx) in groupedItemsByLine"
                            :key="idx"
                            class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-3 bg-slate-800 px-4 py-3"
                            >
                                <div class="min-w-0">
                                    <p class="text-sm font-bold uppercase tracking-wide text-white">
                                        {{ group.line.code }}
                                    </p>
                                    <p class="truncate text-xs text-slate-300">{{ group.line.label }}</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-4">
                                    <div class="text-right">
                                        <p class="text-[10px] font-semibold uppercase text-slate-400">
                                            Solde disponible
                                        </p>
                                        <p class="text-sm font-bold tabular-nums text-white">
                                            {{ formatAmount(group.line.montant_estime) }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[10px] font-semibold uppercase text-slate-400">
                                            FED consommé
                                        </p>
                                        <p class="text-sm font-bold tabular-nums text-sky-300">
                                            {{ formatAmount(group.total) }}
                                        </p>
                                    </div>
                                    <span
                                        v-if="group.total > (group.line.montant_estime ?? 0)"
                                        class="inline-flex items-center gap-1 rounded bg-red-500/20 px-2 py-0.5 text-xs font-semibold uppercase text-red-300"
                                    >
                                        <XCircle class="size-3" />
                                        Hors budget
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 rounded bg-emerald-500/20 px-2 py-0.5 text-xs font-semibold uppercase text-emerald-300"
                                    >
                                        <CheckCircle2 class="size-3" />
                                        Budget OK
                                    </span>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="border-b border-border bg-muted/40 text-[11px] uppercase text-muted-foreground">
                                        <tr>
                                            <th class="px-4 py-2.5 text-left font-semibold">Article</th>
                                            <th class="w-24 px-4 py-2.5 text-center font-semibold">Qté</th>
                                            <th class="w-40 px-4 py-2.5 text-right font-semibold">
                                                Montant retenu
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        <tr v-for="item in group.items" :key="item.id">
                                            <td class="px-4 py-2.5 font-medium uppercase text-foreground">
                                                {{ item.label }}
                                            </td>
                                            <td class="px-4 py-2.5 text-center text-muted-foreground">
                                                {{ formatQuantity(item.quantity) }}
                                            </td>
                                            <td class="px-4 py-2.5 text-right font-semibold tabular-nums">
                                                {{ formatAmount(itemRetainedAmount(item)) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="border-t border-border bg-muted/30 font-semibold">
                                            <td colspan="2" class="px-4 py-2.5 text-right text-xs uppercase">
                                                Total ligne
                                            </td>
                                            <td class="px-4 py-2.5 text-right tabular-nums">
                                                {{ formatAmount(group.total) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div
                                v-if="canTreat && group.total > (group.line.montant_estime ?? 0)"
                                class="flex flex-wrap items-center justify-between gap-3 border-t border-red-100 bg-red-50 px-4 py-3"
                            >
                                <div class="flex items-center gap-2 text-sm text-red-900">
                                    <AlertCircle class="size-4 shrink-0" />
                                    <span class="font-medium">Dépassement sur cette ligne</span>
                                </div>
                                <Button as-child size="sm" class="bg-amber-600 text-white hover:bg-amber-700">
                                    <Link :href="`/feds/cg/${props.fed.id}/reclasser`">
                                        <ArrowRightLeft class="mr-1.5 size-4" />
                                        Reclasser
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Droite : actions CG -->
                <aside class="order-1 print:hidden lg:sticky lg:top-4 lg:order-2 lg:self-start">
                    <div class="flex flex-col gap-4 rounded-xl border border-border bg-card p-4 shadow-sm lg:p-5">
                        <div class="flex items-start gap-3 border-b border-border pb-4">
                            <div class="rounded-lg bg-slate-100 p-2 text-slate-700">
                                <FileText class="size-5" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Contrôle de gestion
                                </p>
                                <h2 class="truncate text-base font-semibold text-foreground">
                                    {{ props.fed.code }}
                                </h2>
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
                                <dt class="text-muted-foreground">Total retenu</dt>
                                <dd class="mt-0.5 font-semibold tabular-nums text-foreground">
                                    {{ formatAmount(totalRetenu) }}
                                </dd>
                            </div>
                            <div v-if="offreRetenue">
                                <dt class="text-muted-foreground">Fournisseur</dt>
                                <dd class="mt-0.5 font-medium uppercase text-foreground">
                                    {{ offreRetenue.fournisseur }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Couverture budgétaire</dt>
                                <dd class="mt-1">
                                    <span
                                        v-if="hasOverrunLine || props.fed.cg_budget_status === 'out_of_budget'"
                                        class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700"
                                    >
                                        <XCircle class="size-3" />
                                        Hors budget
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800"
                                    >
                                        <CheckCircle2 class="size-3" />
                                        Budget OK
                                    </span>
                                </dd>
                            </div>
                        </dl>

                        <div
                            v-if="props.fed.cg_comment"
                            class="rounded-lg border border-border bg-muted/40 p-3 text-sm"
                        >
                            <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">
                                Commentaire CG
                            </p>
                            <p class="whitespace-pre-line text-foreground">{{ props.fed.cg_comment }}</p>
                            <p v-if="props.fed.cg_action_at" class="mt-2 text-xs text-muted-foreground">
                                {{ formatDateTime(props.fed.cg_action_at) }}
                            </p>
                        </div>

                        <div v-if="canTreat" class="flex flex-col gap-2 border-t border-border pt-4">
                            <h3 class="text-sm font-semibold text-foreground">Votre décision</h3>
                            <Button
                                type="button"
                                class="w-full bg-amber-600 text-white hover:bg-amber-700"
                                @click="isVerificationModalOpen = true"
                            >
                                <Calculator class="mr-2 size-4" />
                                Vérification budgétaire
                            </Button>
                            <Button
                                v-if="hasOverrunLine"
                                as-child
                                variant="outline"
                                class="w-full border-amber-300 text-amber-800 hover:bg-amber-50"
                            >
                                <Link :href="`/feds/cg/${props.fed.id}/reclasser`">
                                    <ArrowRightLeft class="mr-2 size-4" />
                                    Reclasser le budget
                                </Link>
                            </Button>
                        </div>

                        <p
                            v-else
                            class="rounded-lg border border-border bg-muted/40 p-3 text-sm text-muted-foreground"
                        >
                            Cette FED n’est plus en attente de vérification CG.
                        </p>

                        <div class="flex flex-col gap-2 border-t border-border pt-4">
                            <ValidationHistoryModal :fed="props.fed" />
                            <Button
                                v-if="hasBudgetHistory"
                                type="button"
                                variant="outline"
                                class="w-full justify-start"
                                @click="showBudgetHistory = true"
                            >
                                <History class="mr-2 size-4" />
                                Historique budgétaire
                            </Button>
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        <!-- Modale vérification budgétaire -->
        <Dialog v-model:open="isVerificationModalOpen">
            <DialogContent class="max-h-[90vh] w-full max-w-xl overflow-y-auto sm:max-w-[600px]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Calculator class="size-5 text-amber-600" />
                        Vérification budgétaire
                    </DialogTitle>
                </DialogHeader>

                <div class="space-y-5 py-2">
                    <div
                        v-if="props.budgetLines.length > 0 && props.fed.items.length > 0"
                        class="overflow-hidden rounded-lg border border-border"
                    >
                        <div class="border-b border-border bg-muted/40 px-4 py-2.5">
                            <h3 class="text-xs font-semibold uppercase text-foreground">
                                Assignation des lignes par article
                            </h3>
                        </div>
                        <div class="divide-y divide-border">
                            <div
                                v-for="item in props.fed.items"
                                :key="item.id"
                                class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-foreground">{{ item.label }}</p>
                                    <p class="mt-0.5 text-xs text-muted-foreground">
                                        Qté : {{ formatQuantity(item.quantity) }} · Retenu :
                                        <span class="font-semibold text-foreground">
                                            {{ formatAmount(itemRetainedAmount(item)) }}
                                        </span>
                                    </p>
                                </div>
                                <div class="w-full sm:w-56">
                                    <select
                                        v-model="itemBudgetLines[item.id]"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    >
                                        <option :value="0" disabled>— Sélectionner une ligne —</option>
                                        <option
                                            v-for="line in props.budgetLines"
                                            :key="line.id"
                                            :value="line.id"
                                        >
                                            {{ line.code }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="(group, idx) in groupedItemsByLine"
                            :key="idx"
                            class="rounded-lg border border-border bg-muted/30 p-3"
                        >
                            <div class="mb-2 flex items-center justify-between border-b border-border pb-2">
                                <p class="text-xs font-bold uppercase text-foreground">
                                    {{ group.line.code }}
                                </p>
                                <span
                                    class="flex items-center gap-1 text-[10px] font-bold uppercase"
                                    :class="
                                        group.total > (group.line.montant_estime ?? 0)
                                            ? 'text-red-600'
                                            : 'text-green-600'
                                    "
                                >
                                    <XCircle
                                        v-if="group.total > (group.line.montant_estime ?? 0)"
                                        class="size-3"
                                    />
                                    <CheckCircle2 v-else class="size-3" />
                                    {{
                                        group.total > (group.line.montant_estime ?? 0)
                                            ? 'Insuffisant'
                                            : 'Disponible'
                                    }}
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <p class="text-[10px] font-semibold uppercase text-muted-foreground">
                                        Solde ligne
                                    </p>
                                    <p class="font-semibold tabular-nums">
                                        {{ formatAmount(group.line.montant_estime) }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] font-semibold uppercase text-muted-foreground">
                                        Consommé
                                    </p>
                                    <p class="font-semibold tabular-nums">
                                        {{ formatAmount(group.total) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="isOutOfBudget"
                            class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-900"
                        >
                            <p class="font-semibold">Budget insuffisant</p>
                            <p class="mt-1 text-red-800">
                                Une ligne est en dépassement. Préférez un reclassement avant de valider.
                            </p>
                        </div>
                        <div
                            v-else
                            class="rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-900"
                        >
                            <p class="font-semibold">Dans le budget</p>
                            <p class="mt-1 text-green-800">
                                Toutes les lignes sollicitées ont un solde suffisant.
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-foreground">
                            Commentaire
                            <span class="text-xs font-normal text-muted-foreground">(optionnel)</span>
                        </label>
                        <textarea
                            v-model="comment"
                            rows="3"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            placeholder="Dépassement, rallonge, précision…"
                        />
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border pt-4">
                        <Button type="button" variant="outline" @click="isVerificationModalOpen = false">
                            Annuler
                        </Button>
                        <Button
                            v-if="isOutOfBudget"
                            type="button"
                            variant="outline"
                            class="border-red-300 text-red-700 hover:bg-red-50"
                            @click="submitDecision(true)"
                        >
                            Valider tout de même (hors budget)
                        </Button>
                        <Button
                            v-else
                            type="button"
                            class="bg-slate-800 text-white hover:bg-slate-900"
                            @click="submitDecision(false)"
                        >
                            Confirmer la vérification
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Modale historique budgétaire -->
        <Dialog :open="showBudgetHistory" @update:open="showBudgetHistory = $event">
            <DialogContent class="max-h-[90vh] w-full max-w-4xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <History class="size-5" />
                        Historique des modifications budgétaires
                    </DialogTitle>
                </DialogHeader>
                <div class="overflow-x-auto py-2">
                    <table class="w-full text-sm">
                        <thead class="bg-muted text-[11px] uppercase text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Date</th>
                                <th class="px-3 py-2 text-left font-semibold">Auteur</th>
                                <th class="px-3 py-2 text-left font-semibold">Action</th>
                                <th class="px-3 py-2 text-left font-semibold">Source</th>
                                <th class="px-3 py-2 text-left font-semibold">Cible</th>
                                <th class="px-3 py-2 text-right font-semibold">Montant</th>
                                <th class="px-3 py-2 text-left font-semibold">Note</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="h in props.fed.budget_line_histories"
                                :key="h.id"
                            >
                                <td class="whitespace-nowrap px-3 py-2 text-muted-foreground">
                                    {{ formatDateTime(h.created_at) }}
                                </td>
                                <td class="px-3 py-2 font-medium">{{ h.user?.name || '—' }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="
                                            h.action === 'transfer_amount'
                                                ? 'bg-amber-100 text-amber-800'
                                                : 'bg-sky-100 text-sky-800'
                                        "
                                    >
                                        {{ actionLabel(h.action) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-xs">
                                    <template v-if="h.fromLine">
                                        {{ h.fromLine.code }}
                                        <span class="block text-muted-foreground">
                                            {{ formatAmount(h.from_montant_before) }} →
                                            {{ formatAmount(h.from_montant_after) }}
                                        </span>
                                    </template>
                                    <template v-else>—</template>
                                </td>
                                <td class="px-3 py-2 text-xs">
                                    <template v-if="h.toLine">
                                        {{ h.toLine.code }}
                                        <span class="block text-muted-foreground">
                                            {{ formatAmount(h.to_montant_before) }} →
                                            {{ formatAmount(h.to_montant_after) }}
                                        </span>
                                    </template>
                                    <template v-else>—</template>
                                </td>
                                <td class="px-3 py-2 text-right font-medium tabular-nums text-amber-800">
                                    {{ h.montant_transfere ? formatAmount(h.montant_transfere) : '—' }}
                                </td>
                                <td class="px-3 py-2 italic text-muted-foreground">
                                    {{ h.note || '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
