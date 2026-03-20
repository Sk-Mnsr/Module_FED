<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { computed, ref } from 'vue';
import { ArrowRightLeft, CheckCircle2, XCircle } from 'lucide-vue-next';
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
    if (value === null || value === undefined) return '-';
    // Formatage avec séparateur de milliers virgule
    const formattedNum = new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(Number(value) || 0);
    return `${formattedNum} FCFA`;
};

const formatQuantity = (value?: number | null) => {
    if (value === null || value === undefined) return '—';
    return Math.round(value).toString();
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

const canValidate = computed(() => props.fed.status === 'facilities_approved' || props.fed.status === 'cg_treated');
const canValidateReclass = computed(() => props.fed.status === 'waiting_daf_reclass_approval');

// Calcul : la FED sera-t-elle transmises au DGA ou passera-t-elle directement en BOC ?
const willGoToDga = computed(() => {
    const threshold = props.dgaThreshold ?? 0;
    const total = parseFloat(String(props.fed.estimated_total ?? 0));
    return threshold > 0 && total >= threshold;
});

const pendingReclassHistory = computed(() => {
    return props.fed.budget_line_histories?.find(h => h.status === 'pending' && h.action === 'transfer_amount');
});

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
                attachments: o.attachments ?? []
            };
        }
        groups[key].offres.push(o);
        const qty = getArticleQuantity(o.fed_item_id);
        groups[key].total += (o.prix_unitaire ?? 0) * qty;
    });

    return Object.values(groups);
});

const comment = ref(props.fed.daf_comment ?? '');

const approve = () => {
    router.post(`/feds/daf/${props.fed.id}/approve`, { comment: comment.value }, { preserveScroll: true });
};

const reject = () => {
    if (confirm('Rejeter cette FED ?')) {
        router.post(`/feds/daf/${props.fed.id}/reject`, { comment: comment.value }, { preserveScroll: true });
    }
};

const approveReclass = () => {
    router.post(`/feds/daf/${props.fed.id}/approve-reclass`, { comment: comment.value }, { preserveScroll: true });
};

