<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { computed, ref } from 'vue';
import ValidationHistoryModal from '@/components/ValidationHistoryModal.vue';

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
        facilities_approved: 'bg-blue-50 text-blue-700 border border-blue-200',
        cg_treated: 'bg-indigo-50 text-indigo-700 border border-indigo-200',
        bon_de_commande: 'bg-cyan-100 text-cyan-800 border border-cyan-200',
    };
    return m[s] ?? 'bg-gray-100 text-gray-700 border border-gray-200';
};

const priorityLabel = (p?: string | null) => ({ low: 'Faible', normal: 'Normal', high: 'Haute', urgent: 'Urgente' }[p ?? ''] ?? '—');

const canAct = computed(() => ['n1_approved', 'achats_needs_info'].includes(props.fed.status));

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
        <div class="mx-auto max-w-4xl p-6">

            <!-- Barre supérieure -->
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Link href="/feds/achats">
                        <button class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            ← Retour à la liste
                        </button>
                    </Link>
                    <span :class="['inline-flex rounded-full px-3 py-1 text-sm font-medium', statusBadge(props.fed.status)]">
                        {{ statusLabel(props.fed.status) }}
                    </span>
                    <span v-if="props.fed.priority" class="inline-flex rounded-full px-3 py-1 text-sm font-medium bg-gray-100 text-gray-700">
                        {{ priorityLabel(props.fed.priority) }}
                    </span>
                </div>
                <!-- Edition (avant transmission) -->
                <Link
                    v-if="canAct"
                    :href="`/feds/achats/${props.fed.id}/cotation`"
                    class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
                >
                    📊 Saisir le tableau comparatif
                </Link>
                <!-- Lecture seule (après transmission, pendant ou après avis expert) -->
                <Link
                    v-else-if="['achats_approved', 'achats_rejected', 'expert_opinion_pending', 'expert_opinion_given'].includes(props.fed.status)"
                    :href="`/feds/achats/${props.fed.id}/cotation`"
                    class="inline-flex items-center gap-2 rounded-md border border-blue-300 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100 transition-colors"
                >
                    👁 Voir le tableau comparatif
                </Link>
            </div>



            <!-- Zone d'action (en haut) -->
            <div v-if="canAct" class="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-5">
                <h3 class="mb-4 text-base font-semibold text-gray-800">Votre décision</h3>
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        Message au demandeur
                        <span class="ml-1 text-xs text-gray-400">(requis pour un complément ou un rejet)</span>
                    </label>
                    <textarea
                        v-model="comment"
                        rows="2"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
                        placeholder="Votre message au demandeur..."
                    />
                </div>
                <div class="flex flex-wrap justify-end gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md border border-orange-200 bg-white px-4 py-2 text-sm font-medium text-orange-600 hover:bg-orange-50 transition-colors"
                        @click="needsInfo"
                    >
                        🔁 Demander un complément
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 transition-colors"
                        @click="reject"
                    >
                        ❌ Rejeter
                    </button>
                </div>
            </div>

            <!-- Historique des validations -->
            <div class="mb-4">
                <ValidationHistoryModal :fed="props.fed" />
            </div>

            <!-- Document -->
            <div class="rounded-lg border-2 border-gray-900 bg-white p-6 shadow-sm">

                <!-- En-tête -->
                <div class="mb-8 flex items-start justify-between border-b border-gray-300 pb-4">
                    <img src="/logo_Cofina.png" alt="Cofina" class="h-14 object-contain" />
                    <div class="text-right">
                        <h1 class="text-xl font-bold uppercase text-gray-900">Fiche d'Engagement de dépense</h1>
                        <p class="mt-1 text-sm font-medium">
                            Réf. : FED n°
                            <span class="inline-block min-w-[120px] border-b border-gray-400 font-semibold">{{ props.fed.code }}</span>
                        </p>
                    </div>
                </div>

                <!-- Infos demande -->
                <div class="mb-6 border-2 border-gray-900 p-4">
                    <h2 class="mb-4 text-base font-bold uppercase">Demande</h2>
                    <div class="grid gap-3 text-sm">
                        <div class="grid grid-cols-[160px_1fr] gap-2">
                            <span class="font-medium text-gray-600">Date :</span>
                            <span>{{ formatDate(props.fed.date) }}</span>
                        </div>
                        <div class="grid grid-cols-[160px_1fr] gap-2">
                            <span class="font-medium text-gray-600">Demandeur :</span>
                            <span class="font-semibold uppercase">{{ props.fed.demandeur || props.fed.requester?.name || '—' }}</span>
                        </div>
                        <div class="grid grid-cols-[160px_1fr] gap-2">
                            <span class="font-medium text-gray-600">Fonction :</span>
                            <span class="uppercase">{{ props.fed.fonction || '—' }}</span>
                        </div>
                        <div class="grid grid-cols-[160px_1fr] gap-2">
                            <span class="font-medium text-gray-600">Département :</span>
                            <span>{{ props.fed.department || '—' }}</span>
                        </div>
                        <div class="grid grid-cols-[160px_1fr] gap-2">
                            <span class="font-medium text-gray-600">Motif :</span>
                            <span class="font-medium uppercase">{{ props.fed.motive || '—' }}</span>
                        </div>
                        <div class="grid grid-cols-[160px_1fr] gap-2">
                            <span class="font-medium text-gray-600">Bénéficiaire(s) :</span>
                            <span>{{ props.fed.beneficiaire || '—' }}</span>
                        </div>

                        <!-- Signatures -->
                        <div class="mt-6 grid grid-cols-2 gap-8 border-t border-gray-300 pt-6">
                            <div class="flex flex-col items-center">
                                <div class="mb-2 flex h-20 w-full max-w-[200px] items-center justify-center border-2 border-gray-500 bg-gray-50 p-2">
                                    <img v-if="props.fed.requester_signature" :src="props.fed.requester_signature" alt="Signature demandeur" class="max-h-full max-w-full object-contain" />
                                </div>
                                <span class="text-xs font-medium">Signature demandeur</span>
                            </div>
                            <div class="flex flex-col items-center">
                                <div class="mb-2 flex h-20 w-full max-w-[200px] items-center justify-center border-2 border-gray-500 bg-gray-50 p-2">
                                    <img v-if="props.fed.n1_signature" :src="props.fed.n1_signature" alt="Signature Manager" class="max-h-full max-w-full object-contain" />
                                </div>
                                <span class="text-xs font-medium">Nom & Signature Manager</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Articles -->
                <div v-if="props.fed.items?.length" class="mb-6 border border-gray-400 p-4">
                    <h2 class="mb-3 text-sm font-bold uppercase">Articles / Services</h2>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-400 bg-gray-100 uppercase text-[11px] text-gray-700">
                                <th class="px-2 py-2 text-left font-bold">Ligne(s) Budgétaire(s)</th>
                                <th class="px-2 py-2 text-left font-bold">Intitulé</th>
                                <th class="px-2 py-2 text-center font-bold">Quantité</th>
                                <th class="px-2 py-2 text-center font-bold">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in props.fed.items" :key="item.id" class="border-b border-gray-200">
                                <td class="px-2 py-2 font-medium text-red-700 uppercase">{{ item.budget_line?.code || '—' }}</td>
                                <td class="px-2 py-2 uppercase">{{ item.label }}</td>
                                <td class="px-2 py-2 text-center">{{item.quantity}}</td>
                                <td class="px-2 py-2 text-xs text-center text-gray-600 italic">{{ item.description || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pièces jointes -->
                <div v-if="props.fed.attachments?.length" class="mb-6 border border-gray-400 p-4">
                    <h2 class="mb-3 text-sm font-bold uppercase">Pièces jointes</h2>
                    <div class="space-y-1 text-sm">
                        <a
                            v-for="att in props.fed.attachments"
                            :key="att.id"
                            :href="`/storage/${att.path}`"
                            target="_blank"
                            class="block text-blue-600 hover:underline"
                        >
                            📎 {{ att.original_name }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
