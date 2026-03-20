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
import SignatureInput from '@/components/SignatureInput.vue';

interface FedItem {
    id: number;
    label: string;
    quantity: number | null;
    description?: string | null;
    budget_line?: BudgetLine | null;
}

interface FedAttachment {
    id: number;
    original_name: string;
    path: string;
}

interface FedRequester {
    name: string;
    email: string;
}

interface BudgetLine {
    code?: string | null;
    label?: string | null;
}

interface OffreAttachment {
    id: number;
    original_name: string;
    path: string;
}

interface FedFournisseurOffre {
    id: number;
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
    priority?: string | null;
    status: string;
    submitted_at?: string | null;
    n1_comment?: string | null;
    n1_avis?: string | null;
    n1_action_at?: string | null;
    requester_signature?: string | null;
    n1_signature?: string | null;
    offre_choisie_id?: number | null;
    expert_opinion_requested?: boolean;
    expert_opinion_comment?: string | null;
    expert_opinion_offre_id?: number | null;
    expert_opinion_at?: string | null;
    facilities_comment?: string | null;
    facilities_action_at?: string | null;
    items: FedItem[];
    attachments: FedAttachment[];
    requester?: FedRequester | null;
    budget_line?: BudgetLine | null;
    budget_lines?: BudgetLine[];
    fournisseur_offres?: FedFournisseurOffre[];
}

interface Props {
    fed: Fed;
    authSignature?: string | null;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Validations N+1', href: '/feds/n1' },
    { title: props.fed.code, href: '#' },
];

const formatDate = (value?: string | null) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const formatBudgetLines = (fed: Fed) => {
    // On récupère toutes les lignes budgétaires uniques des articles
    const uniqueCodes = [...new Set(fed.items?.filter(item => item.budget_line?.code).map(item => item.budget_line!.code))];
    if (uniqueCodes.length > 0) {
        return uniqueCodes.join(' ; ');
    }
    return '—';
};

const statusLabel = (status: string) => {
    switch (status) {
        case 'pending_validation': return 'En attente de validation';
        case 'n1_needs_info': return 'Complément demandé';
        case 'n1_rejected': return 'Rejetée';
        case 'n1_approved': return 'Validée';
        case 'expert_opinion_pending': return 'Avis Expert Métier sollicité';
        default: return status;
    }
};

const statusBadge = (status: string) => {
    switch (status) {
        case 'pending_validation': return 'bg-blue-100 text-blue-700 border border-blue-200';
        case 'n1_needs_info': return 'bg-orange-100 text-orange-700 border border-orange-200';
        case 'n1_rejected': return 'bg-red-100 text-red-700 border border-red-200';
        case 'n1_approved': return 'bg-green-100 text-green-700 border border-green-200';
        case 'expert_opinion_pending': return 'bg-purple-100 text-purple-700 border border-purple-200';
        default: return 'bg-gray-100 text-gray-700 border border-gray-200';
    }
};

const priorityLabel = (priority?: string | null) => {
    switch (priority) {
        case 'low': return 'Faible';
        case 'normal': return 'Normal';
        case 'high': return 'Haute';
        case 'urgent': return 'Urgente';
        default: return '—';
    }
};

const priorityBadge = (priority?: string | null) => {
    switch (priority) {
        case 'urgent': return 'bg-red-100 text-red-700 border border-red-200';
        case 'high': return 'bg-orange-100 text-orange-700 border border-orange-200';
        case 'normal': return 'bg-blue-50 text-blue-600 border border-blue-200';
        default: return 'bg-gray-100 text-gray-600 border border-gray-200';
    }
};

const canValidate = computed(() => props.fed.status === 'pending_validation');
const isExpertOpinionMode = computed(() => props.fed.status === 'expert_opinion_pending');

const comment = ref(props.fed.n1_comment ?? '');
const avis = ref('');
const expertOpinionComment = ref(props.fed.expert_opinion_comment ?? '');
const expertOpinionOffreId = ref<number | null>(props.fed.expert_opinion_offre_id ?? null);
const expandedGroups = ref<Record<number, boolean>>({});

const toggleDetails = (id: number) => {
    expandedGroups.value[id] = !expandedGroups.value[id];
};

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

