<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { computed, ref } from 'vue';
import ValidationHistoryModal from '@/components/ValidationHistoryModal.vue';
import {
    ArrowLeft,
    ArrowRightLeft,
    CheckCircle2,
    ChevronDown,
    FileSpreadsheet,
    FileText,
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
    n1_comment?: string | null;
    n1_action_at?: string | null;
    requester_signature?: string | null;
    n1_signature?: string | null;
    achats_comment?: string | null;
    achats_signature?: string | null;
    facilities_comment?: string | null;
    facilities_action_at?: string | null;
    facilities_signature?: string | null;
    daf_comment?: string | null;
    daf_action_at?: string | null;
    offre_choisie_id?: number | null;
    items: FedItem[];
    attachments: FedAttachment[];
    requester?: FedRequester | null;
    budget_line?: BudgetLine | null;
    budget_lines?: BudgetLine[];
    budget_line_histories?: {
        id: number;
        action: string;
        status: string;
        montant_transfere: number;
        from_line?: BudgetLine;
        to_line?: BudgetLine;
        note?: string;
        created_at: string;
    }[];
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
    montant?: number | null;
    prix_unitaire?: number | null;
    delais_livraison?: string | null;
    garanties_offertes?: string | null;
    conformite_reglementaire?: string | null;
    attachments?: OffreAttachment[];
}

interface Props {
    fed: Fed & { fournisseur_offres?: FedFournisseurOffre[] };
    dgaThreshold?: number;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Validations DAF', href: '/feds/daf' },
    { title: props.fed.code, href: '#' },
];

const formatAmount = (value?: number | null) => {
    if (value === null || value === undefined) return '—';
    const formattedNum = new Intl.NumberFormat('fr-FR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Number(value) || 0);
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
        cg_treated: 'En attente DAF',
        daf_approved: 'Validée DAF → DGA',
        daf_rejected: 'Rejetée DAF',
        dga_rejected: 'Rejetée DGA',
        waiting_daf_reclass_approval: 'Attente reclassement',
        bon_de_commande: 'Bon de commande',
    };
    return labels[status] ?? status;
};

const statusBadge = (status: string) => {
    const badges: Record<string, string> = {
        cg_treated: 'bg-cyan-100 text-cyan-800 border border-cyan-200',
        daf_approved: 'bg-green-100 text-green-800 border border-green-200',
        daf_rejected: 'bg-red-100 text-red-700 border border-red-200',
        dga_rejected: 'bg-red-100 text-red-700 border border-red-200',
        waiting_daf_reclass_approval: 'bg-amber-100 text-amber-800 border border-amber-200',
        bon_de_commande: 'bg-emerald-100 text-emerald-800 border border-emerald-200',
    };
    return badges[status] ?? 'bg-gray-100 text-gray-700 border border-gray-200';
};

const canValidate = computed(
    () => props.fed.status === 'facilities_approved' || props.fed.status === 'cg_treated',
);
const canValidateReclass = computed(() => props.fed.status === 'waiting_daf_reclass_approval');
const canAct = computed(() => canValidate.value || canValidateReclass.value);

const willGoToDga = computed(() => {
    const threshold = props.dgaThreshold ?? 0;
    const total = parseFloat(String(props.fed.estimated_total ?? 0));
    return threshold > 0 && total >= threshold;
});

const pendingReclassHistory = computed(() => {
    return props.fed.budget_line_histories?.find(
        (h) => h.status === 'pending' && h.action === 'transfer_amount',
    );
});

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
                attachments: o.attachments ?? [],
            };
        }
        groups[key].offres.push(o);
        const qty = getArticleQuantity(o.fed_item_id);
        groups[key].total += (o.prix_unitaire ?? 0) * qty;
    });

    return Object.values(groups);
});

const isSelectedOffer = (group: SupplierGroup) =>
    props.fed.offre_choisie_id === group.representative_id ||
    group.offres.some((o) => o.id === props.fed.offre_choisie_id);

const selectedOfferTotal = computed(() => {
    const selected = groupedOffres.value.find((g) => isSelectedOffer(g));
    return selected?.total ?? null;
});

