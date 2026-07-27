<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { computed, ref } from 'vue';
import ValidationHistoryModal from '@/components/ValidationHistoryModal.vue';
import {
    ArrowLeft,
    BarChart3,
    Eye,
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

interface FedRequester { name: string; }

interface BudgetLine { code?: string | null; label?: string | null; }

interface Fed {
    id: number;
    code: string;
    date?: string | null;
    demandeur?: string | null;
    department?: string | null;
    fonction?: string | null;
    beneficiaire?: string | null;
    motive?: string | null;
    priority?: string | null;
    status: string;
    submitted_at?: string | null;
    n1_avis?: string | null;
    n1_comment?: string | null;
    n1_action_at?: string | null;
    achats_comment?: string | null;
    achats_action_at?: string | null;
    facilities_comment?: string | null;
    facilities_action_at?: string | null;
    requester_signature?: string | null;
    n1_signature?: string | null;
    items: FedItem[];
    attachments: FedAttachment[];
    requester?: FedRequester | null;
    budget_line?: BudgetLine | null;
    budget_lines?: BudgetLine[];
    expert_opinion_offre_id?: number | null;
    expert_opinion_comment?: string | null;
    expert_opinion_at?: string | null;
}

interface Props { fed: Fed; }

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Demandes en cours', href: '/feds/achats' },
    { title: props.fed.code, href: '#' },
];

const formatDate = (v?: string | null) => v ? new Date(v).toLocaleDateString('fr-FR') : '—';

const formatBudgetLines = (fed: Fed) => {
    const uniqueCodes = [...new Set(fed.items?.filter(item => item.budget_line?.code).map(item => item.budget_line!.code))];
    if (uniqueCodes.length > 0) {
        return uniqueCodes.join(' ; ');
    }
    return '—';
};

const statusLabel = (s: string) => {
    const m: Record<string, string> = {
        pending_validation: 'En attente N+1',
        n1_needs_info: 'Complément demandé (N+1)',
        n1_approved: 'En attente Achats',
        achats_needs_info: 'Complément demandé',
        achats_rejected: 'Rejetée',
        achats_approved: 'Transmise Facilities',
        expert_opinion_pending: 'En attente avis expert',
        expert_opinion_given: 'Avis expert reçu',
        facilities_needs_info: 'Complément demandé (Facilities)',
        facilities_rejected: 'Rejetée (Facilities)',
        facilities_approved: 'En attente Budget (CG)',
        cg_treated: 'En attente DAF/DGA',
        daf_approved: 'DGA : Approuvée',
        bon_de_commande: 'Bon de Commande',
    };
    return m[s] ?? s;
};

const statusBadge = (s: string) => {
    const m: Record<string, string> = {
        pending_validation: 'bg-yellow-50 text-yellow-700 border border-yellow-200',
        n1_approved: 'bg-blue-100 text-blue-700 border border-blue-200',
        achats_needs_info: 'bg-orange-100 text-orange-700 border border-orange-200',
        achats_rejected: 'bg-red-100 text-red-700 border border-red-200',
        achats_approved: 'bg-green-100 text-green-700 border border-green-200',
        expert_opinion_pending: 'bg-purple-100 text-purple-700 border border-purple-200',
        expert_opinion_given: 'bg-green-100 text-green-700 border border-green-200 ring-1 ring-green-100',
        facilities_needs_info: 'bg-orange-100 text-orange-700 border border-orange-200',
        facilities_rejected: 'bg-red-100 text-red-700 border border-red-200',
        facilities_approved: 'bg-blue-50 text-blue-700 border border-blue-200',
        cg_treated: 'bg-indigo-50 text-indigo-700 border border-indigo-200',
        bon_de_commande: 'bg-cyan-100 text-cyan-800 border border-cyan-200',
    };
    return m[s] ?? 'bg-gray-100 text-gray-700 border border-gray-200';
};

const priorityLabel = (p?: string | null) => ({ low: 'Faible', normal: 'Normal', high: 'Haute', urgent: 'Urgente' }[p ?? ''] ?? '—');

/** Décisions Achats envers le demandeur (rejet / complément) */
const canAct = computed(() => ['n1_approved', 'achats_needs_info'].includes(props.fed.status));
/** Complément demandé par Facilities → Achats doit mettre à jour la cotation */
const canRespondFacilities = computed(() => props.fed.status === 'facilities_needs_info');
/** Accès édition / saisie du tableau comparatif */
const canEditCotation = computed(() =>
    ['n1_approved', 'achats_needs_info', 'facilities_needs_info'].includes(props.fed.status),
);
const canViewCotation = computed(() =>
    [
        'achats_approved',
        'achats_rejected',
        'expert_opinion_pending',
        'expert_opinion_given',
        'facilities_approved',
        'facilities_rejected',
    ].includes(props.fed.status),
);