const getArticleQuantity = (itemId?: number | null) => {
    if (!itemId) return 1;
    const item = props.fed.items?.find(i => i.id == itemId);
    return item ? (item.quantity ?? 1) : 1;
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

    existing.forEach(o => {
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
                acompte: o.acompte_requis === 'OUI' ? `${o.pourcentage_acompte ?? 0}%` : (o.acompte_requis || '—'),
                attachments: o.attachments ?? []
            };
        }
        groups[key].offres.push(o);
        const qty = getArticleQuantity(o.fed_item_id);
        groups[key].total += (o.prix_unitaire ?? 0) * qty;
    });

    return Object.values(groups);
});

const submitExpertOpinion = () => {
    if (!expertOpinionComment.value.trim()) {
        alert('Votre avis expert est obligatoire.');
        return;
    }
    router.post(`/feds/n1/${props.fed.id}/expert-opinion`, {
        expert_opinion_comment: expertOpinionComment.value,
        expert_opinion_offre_id: expertOpinionOffreId.value
    }, { preserveScroll: true });
};
const showSignatureModal = ref(false);
const signatureInputRef = ref<InstanceType<typeof SignatureInput> | null>(null);
const pendingAction = ref<'approve' | 'reject' | 'needsInfo' | null>(null);

const openActionModal = (action: 'approve' | 'reject' | 'needsInfo') => {
    pendingAction.value = action;
    showSignatureModal.value = true;
};

const approve = () => {
    const signature = signatureInputRef.value?.getSignature();
    if (!signature) {
        alert('Veuillez signer pour valider.');
        return;
    }

    showSignatureModal.value = false;
    router.post(
        `/feds/n1/${props.fed.id}/approve`,
        { comment: comment.value, avis: avis.value, n1_avis: avis.value, n1_signature: signature },
        { preserveScroll: true }
    );
};

const submitDecision = () => {
    if (pendingAction.value === 'approve') {
        approve();
        return;
    }

    const c = comment.value?.trim();
    if (!c) {
        alert('Le commentaire est obligatoire pour cette action.');
        return;
    }

    if (pendingAction.value === 'reject') {
        if (!confirm('Confirmer le rejet ?')) return;
        showSignatureModal.value = false;
        router.post(`/feds/n1/${props.fed.id}/reject`, { comment: c }, { preserveScroll: true });
    } else if (pendingAction.value === 'needsInfo') {
        if (!confirm('Confirmer la demande de complément ?')) return;
        showSignatureModal.value = false;
        router.post(`/feds/n1/${props.fed.id}/needs-info`, { comment: c }, { preserveScroll: true });
    }
};

// Les fonctions reject() et needsInfo() ne sont plus utilisées directement par les boutons mais via openActionModal

</script>