const comment = ref(props.fed.daf_comment ?? '');
const showComparatif = ref(false);

const approve = () => {
    router.post(`/feds/daf/${props.fed.id}/approve`, { comment: comment.value }, { preserveScroll: true });
};

const reject = () => {
    if (confirm('Rejeter cette FED ?')) {
        router.post(`/feds/daf/${props.fed.id}/reject`, { comment: comment.value }, { preserveScroll: true });
    }
};

const approveReclass = () => {
    router.post(
        `/feds/daf/${props.fed.id}/approve-reclass`,
        { comment: comment.value },
        { preserveScroll: true },
    );
};

const rejectReclass = () => {
    if (!comment.value.trim()) {
        alert('Veuillez saisir un motif de rejet dans le champ Commentaire DAF.');
        return;
    }
    if (
        confirm(
            'Rejeter cette demande de transfert budgétaire ? La FED retournera au Contrôle de Gestion.',
        )
    ) {
        router.post(
            `/feds/daf/${props.fed.id}/reject-reclass`,
            { comment: comment.value },
            { preserveScroll: true },
        );
    }
};
</script>

<template>
    <Head :title="`Validation DAF - ${props.fed.code}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-0 flex-1 flex-col gap-4 p-4 lg:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
                <div class="flex flex-wrap items-center gap-3">
                    <Button as-child variant="outline" size="sm">
                        <Link href="/feds/daf" class="inline-flex items-center gap-1.5">
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

            <div class="grid min-h-0 flex-1 gap-4 lg:grid-cols-[minmax(0,1.45fr)_minmax(300px,0.7fr)]">
                <!-- Colonne gauche : fiche officielle -->
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

                <!-- Panneau droit : actions DAF -->
                <aside class="order-1 print:hidden lg:sticky lg:top-4 lg:order-2 lg:self-start">
                    <div class="flex flex-col gap-4 rounded-xl border border-border bg-card p-4 shadow-sm lg:p-5">
                        <div class="flex items-start gap-3 border-b border-border pb-4">
                            <div class="rounded-lg bg-slate-100 p-2 text-slate-700">
                                <FileText class="size-5" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Validation DAF
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
                            <div v-if="selectedOfferTotal !== null">
                                <dt class="text-muted-foreground">Offre retenue</dt>
                                <dd class="mt-0.5 font-semibold tabular-nums text-foreground">
                                    {{ formatAmount(selectedOfferTotal) }}
                                </dd>
                            </div>
                            <div v-if="canValidate && (props.dgaThreshold ?? 0) > 0">
                                <dt class="text-muted-foreground">Orientation</dt>
                                <dd class="mt-1">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="
                                            willGoToDga
                                                ? 'bg-amber-100 text-amber-800'
                                                : 'bg-green-100 text-green-800'
                                        "
                                    >
                                        {{ willGoToDga ? '→ Transmission au DGA' : '→ BOC direct' }}
                                    </span>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Seuil DGA :
                                        {{
                                            new Intl.NumberFormat('fr-FR', {
                                                style: 'currency',
                                                currency: 'XOF',
                                                maximumFractionDigits: 0,
                                            }).format(props.dgaThreshold ?? 0)
                                        }}
                                    </p>
                                </dd>
                            </div>
                        </dl>

                        <!-- Bloc reclassement -->
                        <div
                            v-if="canValidateReclass && pendingReclassHistory"
                            class="rounded-lg border border-amber-300 bg-amber-50 p-3"
                        >
                            <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold text-amber-900">
                                <ArrowRightLeft class="size-4" />
                                Transfert budgétaire
                            </h3>
                            <p class="mb-3 text-xs text-amber-800">
                                Le Contrôle de Gestion demande un transfert avant de poursuivre.
                            </p>
                            <div class="space-y-2 text-sm">
                                <div>
                                    <p class="text-[10px] font-semibold uppercase text-amber-700">Source</p>
                                    <p class="font-medium text-foreground">
                                        {{ pendingReclassHistory.from_line?.code || '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold uppercase text-amber-700">Cible</p>
                                    <p class="font-medium text-foreground">
                                        {{ pendingReclassHistory.to_line?.code || '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold uppercase text-amber-700">Montant</p>
                                    <p class="text-lg font-bold tabular-nums text-amber-800">
                                        {{ formatAmount(pendingReclassHistory.montant_transfere) }}
                                    </p>
                                </div>
                                <div v-if="pendingReclassHistory.note">
                                    <p class="text-[10px] font-semibold uppercase text-amber-700">Motif CG</p>
                                    <p class="text-xs italic text-foreground">
                                        « {{ pendingReclassHistory.note }} »
                                    </p>
                                </div>
                            </div>
                        </div>

                        <Button
                            v-if="props.fed.fournisseur_offres?.length"
                            type="button"
                            variant="outline"
                            class="w-full"
                            @click="showComparatif = true"
                        >
                            <FileSpreadsheet class="mr-2 size-4" />
                            Voir le tableau comparatif
                        </Button>

                        <div class="space-y-3 border-t border-border pt-4">
                            <h3 class="text-sm font-semibold text-foreground">Votre décision</h3>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-foreground">
                                    Commentaire DAF
                                    <span class="text-xs font-normal text-muted-foreground">
                                        {{ canValidateReclass ? '(obligatoire si rejet)' : '(optionnel)' }}
                                    </span>
                                </label>
                                <textarea
                                    v-model="comment"
                                    rows="4"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:bg-muted/50"
                                    :readonly="!canAct"
                                    placeholder="Ajouter un commentaire…"
                                />
                                <p v-if="props.fed.daf_action_at" class="mt-1 text-xs text-muted-foreground">
                                    Traité le
                                    {{ new Date(props.fed.daf_action_at).toLocaleString('fr-FR') }}
                                </p>
                            </div>

                            <div v-if="canValidateReclass" class="flex flex-col gap-2">
                                <Button
                                    type="button"
                                    class="w-full bg-amber-600 text-white hover:bg-amber-700"
                                    @click="approveReclass"
                                >
                                    <CheckCircle2 class="mr-2 size-4" />
                                    Approuver le transfert
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="w-full border-red-200 text-red-700 hover:bg-red-50"
                                    @click="rejectReclass"
                                >
                                    <XCircle class="mr-2 size-4" />
                                    Rejeter le transfert
                                </Button>
                            </div>

                            <div v-else-if="canValidate" class="flex flex-col gap-2">
                                <Button
                                    v-if="willGoToDga"
                                    type="button"
                                    class="w-full bg-amber-600 text-white hover:bg-amber-700"
                                    @click="approve"
                                >
                                    <CheckCircle2 class="mr-2 size-4" />
                                    Valider et transmettre au DGA
                                </Button>
                                <Button
                                    v-else
                                    type="button"
                                    class="w-full bg-green-600 text-white hover:bg-green-700"
                                    @click="approve"
                                >
                                    <CheckCircle2 class="mr-2 size-4" />
                                    Valider et générer le BOC
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="w-full border-red-200 text-red-700 hover:bg-red-50"
                                    @click="reject"
                                >
                                    <XCircle class="mr-2 size-4" />
                                    Rejeter
                                </Button>
                            </div>

                            <p
                                v-else
                                class="rounded-lg border border-border bg-muted/40 p-3 text-sm text-muted-foreground"
                            >
                                Cette FED n’est plus en attente de validation DAF. Consultation en lecture seule.
                            </p>
                        </div>

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
                        Tableau comparatif – Offre retenue
                    </DialogTitle>
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
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 px-4 py-3 text-left"
                            :class="
                                isSelectedOffer(group)
                                    ? 'border-b border-green-100 bg-green-50'
                                    : 'border-b border-border bg-muted/30'
                            "
                            @click="toggleDetails(group.id)"
                        >
                            <span
                                v-if="isSelectedOffer(group)"
                                class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-green-600 text-xs font-bold text-white"
                            >
                                ✓
                            </span>
                            <span v-else class="size-6 shrink-0" />
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-base font-bold uppercase text-foreground">
                                            {{ group.fournisseur }}
                                        </h3>
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
                            </div>
                        </button>

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
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