const rejectReclass = () => {
    if (!comment.value.trim()) {
        alert('Veuillez saisir un motif de rejet dans le champ Commentaire DAF.');
        return;
    }
    if (confirm('Rejeter cette demande de transfert budgétaire ? La FED retournera au Contrôle de Gestion.')) {
        router.post(`/feds/daf/${props.fed.id}/reject-reclass`, { comment: comment.value }, { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="`Validation DAF - ${props.fed.code}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl p-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
                <div class="flex items-center gap-2">
                    <Link href="/feds/daf">
                        <Button variant="outline">Retour</Button>
                    </Link>
                    <span class="rounded-full bg-blue-100 px-3 py-1.5 text-sm font-medium text-blue-800">
                        FED {{ props.fed.code }}
                    </span>
                    <span class="rounded-full px-3 py-1.5 text-sm font-medium"
                        :class="{
                            'bg-green-100 text-green-800': props.fed.status === 'daf_approved',
                            'bg-red-100 text-red-800': props.fed.status === 'daf_rejected',
                            'bg-yellow-100 text-yellow-800': props.fed.status === 'cg_treated',
                            'bg-amber-100 text-amber-800': props.fed.status === 'waiting_daf_reclass_approval',
                            'bg-gray-100 text-gray-700': !['daf_approved','daf_rejected','cg_treated','waiting_daf_reclass_approval'].includes(props.fed.status),
                        }"
                    >
                        <span v-if="props.fed.status === 'cg_treated'">En attente de validation DAF</span>
                        <span v-else-if="props.fed.status === 'daf_approved'">Validé par le DAF</span>
                        <span v-else-if="props.fed.status === 'daf_rejected'">Rejeté par le DAF</span>
                        <span v-else-if="props.fed.status === 'waiting_daf_reclass_approval'">En attente approbation reclassement</span>
                        <span v-else>{{ props.fed.status }}</span>
                    </span>
                </div>
                <ValidationHistoryModal :fed="props.fed" />
            </div>

            <div v-if="canValidateReclass && pendingReclassHistory" class="mb-6 rounded-lg border-2 border-amber-400 bg-amber-50 p-6 shadow-sm print:hidden">
                <h2 class="mb-4 text-lg font-bold text-amber-900 border-b border-amber-200 pb-2 flex items-center gap-2">
                    <ArrowRightLeft class="h-5 w-5" />
                    Validation du reclassement budgétaire
                </h2>
                
                <p class="mb-4 text-sm text-amber-800">
                    Le Contrôle de Gestion a demandé un transfert budgétaire avant de pouvoir poursuivre la validation de cette Fiche d'Engagement de Dépenses.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="bg-white p-4 rounded border border-amber-200 shadow-sm">
                        <p class="text-xs text-gray-500 font-semibold mb-1 uppercase">Ligne Source (à débiter)</p>
                        <p class="font-medium text-gray-900">{{ pendingReclassHistory.from_line?.code || '—' }}</p>
                        <p class="text-sm text-gray-600">{{ pendingReclassHistory.from_line?.label || '—' }}</p>
                    </div>
                    
                    <div class="bg-white p-4 rounded border border-amber-200 shadow-sm">
                        <p class="text-xs text-gray-500 font-semibold mb-1 uppercase">Ligne Cible (à créditer)</p>
                        <p class="font-medium text-gray-900">{{ pendingReclassHistory.to_line?.code || '—' }}</p>
                        <p class="text-sm text-gray-600">{{ pendingReclassHistory.to_line?.label || '—' }}</p>
                    </div>
                </div>

                <div class="bg-white p-4 rounded border border-amber-200 shadow-sm flex items-center justify-between mb-4">
                    <div>
                        <p class="text-xs text-gray-500 font-semibold mb-1 uppercase">Montant du transfert demandé</p>
                        <p class="text-2xl font-bold text-amber-700">{{ formatAmount(pendingReclassHistory.montant_transfere) }}</p>
                    </div>
                    <div v-if="pendingReclassHistory.note" class="max-w-xs text-right">
                        <p class="text-xs text-gray-500 font-semibold mb-1 uppercase">Motif du Contrôle de Gestion</p>
                        <p class="text-sm text-gray-800 italic">« {{ pendingReclassHistory.note }} »</p>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border-2 border-gray-900 bg-white p-6 shadow-sm print:border print:shadow-none">
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

                <div class="mb-6 border-2 border-gray-900 p-4">
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
                        <div class="grid grid-cols-[140px_1fr] gap-2 rounded border-l-4 border-red-500 bg-red-50/80 px-2 py-1.5">
                            <span class="font-medium text-red-800">Ligne(s) budgétaire(s) :</span>
                            <span class="font-medium text-red-900 uppercase">{{ formatBudgetLines(props.fed) }}</span>
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
                        <tfoot>
                            <tr class="bg-gray-100 font-medium">
                                <td colspan="3" class="px-2 py-2">Montant total estimé</td>
                                <td class="px-2 py-2 text-right">{{ formatAmount(props.fed.estimated_total) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div v-if="props.fed.fournisseur_offres?.length" class="mb-6">
                    <h2 class="mb-3 text-sm font-bold uppercase text-gray-800">Tableau comparatif – Offre retenue</h2>
                    
                    <div class="space-y-4">
                        <div 
                            v-for="(group, gIdx) in groupedOffres" 
                            :key="gIdx" 
                            class="overflow-hidden rounded-xl border-2 transition-all shadow-sm"
                            :class="[
                                props.fed.offre_choisie_id === group.representative_id || group.offres.some(o => o.id === props.fed.offre_choisie_id)
                                    ? 'border-green-500 bg-green-50/20 ring-4 ring-green-50' 
                                    : 'border-gray-200 bg-white'
                            ]"
                        >
                            <!-- Bandeau supérieur du fournisseur -->
                            <div 
                                class="flex items-center gap-4 px-6 py-4 cursor-pointer select-none"
                                :class="props.fed.offre_choisie_id === group.representative_id || group.offres.some(o => o.id === props.fed.offre_choisie_id) ? 'bg-green-50 border-b border-green-100' : 'bg-gray-50 border-b border-gray-100'"
                                @click="toggleDetails(group.id)"
                            >
                                <div class="flex-shrink-0">
                                    <span v-if="props.fed.offre_choisie_id === group.representative_id || group.offres.some(o => o.id === props.fed.offre_choisie_id)" class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-green-600 text-white font-bold">✓</span>
                                    <span v-else class="h-6 w-6 inline-block"></span>
                                </div>

                                <div class="flex-grow">
                                    <div class="flex items-center justify-between font-bold">
                                        <div class="flex items-center gap-3">
                                            <h3 class="text-gray-900 uppercase text-lg">{{ group.fournisseur }}</h3>
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
                                <div class="flex-grow"></div>
                                <button 
                                    @click="toggleDetails(group.id)"
                                    class="text-[10px] font-bold uppercase text-blue-600 hover:text-blue-800"
                                >
                                    {{ expandedGroups[group.id] ? 'Masquer le détail' : 'Voir le détail des articles' }}
                                </button>
                            </div>

                            <!-- Détail par article (conditionnel) -->
                            <div v-show="expandedGroups[group.id]" class="p-4 bg-gray-50/30 border-b border-gray-100 animate-in fade-in slide-in-from-top-1 duration-200">
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
                </div>

                <div class="mb-6 border border-gray-400 p-4">
                    <h2 class="mb-3 text-sm font-bold uppercase">Pièces jointes</h2>
                    <div v-if="props.fed.attachments?.length" class="space-y-2 text-sm">
                        <a v-for="attachment in props.fed.attachments" :key="attachment.id" :href="`/storage/${attachment.path}`" target="_blank" class="block text-blue-600 hover:text-blue-700">
                            {{ attachment.original_name }}
                        </a>
                    </div>
                    <p v-else class="text-sm text-gray-500">Aucune pièce jointe.</p>
                </div>

                <div class="mb-6 border border-gray-400 p-4 print:hidden">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-sm font-bold uppercase">Commentaire DAF</h2>
                        <span v-if="props.fed.daf_action_at" class="text-xs text-gray-500">
                            {{ new Date(props.fed.daf_action_at).toLocaleString('fr-FR') }}
                        </span>
                    </div>
                    <textarea
                        v-model="comment"
                        rows="3"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        :readonly="!canValidate && !canValidateReclass"
                        :class="{ 'bg-gray-50 text-gray-600': !canValidate && !canValidateReclass }"
                        placeholder="Ajouter un commentaire (optionnel pour approuver, obligatoire pour rejeter)."
                    />
                </div>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
                <!-- Indicateur seuil DAF -->
                <div v-if="canValidate && (props.dgaThreshold ?? 0) > 0" class="text-sm text-gray-500 flex items-center gap-1.5">
                    <span
                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 font-medium text-xs"
                        :class="willGoToDga ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800'"
                    >
                        {{ willGoToDga ? '→ Transmission au DGA' : '→ BOC direct' }}
                    </span>
                    <span>(Seuil DGA : {{ new Intl.NumberFormat('fr-FR', {style:'currency',currency:'XOF',maximumFractionDigits:0}).format(props.dgaThreshold ?? 0) }})</span>
                </div>
                <div v-else class="flex-1" />

                <div class="flex flex-wrap gap-2">
                    <template v-if="canValidateReclass">
                        <Button type="button" variant="outline" class="text-red-600 border-red-200 hover:text-red-700 hover:bg-red-50" @click="rejectReclass">
                            Rejeter le transfert
                        </Button>
                        <Button type="button" class="bg-amber-600 hover:bg-amber-700" @click="approveReclass">
                            Approuver le transfert (Le cycle de la FED continuera)
                        </Button>
                    </template>
                    <template v-else-if="canValidate">
                        <Button type="button" variant="outline" class="text-red-600 hover:text-red-700" @click="reject">Rejeter</Button>
                        <Button
                            v-if="willGoToDga"
                            type="button"
                            class="bg-amber-600 hover:bg-amber-700 text-white"
                            @click="approve"
                        >
                            Valider et transmettre au DGA
                        </Button>
                        <Button
                            v-else
                            type="button"
                            class="bg-green-600 hover:bg-green-700 text-white"
                            @click="approve"
                        >
                            Valider et générer le BOC
                        </Button>
                    </template>
                </div>
            </div>
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