<template>
    <Head :title="`Validation - ${props.fed.code}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl p-6">

            <!-- Barre d'actions supérieure -->
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Link href="/feds/n1">
                        <Button variant="outline" size="sm">← Retour à la liste</Button>
                    </Link>
                    <span :class="['inline-flex rounded-full px-3 py-1 text-sm font-medium', statusBadge(props.fed.status)]">
                        {{ statusLabel(props.fed.status) }}
                    </span>
                    <span v-if="props.fed.priority" :class="['inline-flex rounded-full px-3 py-1 text-sm font-medium', priorityBadge(props.fed.priority)]">
                        {{ priorityLabel(props.fed.priority) }}
                    </span>
                </div>
                <div v-if="props.fed.submitted_at" class="text-xs text-gray-500">
                    Soumis le {{ formatDate(props.fed.submitted_at) }}
                </div>
            </div>

            <!-- Zone d'action N+1 (en haut pour un accès rapide) -->
            <div v-if="canValidate" class="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-5">
                <h3 class="mb-4 text-base font-semibold text-gray-800">Votre décision</h3>

                <!-- Boutons -->
                <div class="flex flex-wrap justify-end gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md border border-orange-200 bg-white px-4 py-2 text-sm font-medium text-orange-600 hover:bg-orange-50 transition-colors"
                        @click="openActionModal('needsInfo')"
                    >
                         Demander un complément
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 transition-colors"
                        @click="openActionModal('reject')"
                    >
                         Rejeter
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 transition-colors"
                        @click="openActionModal('approve')"
                    >
                         Valider
                    </button>
                </div>
            </div>

            <!-- Zone d'action Avis Expert (N+1) -->
            <div v-if="isExpertOpinionMode" class="mb-6 rounded-lg border-2 border-purple-200 bg-purple-50/30 p-6 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-bold text-purple-900 uppercase flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></svg>
                        Avis Expert Métier sollicité par Facilities
                    </h3>
                </div>

                <div class="mb-6 rounded-lg bg-white p-4 border border-purple-100 shadow-sm">
                    <p class="text-xs font-bold text-purple-700 uppercase mb-2">Commentaire de Facilities :</p>
                    <p class="text-sm text-gray-700 italic">"{{ props.fed.facilities_comment || 'Aucun commentaire' }}"</p>
                </div>

                <div class="mb-6">
                    <h4 class="text-sm font-bold text-gray-800 mb-4 uppercase">Comparez les offres et donnez votre avis expert :</h4>
                    <div class="space-y-4">
                        <div 
                            v-for="(group, gIdx) in groupedOffres" 
                            :key="gIdx" 
                            class="overflow-hidden rounded-xl border-2 transition-all"
                            :class="[
                                expertOpinionOffreId === group.representative_id 
                                    ? 'border-purple-500 bg-purple-50/20 ring-4 ring-purple-50' 
                                    : 'border-gray-200 bg-white hover:border-purple-300'
                            ]"
                        >
                            <div class="flex items-center gap-4 px-4 py-3 cursor-pointer select-none" @click="toggleDetails(group.id)">
                                <div class="flex-shrink-0">
                                    <input
                                        v-model="expertOpinionOffreId"
                                        type="radio"
                                        :name="`expert-offre-${props.fed.id}`"
                                        :value="group.representative_id"
                                        class="h-5 w-5 text-purple-600 focus:ring-purple-500 cursor-pointer"
                                    />
                                </div>
                                <div class="flex-grow">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="text-lg font-bold text-gray-900 uppercase">{{ group.fournisseur }}</span>
                                            <span v-if="props.fed.offre_choisie_id === group.representative_id" class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-[10px] font-bold uppercase ring-1 ring-blue-200">
                                                Choix Facilities
                                            </span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-sm font-black text-gray-900">{{ formatAmount(group.total) }}</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Ligne des conditions (toujours visible) -->
                                    <div class="mt-2 flex items-center gap-4 text-[10px]">
                                        <div class="flex items-center gap-1"><span class="font-bold text-gray-400">Délai:</span> <span class="text-gray-700 font-semibold">{{ group.delais }}</span></div>
                                        <div class="flex items-center gap-1"><span class="font-bold text-gray-400">Garantie:</span> <span class="text-gray-700 font-semibold">{{ group.garantie }}</span></div>
                                        <div class="flex items-center gap-1">
                                            <span class="font-bold text-gray-400">Acompte:</span> 
                                            <span class="text-gray-700 font-semibold">{{ group.acompte }}</span>
                                        </div>
                                        <div class="flex-grow"></div>
                                        <button @click.stop="toggleDetails(group.id)" class="text-blue-600 font-bold hover:underline">
                                            {{ expandedGroups[group.id] ? 'Masquer articles' : 'Voir articles' }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Détail par article (conditionnel) -->
                            <div v-show="expandedGroups[group.id]" class="p-3 bg-gray-50/50 border-t border-gray-100 animate-in fade-in duration-200">
                                <table class="w-full text-[11px]">
                                    <thead class="text-gray-400 font-bold uppercase bg-gray-100/30">
                                        <tr><th class="px-2 py-1.5 text-left">Désignation</th><th class="px-2 py-1.5 text-center w-12">Qté</th><th class="px-2 py-1.5 text-right w-24">P.U.</th><th class="px-2 py-1.5 text-right w-24">Total</th></tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="o in group.offres" :key="o.id">
                                            <td class="px-2 py-1.5">{{ props.fed.items?.find(i => i.id === o.fed_item_id)?.label || 'Global' }}</td>
                                            <td class="px-2 py-1.5 text-center">{{ formatQuantity(getArticleQuantity(o.fed_item_id)) }}</td>
                                            <td class="px-2 py-1.5 text-right">{{ formatAmount(o.prix_unitaire) }}</td>
                                            <td class="px-2 py-1.5 text-right font-bold text-purple-900">{{ formatAmount((o.prix_unitaire ?? 0) * getArticleQuantity(o.fed_item_id)) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="mb-1 block text-sm font-bold text-purple-900 uppercase">
                        Votre Avis Expert Métier <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        v-model="expertOpinionComment"
                        rows="3"
                        class="w-full rounded-md border-2 border-purple-200 px-3 py-2 text-sm focus:border-purple-400 focus:outline-none focus:ring-1 focus:ring-purple-400"
                        placeholder="Veuillez saisir votre avis en tant qu'expert métier (obligatoire)..."
                    />
                </div>

                <div class="flex justify-end">
                    <Button 
                        type="button" 
                        class="bg-purple-600 hover:bg-purple-700 text-white font-bold px-8" 
                        @click="submitExpertOpinion"
                    >
                        Enregistrer mon avis expert métier
                    </Button>
                </div>
            </div>

            <!-- Commentaire existant (si déjà traité) -->
            <div v-if="props.fed.n1_comment && !canValidate" class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4">
                <div class="flex items-start justify-between">
                    <p class="text-sm font-semibold text-amber-800">Commentaire N+1</p>
                    <span v-if="props.fed.n1_action_at" class="text-xs text-amber-600">
                        {{ new Date(props.fed.n1_action_at).toLocaleString('fr-FR') }}
                    </span>
                </div>
                <p class="mt-2 whitespace-pre-line text-sm text-amber-900">{{ props.fed.n1_comment }}</p>
            </div>

            <!-- Avis Expert Enregistré (si déjà fait) -->
            <div v-if="props.fed.expert_opinion_at" class="mb-6 rounded-lg border border-purple-200 bg-purple-50 p-4">
                <div class="flex items-start justify-between">
                    <p class="text-sm font-semibold text-purple-800 text-sm">Avis Expert Métier (N+1)</p>
                    <span class="text-xs text-purple-600">
                        {{ new Date(props.fed.expert_opinion_at).toLocaleString('fr-FR') }}
                    </span>
                </div>
                <div class="mt-2 flex items-center gap-2 mb-2">
                    <span class="text-xs font-bold text-purple-700 uppercase">Offre suggérée :</span>
                    <span class="text-sm font-bold text-purple-900">
                        {{ props.fed.fournisseur_offres?.find(o => o.id === props.fed.expert_opinion_offre_id)?.fournisseur || 'Identique' }}
                    </span>
                </div>
                <p class="whitespace-pre-line text-sm text-purple-900 italic border-l-4 border-purple-300 pl-3">"{{ props.fed.expert_opinion_comment }}"</p>
            </div>

            <!-- Document format -->
            <div class="rounded-lg border-2 border-gray-900 bg-white p-6 shadow-sm">

                <!-- En-tête document -->
                <div class="mb-8 flex items-start justify-between border-b border-gray-300 pb-4">
                    <div class="flex items-center gap-3">
                        <img src="/logo_Cofina.png" alt="Cofina" class="h-14 object-contain" />
                    </div>
                    <div class="text-right">
                        <h1 class="text-xl font-bold uppercase text-gray-900">Fiche d'Engagement de dépense</h1>
                        <p class="mt-1 text-sm font-medium">
                            Réf. : FED n°
                            <span class="inline-block min-w-[120px] border-b border-gray-400 font-semibold">{{ props.fed.code }}</span>
                        </p>
                    </div>
                </div>

                <!-- Section DEMANDE -->
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
                        <div class="grid grid-cols-[160px_1fr] gap-2 rounded border-l-4 border-red-500 bg-red-50/80 px-2 py-1.5">
                            <span class="font-medium text-red-800">Ligne(s) budgétaire(s) :</span>
                            <span class="font-medium text-red-900 uppercase">{{ formatBudgetLines(props.fed) }}</span>
                        </div>
                        <div class="grid grid-cols-[160px_1fr] gap-2">
                            <span class="font-medium text-gray-600">Motif de la dépense :</span>
                            <span class="font-medium uppercase">{{ props.fed.motive || '—' }}</span>
                        </div>
                        <div class="grid grid-cols-[160px_1fr] gap-2">
                            <span class="font-medium text-gray-600">Bénéficiaire(s) :</span>
                            <span>{{ props.fed.beneficiaire || '—' }}</span>
                        </div>
                        <div class="grid grid-cols-[160px_1fr] gap-2">
                            <span class="font-medium text-gray-600">Fournisseur :</span>
                            <span>—</span>
                        </div>
                        <div class="grid grid-cols-[160px_1fr] gap-2">
                            <span class="font-medium text-gray-600">Pro-forma :</span>
                            <span>☐ oui Réf n° .................... ☐ non</span>
                        </div>

                        <!-- Zone de signatures -->
                        <div class="mt-6 grid grid-cols-2 gap-8 border-t border-gray-300 pt-6">
                            <div class="flex flex-col items-center">
                                <div class="mb-2 flex h-24 w-full max-w-[250px] items-center justify-center border-2 border-gray-500 bg-gray-50 p-2">
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
                                <div class="mb-2 flex h-24 w-full max-w-[250px] items-center justify-center border-2 border-gray-500 bg-gray-50 p-2">
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

                <!-- Articles / Services (si présents) -->
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
                                <td class="px-2 py-2 text-center">{{ formatQuantity(item.quantity) }}</td>
                                <td class="px-2 py-2 text-xs text-center text-gray-600 italic">{{ item.description || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pièces jointes (si présentes) -->
                <div v-if="props.fed.attachments?.length" class="mb-6 border border-gray-400 p-4">
                    <h2 class="mb-3 text-sm font-bold uppercase">Pièces jointes</h2>
                    <div class="space-y-2 text-sm">
                        <a
                            v-for="attachment in props.fed.attachments"
                            :key="attachment.id"
                            :href="`/storage/${attachment.path}`"
                            target="_blank"
                            class="block text-blue-600 hover:text-blue-700 hover:underline"
                        >
                            📎 {{ attachment.original_name }}
                        </a>
                    </div>
                </div>
            </div>

            <!-- Vue lecture seule si déjà traité -->
            <div v-if="!canValidate && props.fed.n1_comment" class="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-5">
                <h3 class="mb-2 text-sm font-semibold text-gray-700">Décision enregistrée</h3>
                <div class="flex items-center justify-between text-sm text-gray-600">
                    <span :class="['inline-flex rounded-full px-3 py-1 text-sm font-medium', statusBadge(props.fed.status)]">
                        {{ statusLabel(props.fed.status) }}
                    </span>
                    <span v-if="props.fed.n1_action_at" class="text-xs text-gray-400">
                        Le {{ new Date(props.fed.n1_action_at).toLocaleDateString('fr-FR') }}
                    </span>
                </div>
            </div>

            <!-- Modale de signature -->
            <Dialog v-model:open="showSignatureModal">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {{ pendingAction === 'approve' ? 'Signature de validation' : (pendingAction === 'reject' ? 'Rejet de la demande' : 'Demande de complément') }}
                        </DialogTitle>
                        <DialogDescription>
                            {{ pendingAction === 'approve' ? 'Signez pour confirmer la validation.' : 'Veuillez motiver votre décision.' }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4 py-4">
                        <!-- Champ Commentaire pour le demandeur : Uniquement pour Rejet ou Complément -->
                        <div v-if="pendingAction !== 'approve'" class="space-y-2">
                            <label class="text-sm font-medium leading-none">
                                Message au demandeur
                                <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                v-model="comment"
                                rows="2"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
                                placeholder="Saisissez le motif ici (obligatoire)..."
                            />
                        </div>

                        <!-- Champ Avis obligatoire dans la modale (uniquement pour validation) -->
                        <div v-if="pendingAction === 'approve'" class="rounded-md border border-blue-200 bg-blue-50 p-4">
                            <label class="mb-1 block text-sm font-semibold text-blue-800">
                                Avis du responsable <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                v-model="avis"
                                rows="3"
                                class="w-full rounded-md border border-blue-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                placeholder="Votre avis sur cette demande de dépense..."
                            />
                        </div>
                    </div>

                    <SignatureInput v-if="pendingAction === 'approve'" ref="signatureInputRef" :saved-signature="props.authSignature" />
                    <DialogFooter>
                        <button type="button" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="showSignatureModal = false">
                            Annuler
                        </button>
                        <button 
                            type="button" 
                            :class="[
                                pendingAction === 'approve' ? 'bg-green-600 hover:bg-green-700' : (pendingAction === 'reject' ? 'bg-red-600 hover:bg-red-700' : 'bg-orange-600 hover:bg-orange-700'),
                                'inline-flex items-center rounded-md px-4 py-2 text-sm font-medium text-white transition-colors'
                            ]"
                            @click="submitDecision"
                        >
                            Confirmer {{ pendingAction === 'approve' ? 'la validation ✅' : '' }}
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
@media print {
    .print\:hidden {
        display: none !important;
    }
}
</style>
