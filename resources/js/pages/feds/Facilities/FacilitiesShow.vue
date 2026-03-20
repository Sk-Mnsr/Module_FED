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
const currentSection = computed(() => {
    const url = typeof window !== 'undefined' ? window.location.href : '';
    try {
        const params = new URL(url, 'https://x').searchParams;
        const s = params.get('section') || 'demande';
        return ['demande', 'tableau'].includes(s) ? s : 'demande';
    } catch {
        return 'demande';
    }
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Validations Facilities', href: '/feds/facilities' },
    { title: props.fed.code, href: '#' },
];

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

const formatDate = (value?: string | null) => {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const formatBudgetLines = (fed: Fed) => {
    const uniqueCodes = [...new Set(fed.items?.filter(item => item.budget_line?.code).map(item => item.budget_line!.code))];
    if (uniqueCodes.length > 0) {
        return uniqueCodes.join(' ; ');
    }
    return '—';
};

const comment = ref('');
const offreChoisieId = ref<number | null>(null);
const requestExpertOpinion = ref(false);
const showActionModal = ref(false);
const pendingAction = ref<'approve' | 'reject' | 'needsInfo' | null>(null);

const openActionModal = (action: 'approve' | 'reject' | 'needsInfo') => {
    if (action === 'approve' && !offreChoisieId.value) {
        alert('Veuillez sélectionner l\'offre retenue dans le tableau.');
        return;
    }
    pendingAction.value = action;
    showActionModal.value = true;
};

const canValidate = computed(() => ['achats_approved', 'facilities_needs_info', 'expert_opinion_given'].includes(props.fed.status));
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
                request_expert_opinion: requestExpertOpinion.value 
            },
            { preserveScroll: true }
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
    const item = props.fed.items?.find(i => i.id == itemId);
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

    existing.forEach(o => {
        const key = o.fournisseur_id ? `id_${o.fournisseur_id}` : `name_${o.fournisseur}`;
        if (!groups[key]) {
            groups[key] = {
                id: o.id ?? Math.random(), // Use a random number if id is missing
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
        
        // Si cette offre est déjà celle choisie dans la FED, on initialise notre sélection locale
        if (props.fed.offre_choisie_id === o.id) {
            offreChoisieId.value = o.id ?? null;
        }
    });

    return Object.values(groups);
});

const getStatusBadgeClass = (s: string) => {
    switch (s) {
        case 'achats_approved': return 'bg-blue-100 text-blue-700 border border-blue-200';
        case 'expert_opinion_pending': return 'bg-purple-100 text-purple-700 border border-purple-200';
        case 'expert_opinion_given': return 'bg-green-100 text-green-700 border border-green-200 ring-2 ring-green-50';
        case 'facilities_needs_info': return 'bg-orange-100 text-orange-700 border border-orange-200';
        case 'facilities_approved': return 'bg-green-100 text-green-700 border border-green-200';
        case 'facilities_rejected': return 'bg-red-100 text-red-700 border border-red-200';
        default: return 'bg-gray-100 text-gray-700 border border-gray-200';
    }
};

const getStatusLabel = (s: string) => {
    switch (s) {
        case 'achats_approved': return 'En attente Facilities';
        case 'expert_opinion_pending': return 'En attente retour métier';
        case 'expert_opinion_given': return 'Avis expert reçu';
        case 'facilities_needs_info': return 'Complément demandé';
        case 'facilities_approved': return 'Approuvée Facilities';
        case 'facilities_rejected': return 'Rejetée Facilities';
        default: return s;
    }
};
</script>

<template>
    <Head :title="`Validation Facilities - ${props.fed.code}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl p-6">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4 print:hidden">
                <div class="flex items-center gap-2">
                    <Link href="/feds/facilities">
                        <Button variant="outline">Retour à la liste</Button>
                    </Link>
                    <span class="rounded-full bg-blue-100 px-3 py-1.5 text-sm font-medium text-blue-800">
                        FED {{ props.fed.code }}
                    </span>
                    <span :class="['rounded-full px-3 py-1.5 text-sm font-medium', getStatusBadgeClass(props.fed.status)]">
                        Statut : {{ getStatusLabel(props.fed.status) }}
                    </span>
                </div>
                <!-- Bouton Historique -->
                <ValidationHistoryModal :fed="props.fed" class="mt-2 sm:mt-0" />
            </div>

            <!-- Onglets de navigation -->
            <div class="mb-6 flex border-b border-gray-200 print:hidden">
                <Link
                    :href="`/feds/facilities/${props.fed.id}?section=demande`"
                    :class="[
                        'px-6 py-3 text-sm font-bold uppercase tracking-wider border-b-2 transition-all',
                        currentSection === 'demande' ? 'border-blue-600 text-blue-600 bg-blue-50/50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                    ]"
                >
                    1. Détail de la demande
                </Link>
                <Link
                    :href="`/feds/facilities/${props.fed.id}?section=tableau`"
                    :class="[
                        'px-6 py-3 text-sm font-bold uppercase tracking-wider border-b-2 transition-all',
                        currentSection === 'tableau' ? 'border-blue-600 text-blue-600 bg-blue-50/50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                    ]"
                >
                    2. Tableau comparatif & Offres
                </Link>
            </div>



            <!-- Bandeau Avis Expert -->
            <div v-if="isWaitingExpert" class="mb-6 rounded-lg border border-purple-200 bg-purple-50 p-4 flex items-center gap-3 text-purple-800">
                <span class="text-xl">⏳</span>
                <div>
                    <p class="font-bold">En attente du retour métier (Avis Expert)</p>
                    <p class="text-sm">Vous avez sollicité l'avis du manager N+1. La FED sera à nouveau modifiable une fois l'avis donné.</p>
                </div>
            </div>

            <!-- Fiche Demande (document principal) -->
            <div v-show="currentSection === 'demande'" class="mb-6 rounded-lg border-2 border-gray-900 bg-white p-6 shadow-sm print:border print:shadow-none">
                <div class="mb-6 flex items-start justify-between border-b border-gray-300 pb-4">
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

                <div class="border-2 border-gray-900 p-4">
                    <h2 class="mb-4 text-base font-bold uppercase">Demande</h2>
                    <div class="grid gap-3 text-sm">
                        <div class="grid grid-cols-[140px_1fr] gap-2">
                            <span class="font-medium">Date :</span>
                            <span>{{ formatDate(props.fed.date) }}</span>
                        </div>
                        <div class="grid grid-cols-[140px_1fr] gap-2">
                            <span class="font-medium">Demandeur :</span>
                            <span class="uppercase">{{ props.fed.demandeur || props.fed.requester?.name || '—' }}</span>
                        </div>
                        <div class="grid grid-cols-[140px_1fr] gap-2">
                            <span class="font-medium">Fonction :</span>
                            <span class="uppercase">{{ props.fed.fonction || '—' }}</span>
                        </div>
                        <div class="grid grid-cols-[140px_1fr] gap-2">
                            <span class="font-medium">Département :</span>
                            <span>{{ props.fed.department || '—' }}</span>
                        </div>
                        <div class="grid grid-cols-[140px_1fr] gap-2">
                            <span class="font-medium">Motif de la dépense :</span>
                            <span class="uppercase">{{ props.fed.motive || '—' }}</span>
                        </div>
                        <div class="grid grid-cols-[140px_1fr] gap-2">
                            <span class="font-medium">Bénéficiaire(s) :</span>
                            <span>{{ props.fed.beneficiaire || '—' }}</span>
                        </div>
                        <div class="grid grid-cols-[140px_1fr] gap-2">
                            <span class="font-medium">Fournisseur :</span>
                            <span>—</span>
                        </div>
                        <div class="grid grid-cols-[140px_1fr] gap-2">
                            <span class="font-medium">Pro-forma :</span>
                            <span>☐ oui Réf n° .................... ☐ non</span>
                        </div>

                        <!-- Signatures Demandeur et Manager (N+1) uniquement -->
                        <div class="mt-6 grid grid-cols-2 gap-6 border-t border-gray-300 pt-6">
                            <div class="flex flex-col items-center justify-center">
                                <div class="mb-2 flex h-20 w-full max-w-[140px] items-center justify-center border-2 border-gray-500 bg-gray-50 p-2">
                                    <img v-if="props.fed.requester_signature" :src="props.fed.requester_signature" alt="Signature demandeur" class="max-h-full max-w-full object-contain" />
                                </div>
                                <span class="text-xs font-medium">Demandeur</span>
                            </div>
                            <div class="flex flex-col items-center justify-center">
                                <div class="mb-2 flex h-20 w-full max-w-[140px] items-center justify-center border-2 border-gray-500 bg-gray-50 p-2">
                                    <img v-if="props.fed.n1_signature" :src="props.fed.n1_signature" alt="Signature Manager" class="max-h-full max-w-full object-contain" />
                                </div>
                                <span class="text-xs font-medium">Manager (N+1)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Articles / Services (dans l'onglet Détail avec Demande) -->
            <div v-show="currentSection === 'demande'" class="mb-6 rounded-lg border-2 border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-base font-bold uppercase text-gray-800">Articles / Services</h2>
                <template v-if="props.fed.items?.length">
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
                        <tfoot>
                            <tr class="bg-gray-100 font-medium">
                                <td colspan="3" class="px-2 py-2">Montant total estimé initial</td>
                                <td class="px-2 py-2 text-right">{{ formatAmount(props.fed.estimated_total) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </template>
                <p v-else class="text-sm text-gray-500">Aucun article.</p>
                <!-- Pièces jointes (pour les articles) -->
                <div class="mt-6 border-t border-gray-200 pt-6">
                    <h3 class="mb-3 text-sm font-bold uppercase text-gray-700">Pièces jointes</h3>
                    <div v-if="props.fed.attachments?.length" class="space-y-2 text-sm">
                        <a v-for="attachment in props.fed.attachments" :key="attachment.id" :href="`/storage/${attachment.path}`" target="_blank" class="block text-blue-600 hover:text-blue-700">
                            {{ attachment.original_name }}
                        </a>
                    </div>
                    <p v-else class="text-sm text-gray-500">Aucune pièce jointe.</p>
                </div>
            </div>

            <!-- Tableau comparatif des offres (Fiche par fournisseur) -->
            <div v-show="currentSection === 'tableau'" class="mb-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-gray-900 uppercase">Tableau comparatif des offres</h2>
                    <div class="text-xs font-medium text-gray-500 bg-gray-100 px-3 py-1 rounded-full uppercase">
                        {{ groupedOffres.length }} Fournisseur(s) comparé(s)
                    </div>
                </div>

                <template v-if="groupedOffres.length">
                    <div class="space-y-6">
                        <div 
                            v-for="(group, gIdx) in groupedOffres" 
                            :key="gIdx" 
                            class="overflow-hidden rounded-xl border-2 transition-all"
                            :class="[
                                offreChoisieId === group.representative_id 
                                    ? 'border-green-500 bg-green-50/20 ring-4 ring-green-50' 
                                    : 'border-gray-200 bg-white hover:border-blue-300'
                            ]"
                        >
                            <!-- Bandeau supérieur du fournisseur -->
                            <div 
                                class="flex items-center gap-4 px-6 py-4 cursor-pointer select-none"
                                :class="offreChoisieId === group.representative_id ? 'bg-green-50 border-b border-green-100' : 'bg-gray-50 border-b border-gray-100'"
                                @click="toggleDetails(group.id)"
                            >
                                <div v-if="canValidate" class="flex-shrink-0" @click.stop>
                                    <input
                                        v-model="offreChoisieId"
                                        type="radio"
                                        :name="`offre-${props.fed.id}`"
                                        :value="group.representative_id"
                                        class="h-5 w-5 text-green-600 focus:ring-green-500 cursor-pointer"
                                    />
                                </div>
                                <div v-else-if="props.fed.offre_choisie_id" class="flex-shrink-0">
                                    <span v-if="group.offres.some(o => o.id === props.fed.offre_choisie_id)" class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-green-600 text-white font-bold">✓</span>
                                    <span v-else class="h-6 w-6 inline-block"></span>
                                </div>

                                <div class="flex-grow">
                                    <div class="flex items-center justify-between font-bold">
                                        <div class="flex items-center gap-3">
                                            <h3 class="text-gray-900 uppercase text-lg">{{ group.fournisseur }}</h3>
                                            <span 
                                                v-if="group.offres.some(o => o.id === props.fed.expert_opinion_offre_id)"
                                                class="inline-flex items-center rounded bg-orange-100 px-2 py-0.5 text-[10px] font-black text-orange-700 border border-orange-200 uppercase tracking-tighter animate-pulse shadow-sm"
                                            >
                                                ⭐ Choix de l'expert métier
                                            </span>
                                            <svg 
                                                xmlns="http://www.w3.org/2000/svg" 
                                                class="h-4 w-4 text-gray-400 transition-transform" 
                                                :class="{ 'rotate-180': expandedGroups[group.id] }"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            >
                                                <path d="m6 9 6 6 6-6"/>
                                            </svg>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-[10px] font-bold uppercase text-gray-500 mb-0.5">Montant total TTC </div>
                                            <div class="text-xl font-black text-blue-900">{{ formatAmount(group.total) }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Ligne des conditions (toujours visible) -->
                            <div class="px-6 py-2 bg-white flex items-center gap-6 border-b border-gray-50">
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold uppercase text-gray-400">Délai:</span>
                                    <span class="text-xs font-semibold text-gray-700">{{ group.delais }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold uppercase text-gray-400">Garantie:</span>
                                    <span class="text-xs font-semibold text-gray-700">{{ group.garantie }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold uppercase text-gray-400">Conformité:</span>
                                    <span class="text-xs font-bold" :class="group.conformite === 'OUI' ? 'text-green-600' : 'text-orange-600'">{{ group.conformite }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold uppercase text-gray-400">Acompte:</span>
                                    <span class="text-xs font-semibold text-gray-700">{{ group.acompte }}</span>
                                </div>
                                <div class="flex-grow"></div>
                                <button 
                                    @click="toggleDetails(group.id)"
                                    class="text-[10px] font-bold uppercase text-blue-600 hover:text-blue-800 flex items-center gap-1"
                                >
                                    {{ expandedGroups[group.id] ? 'Masquer le détail' : 'Voir le détail des articles' }}
                                </button>
                            </div>

                            <!-- Détail par article (conditionnel) -->
                            <div v-show="expandedGroups[group.id]" class="p-4 overflow-x-auto bg-gray-50/30 border-b border-gray-100 animate-in fade-in slide-in-from-top-1 duration-200">
                                <table class="w-full text-xs">
                                    <thead class="bg-gray-100/50 text-[10px] font-bold uppercase text-gray-500">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Article / Service</th>
                                            <th class="px-3 py-2 text-center w-20">Qté</th>
                                            <th class="px-3 py-2 text-right w-32">P.U.</th>
                                            <th class="px-3 py-2 text-right w-32 bg-blue-50/50">Total TTC</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="o in group.offres" :key="o.id" class="hover:bg-white transition-colors">
                                            <td class="px-3 py-2 font-medium text-gray-800">
                                                {{ props.fed.items?.find(i => i.id === o.fed_item_id)?.label || 'Montant global' }}
                                            </td>
                                            <td class="px-3 py-2 text-center text-gray-500">
                                                {{ formatQuantity(getArticleQuantity(o.fed_item_id)) }}
                                            </td>
                                            <td class="px-3 py-2 text-right text-gray-600">
                                                {{ formatAmount(o.prix_unitaire) }}
                                            </td>
                                            <td class="px-3 py-2 text-right font-bold text-blue-800 bg-blue-50/30">
                                                {{ formatAmount((o.prix_unitaire ?? 0) * getArticleQuantity(o.fed_item_id)) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pied de carte : Documents -->
                            <div class="bg-gray-100/30 px-6 py-2.5 flex items-center justify-between gap-4">
                                <div class="text-[9px] font-bold uppercase text-gray-400">
                                    Fournisseur selectionné : {{ group.fournisseur }}
                                </div>

                                <div class="flex items-center gap-2">
                                    <template v-if="group.attachments.length">
                                        <a 
                                            v-for="att in group.attachments" 
                                            :key="att.id" 
                                            :href="`/storage/${att.path}`" 
                                            target="_blank" 
                                            class="flex items-center gap-2 rounded bg-white px-3 py-1.5 text-[11px] font-bold text-blue-600 shadow-sm border border-blue-100 hover:bg-blue-50 transition-colors"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.51a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                            Voir le devis
                                        </a>
                                    </template>
                                    <span v-else class="text-[10px] italic text-gray-400 uppercase">Aucun document joint</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
                <div v-else class="rounded-xl border-2 border-dashed border-gray-200 p-12 text-center">
                    <p class="text-gray-500 font-medium">Aucune offre n'a encore été saisie pour cette demande.</p>
                </div>
            </div>

            <!-- Avis enregistré (affiché quand déjà traité, sur la section Tableau) -->
            <div v-if="currentSection === 'tableau' && !canValidate && props.fed.facilities_comment" class="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-4 print:hidden">
                <h2 class="mb-2 text-sm font-bold uppercase text-gray-600">Avis Facilities</h2>
                <p class="text-sm text-gray-700">{{ props.fed.facilities_comment }}</p>
                <span v-if="props.fed.facilities_action_at" class="mt-2 block text-xs text-gray-500">
                    {{ new Date(props.fed.facilities_action_at).toLocaleString('fr-FR') }}
                </span>
            </div>

            <!-- Actions intégrées (Valider, Rejeter, Mettre en attente) - uniquement sur le tableau comparatif -->
            <div v-if="currentSection === 'tableau' && canValidate" class="mt-6 rounded-lg border-2 border-gray-200 bg-white p-6 shadow-sm print:hidden">
                <!-- Option Avis Expert (disponible avant validation) -->
                <div class="mb-6 rounded-lg bg-blue-50/50 p-4 border border-blue-200 shadow-sm transition-all hover:bg-blue-50">
                    <label class="flex items-center gap-4 cursor-pointer">
                        <input 
                            v-model="requestExpertOpinion" 
                            type="checkbox" 
                            class="h-6 w-6 rounded border-blue-400 text-blue-600 focus:ring-blue-500 cursor-pointer"
                        />
                        <div class="flex flex-col">
                            <span class="text-sm font-black text-blue-900 uppercase">Solliciter l'avis de l'expert métier (N+1)</span>
                            <span class="text-xs text-blue-700 font-medium">Le manager (N+1) recevra la FED pour donner sa recommandation avant votre validation finale.</span>
                        </div>
                    </label>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <Button type="button" variant="outline" @click="openActionModal('needsInfo')">
                        Demander un complément
                    </Button>
                    <Button type="button" variant="outline" class="border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700" @click="openActionModal('reject')">
                        Rejeter la demande
                    </Button>
                    <Button type="button" class="bg-green-600 hover:bg-green-700 text-white" @click="openActionModal('approve')">
                        Valider l'offre retenue
                    </Button>
                </div>
            </div>

            <!-- Modale de décision Facilities -->
            <Dialog v-model:open="showActionModal">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {{ pendingAction === 'approve' ? 'Validation de l\'offre' : (pendingAction === 'reject' ? 'Rejet de la demande' : 'Demande de complément') }}
                        </DialogTitle>
                        <DialogDescription>
                            Veuillez accompagner votre action d'un commentaire explicatif.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4 py-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                                Commentaire / Avis <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                v-model="comment"
                                rows="4"
                                class="flex min-h-[100px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder="Saisissez votre commentaire obligatoire ici..."
                            ></textarea>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="showActionModal = false">Annuler</Button>
                        <Button 
                            :class="[
                                pendingAction === 'approve' ? 'bg-green-600 hover:bg-green-700' : (pendingAction === 'reject' ? 'bg-red-600 hover:bg-red-700' : 'bg-orange-600 hover:bg-orange-700'),
                                'text-white'
                            ]"
                            @click="submitDecision"
                        >
                            Confirmer
                        </Button>
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
    .print\:border {
        border-width: 1px;
    }
    .print\:shadow-none {
        box-shadow: none;
    }
}
</style>