const comment = ref('');

const reject = () => {
    if (!comment.value.trim()) {
        alert('Veuillez saisir un commentaire avant de rejeter.');
        return;
    }
    if (confirm('Confirmer le rejet de cette demande ?')) {
        router.post(`/feds/achats/${props.fed.id}/reject`, { comment: comment.value }, { preserveScroll: true });
    }
};

const needsInfo = () => {
    if (!comment.value.trim()) {
        alert('Veuillez saisir un message pour le demandeur.');
        return;
    }
    if (confirm('Renvoyer la demande au demandeur pour complément ?')) {
        router.post(`/feds/achats/${props.fed.id}/needs-info`, { comment: comment.value }, { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="`FED ${props.fed.code} – Achats`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-0 flex-1 flex-col gap-4 p-4 lg:p-6">
            <!-- Barre supérieure -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    <Link href="/feds/achats">
                        <Button variant="outline" size="sm">
                            <ArrowLeft class="mr-1.5 size-4" />
                            Retour à la liste
                        </Button>
                    </Link>
                    <span :class="['inline-flex rounded-full px-3 py-1 text-sm font-medium', statusBadge(props.fed.status)]">
                        {{ statusLabel(props.fed.status) }}
                    </span>
                    <span
                        v-if="props.fed.priority"
                        class="inline-flex rounded-full border border-gray-200 bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700"
                    >
                        {{ priorityLabel(props.fed.priority) }}
                    </span>
                </div>

            </div>

            <!-- Layout 2 colonnes : fiche | actions (dès lg) -->
            <div class="grid min-h-0 flex-1 gap-4 lg:grid-cols-[minmax(0,1.45fr)_minmax(300px,0.7fr)]">
                <!-- Gauche : document -->
                <div class="order-2 min-h-0 overflow-y-auto rounded-xl border border-border bg-muted/30 p-3 lg:order-1 lg:p-4">
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
                                    v-if="formatBudgetLines(props.fed) !== '—'"
                                    class="grid grid-cols-[140px_1fr] gap-2 rounded border-l-4 border-red-500 bg-red-50/80 px-2 py-1.5 sm:grid-cols-[170px_1fr]"
                                >
                                    <span class="font-medium text-red-800">Ligne(s) budgétaire(s) :</span>
                                    <span class="font-medium uppercase text-red-900">{{ formatBudgetLines(props.fed) }}</span>
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
                                        <div class="mb-2 flex h-20 w-full max-w-[200px] items-center justify-center border-2 border-gray-500 bg-gray-50 p-2">
                                            <img
                                                v-if="props.fed.requester_signature"
                                                :src="props.fed.requester_signature"
                                                alt="Signature demandeur"
                                                class="max-h-full max-w-full object-contain"
                                            />
                                        </div>
                                        <span class="text-xs font-medium">Signature demandeur</span>
                                    </div>
                                    <div class="flex flex-col items-center">
                                        <div class="mb-2 flex h-20 w-full max-w-[200px] items-center justify-center border-2 border-gray-500 bg-gray-50 p-2">
                                            <img
                                                v-if="props.fed.n1_signature"
                                                :src="props.fed.n1_signature"
                                                alt="Signature Manager"
                                                class="max-h-full max-w-full object-contain"
                                            />
                                        </div>
                                        <span class="text-xs font-medium">Nom & Signature Manager</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="props.fed.items?.length" class="mb-6 border border-gray-400 p-4">
                            <h2 class="mb-3 text-sm font-bold uppercase">Articles / Services</h2>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-400 bg-gray-100 text-[11px] uppercase text-gray-700">
                                            <th class="px-2 py-2 text-left font-bold">Ligne(s) Budgétaire(s)</th>
                                            <th class="px-2 py-2 text-left font-bold">Intitulé</th>
                                            <th class="px-2 py-2 text-center font-bold">Quantité</th>
                                            <th class="px-2 py-2 text-center font-bold">Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="item in props.fed.items" :key="item.id" class="border-b border-gray-200">
                                            <td class="px-2 py-2 font-medium uppercase text-red-700">
                                                {{ item.budget_line?.code || '—' }}
                                            </td>
                                            <td class="px-2 py-2 uppercase">{{ item.label }}</td>
                                            <td class="px-2 py-2 text-center">{{ item.quantity }}</td>
                                            <td class="px-2 py-2 text-center text-xs italic text-gray-600">
                                                {{ item.description || '—' }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div v-if="props.fed.attachments?.length" class="border border-gray-400 p-4">
                            <h2 class="mb-3 flex items-center gap-2 text-sm font-bold uppercase">
                                <Paperclip class="size-4" />
                                Pièces jointes
                            </h2>
                            <div class="space-y-1 text-sm">
                                <a
                                    v-for="att in props.fed.attachments"
                                    :key="att.id"
                                    :href="`/storage/${att.path}`"
                                    target="_blank"
                                    class="block text-blue-600 hover:underline"
                                >
                                    {{ att.original_name }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Droite : actions -->
                <aside class="order-1 lg:sticky lg:top-4 lg:order-2 lg:self-start">
                    <div class="flex flex-col gap-4 rounded-xl border border-border bg-card p-4 shadow-sm lg:p-5">
                        <div class="flex items-start gap-3 border-b border-border pb-4">
                            <div class="rounded-lg bg-slate-100 p-2 text-slate-700">
                                <FileText class="size-5" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Achats & consultations
                                </p>
                                <h2 class="truncate text-base font-semibold text-foreground">{{ props.fed.code }}</h2>
                                <p class="mt-0.5 text-sm text-muted-foreground">
                                    {{ props.fed.demandeur || props.fed.requester?.name || 'Demandeur' }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="canRespondFacilities && props.fed.facilities_comment"
                            class="rounded-lg border border-orange-300 bg-orange-50 p-3"
                        >
                            <h3 class="mb-1 text-sm font-semibold text-orange-900">
                                Demande de Facilities
                            </h3>
                            <p class="whitespace-pre-line text-sm text-orange-800">
                                {{ props.fed.facilities_comment }}
                            </p>
                        </div>

                        <Link
                            v-if="canEditCotation"
                            :href="`/feds/achats/${props.fed.id}/cotation`"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-blue-700"
                        >
                            <BarChart3 class="size-4" />
                            {{
                                canRespondFacilities
                                    ? 'Compléter / modifier le tableau comparatif'
                                    : 'Saisir le tableau comparatif'
                            }}
                        </Link>
                        <Link
                            v-else-if="canViewCotation"
                            :href="`/feds/achats/${props.fed.id}/cotation`"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-blue-300 bg-blue-50 px-4 py-2.5 text-sm font-medium text-blue-700 transition-colors hover:bg-blue-100"
                        >
                            <Eye class="size-4" />
                            Voir le tableau comparatif
                        </Link>

                        <div v-if="canAct" class="space-y-3">
                            <h3 class="text-sm font-semibold text-foreground">Votre décision</h3>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-foreground">
                                    Message au demandeur
                                    <span class="ml-1 text-xs font-normal text-muted-foreground">
                                        (requis pour complément ou rejet)
                                    </span>
                                </label>
                                <textarea
                                    v-model="comment"
                                    rows="4"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    placeholder="Votre message au demandeur…"
                                />
                            </div>
                            <div class="flex flex-col gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="w-full justify-start border-orange-200 text-orange-700 hover:bg-orange-50"
                                    @click="needsInfo"
                                >
                                    <MessageSquareWarning class="mr-2 size-4" />
                                    Demander un complément
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="w-full justify-start border-red-200 text-red-700 hover:bg-red-50"
                                    @click="reject"
                                >
                                    <XCircle class="mr-2 size-4" />
                                    Rejeter
                                </Button>
                            </div>
                        </div>

                        <div
                            v-else-if="canRespondFacilities"
                            class="rounded-lg border border-border bg-muted/40 p-4 text-sm text-muted-foreground"
                        >
                            Modifiez le tableau comparatif puis renvoyez-le à Facilities.
                        </div>

                        <div v-else class="rounded-lg border border-border bg-muted/40 p-4">
                            <h3 class="mb-2 text-sm font-semibold text-foreground">Statut</h3>
                            <span :class="['inline-flex rounded-full px-3 py-1 text-sm font-medium', statusBadge(props.fed.status)]">
                                {{ statusLabel(props.fed.status) }}
                            </span>
                            <p v-if="props.fed.achats_comment" class="mt-3 whitespace-pre-line text-sm text-muted-foreground">
                                {{ props.fed.achats_comment }}
                            </p>
                        </div>

                        <div class="border-t border-border pt-4">
                            <ValidationHistoryModal :fed="props.fed" />
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
