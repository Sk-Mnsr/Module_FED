<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { computed, ref } from 'vue';
import { Calculator, ArrowRightLeft, History, CheckCircle2, XCircle, AlertCircle } from 'lucide-vue-next';
import ValidationHistoryModal from '@/components/ValidationHistoryModal.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
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
    budgetLines: BudgetLine[]; // toutes les lignes du même budget
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Contrôle de Gestion', href: '/feds/cg' },
    { title: props.fed.code, href: '#' },
];

const formatAmount = (value?: number | null) => {
    if (value === null || value === undefined) return '-';
    // Formatage avec des virgules comme séparateurs de milliers
    const formattedNum = new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value);
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

const formatDateTime = (value?: string | null) => {
    if (!value) return '-';
    return new Date(value).toLocaleString('fr-FR');
};

const formatBudgetLines = (fed: Fed) => {
    const uniqueCodes = [...new Set(fed.items?.filter(item => item.budget_line?.code).map(item => item.budget_line!.code))];
    if (uniqueCodes.length > 0) {
        return uniqueCodes.join(' ; ');
    }
    return '—';
};

// State
const canTreat = computed(() => props.fed.status === 'facilities_approved');
const isVerificationModalOpen = ref(false);

const itemBudgetLines = ref<Record<number, number>>({});

// Initialisation des lignes budgétaires par article
const initItemBudgetLines = () => {
    props.fed.items.forEach(item => {
        itemBudgetLines.value[item.id] = item.budget_line_id || props.fed.budget_line_id || 0;
    });
};
initItemBudgetLines();

const groupedItemsByLine = computed(() => {
    const groups: Record<number, { line: BudgetLine; items: FedItem[]; total: number }> = {};

    props.fed.items.forEach(item => {
        // La ligne provient désormais de l'assignation individuelle par article
        const currentLineId = itemBudgetLines.value[item.id];
        const line = currentLineId ? props.budgetLines.find(l => l.id === currentLineId) : item.budget_line || props.fed.budget_line;
        
        if (!line) return;
        if (!groups[line.id]) {
            groups[line.id] = { line, items: [], total: 0 };
        }
        groups[line.id].items.push(item);
        
        // Calcul du total basé sur l'offre choisie
        const offre = props.fed.fournisseur_offres?.find(o => o.id === props.fed.offre_choisie_id);
        if (offre) {
            groups[line.id].total += (offre.prix_unitaire ?? 0) * (item.quantity ?? 1);
        } else {
            // Fallback sur le total estimé de l'item si pas d'offre
            groups[line.id].total += item.total_price ?? 0;
        }
    });
    return Object.values(groups);
});

const isOutOfBudget = computed(() => {
    if (props.fed.status !== 'facilities_approved') {
        return props.fed.cg_budget_status === 'out_of_budget';
    }
    // Si une seule ligne est hors budget, la FED est hors budget
    return groupedItemsByLine.value.some(group => group.total > (group.line.montant_estime ?? 0));
});

// === Formulaire de traitement initial ===
const comment = ref('');

const submitDecision = (forceOutOfBudget = false) => {
    const c = comment.value?.trim();
    const finalStatus = forceOutOfBudget || isOutOfBudget.value ? 'out_of_budget' : 'in_budget';

    if (!confirm(`Confirmer la vérification budgétaire ?`)) return;

    router.post(
        `/feds/cg/${props.fed.id}/treat`,
        {
            cg_budget_status: finalStatus,
            comment: c,
            item_budget_lines: itemBudgetLines.value,
        },
        { preserveScroll: true }
    );
};

// === Changer la ligne budgétaire ===
const changingLine = ref(false);
const newBudgetLineId = ref<number | null>(null);
const changeLineNote = ref('');

const submitChangeLine = () => {
    if (!newBudgetLineId.value) {
        alert('Veuillez sélectionner une ligne budgétaire.');
        return;
    }
    router.post(
        `/feds/cg/${props.fed.id}/change-budget-line`,
        { budget_line_id: newBudgetLineId.value, note: changeLineNote.value || null },
        {
            preserveScroll: true,
            onSuccess: () => {
                changingLine.value = false;
                newBudgetLineId.value = null;
                changeLineNote.value = '';
            },
        }
    );
};

// === Reclassement rejeté ===
const rejectedReclassHistory = computed(() => {
    if (!props.fed.budget_line_histories) return null;
    const sortedHistories = [...props.fed.budget_line_histories].sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());
    return sortedHistories.find(h => h.status === 'rejected' && h.action === 'transfer_amount');
});

const actionLabel = (action: string) => {
    const map: Record<string, string> = {
        change_line: 'Changement de ligne',
        transfer_amount: 'Transfert de montant',
    };
    return map[action] ?? action;
};
</script>

<template>
    <Head :title="`Vérification CG - ${props.fed.code}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl p-6">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4 print:hidden">
                <div class="flex items-center gap-2">
                    <Link href="/feds/cg">
                        <Button variant="outline">Retour à la liste</Button>
                    </Link>
                    <span class="rounded-full bg-blue-100 px-3 py-1.5 text-sm font-medium text-blue-800">
                        FED {{ props.fed.code }}
                    </span>
                    <span class="rounded-full bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700">
                        Statut : {{ props.fed.status }}
                    </span>
                </div>
            </div>

            <!-- Fiche Demande -->
            <div class="mb-6 rounded-lg border-2 border-gray-900 bg-white p-6 shadow-sm print:border print:shadow-none">
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
                        <!-- Ligne budgétaire principale -->
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
                            <span class="font-medium">Offre finale retenue :</span>
                            <span class="font-bold text-gray-900">
                                <template v-if="props.fed.offre_choisie_id && props.fed.fournisseur_offres">
                                    {{ props.fed.fournisseur_offres.find(o => o.id === props.fed.offre_choisie_id)?.fournisseur || '—' }}
                                    ( Prix unitaire : {{ formatAmount(props.fed.fournisseur_offres.find(o => o.id === props.fed.offre_choisie_id)?.prix_unitaire) }} )
                                </template>
                                <template v-else>Non statué</template>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Articles / Services groupés par Ligne Budgétaire -->
            <div class="space-y-8 mb-8">
                <div v-for="(group, idx) in groupedItemsByLine" :key="idx" class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <!-- En-tête de la carte ligne budgétaire -->
                    <div class="flex flex-wrap items-center justify-between bg-[#1f2937] px-6 py-4 gap-4">
                        <div class="flex items-center gap-4">
                            <h3 class="text-sm font-bold uppercase text-white tracking-wider">{{ group.line.code }}</h3>
                            <span class="rounded-full bg-gray-700 px-3 py-1 text-xs font-medium text-gray-200">
                                {{ group.line.label }}
                            </span>
                        </div>

                        <!-- Analyse rapide du budget pour cette ligne -->
                        <div class="flex items-center gap-6">
                            <div class="text-right hidden sm:block">
                                <p class="text-[10px] font-bold uppercase text-gray-400">Solde disponible</p>
                                <p class="text-sm font-black text-gray-100">{{ formatAmount(group.line.montant_estime) }}</p>
                            </div>
                            <div class="text-right hidden sm:block">
                                <p class="text-[10px] font-bold uppercase text-gray-400">Fed Consommé</p>
                                <p class="text-sm font-black text-blue-400">{{ formatAmount(group.total) }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span v-if="group.total > (group.line.montant_estime ?? 0)" class="inline-flex items-center rounded bg-red-500/20 px-2 py-0.5 text-xs font-bold text-red-400 border border-red-500/30 uppercase tracking-tighter shadow-sm">
                                    <XCircle class="mr-1 h-3 w-3" /> Hors Budget
                                </span>
                                <span v-else class="inline-flex items-center rounded bg-emerald-500/20 px-2 py-0.5 text-xs font-bold text-emerald-400 border border-emerald-500/30 uppercase tracking-tighter shadow-sm">
                                    <CheckCircle2 class="mr-1 h-3 w-3" /> Budget OK
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-white text-[11px] uppercase font-bold text-gray-500 border-b border-gray-200">
                                    <th class="px-6 py-4 text-left w-12 text-gray-400">#</th>
                                    <th class="px-6 py-4 text-left">Article / Service</th>
                                    <th class="px-6 py-4 text-center w-32">Quantité</th>
                                    <th class="px-6 py-4 text-right text-blue-600 bg-blue-50/20 w-48">Montant Retenu</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-for="(item, i) in group.items" :key="item.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 text-gray-400 text-xs">{{ i + 1 }}</td>
                                    <td class="px-6 py-4 font-medium text-gray-800 uppercase text-xs">{{ item.label }}</td>
                                    <td class="px-6 py-4 text-center text-gray-600">
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">
                                            {{ formatQuantity(item.quantity) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right font-bold text-gray-900 bg-blue-50/10">
                                        {{ props.fed.fournisseur_offres?.find(o => o.id === props.fed.offre_choisie_id)?.prix_unitaire 
                                            ? formatAmount(props.fed.fournisseur_offres!.find(o => o.id === props.fed.offre_choisie_id)!.prix_unitaire! * (item.quantity ?? 1))
                                            : formatAmount(item.total_price) 
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-blue-50/30 font-black border-t border-gray-200">
                                    <td colspan="3" class="px-6 py-4 text-right text-[11px] uppercase tracking-wider text-blue-800">Total ligne budgétaire</td>
                                    <td class="px-6 py-4 text-right text-lg text-blue-900">{{ formatAmount(group.total) }}</td>
                                </tr>
                            </tfoot>
                        </table>

                        <!-- Action Reclassement spécifique -->
                        <div v-if="canTreat && group.total > (group.line.montant_estime ?? 0)" class="flex items-center justify-between bg-red-50 p-4 border-t border-red-100">
                            <div class="flex items-center gap-3">
                                <AlertCircle class="h-5 w-5 text-red-600" />
                                <p class="text-xs font-bold text-red-900 uppercase">Attention : Dépassement de budget sur cette ligne</p>
                            </div>
                            <Link :href="`/feds/cg/${props.fed.id}/reclasser`">
                                <Button size="sm" class="bg-amber-600 hover:bg-amber-700 text-white text-xs uppercase font-black shadow-sm">
                                    <ArrowRightLeft class="mr-2 h-4 w-4" /> Reclasser
                                </Button>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Vérification Budgétaire CG -->
            <Dialog v-model:open="isVerificationModalOpen">
                <DialogContent class="sm:max-w-[600px]">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2 text-xl">
                            <Calculator class="h-6 w-6 text-yellow-600"/>
                            Vérification Budgétaire
                        </DialogTitle>
                    </DialogHeader>

                    <div class="mt-4 flex-1 overflow-y-auto pr-2" style="max-height: calc(100vh - 12rem);">
                        <div class="space-y-6">
                            <!-- Assignation par article -->
                            <div v-if="props.budgetLines.length > 0 && props.fed.items.length > 0" class="rounded-lg border border-gray-200 shadow-sm bg-white overflow-hidden">
                                 <div class="bg-gray-50 border-b border-gray-200 px-4 py-3">
                                     <h3 class="text-xs font-bold uppercase text-gray-700">Assignation des lignes budgétaires par article</h3>
                                 </div>
                                 <div class="divide-y divide-gray-100">
                                     <div v-for="item in props.fed.items" :key="item.id" class="p-4 flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between hover:bg-gray-50/50 transition-colors">
                                         <div class="flex-1">
                                             <p class="text-sm font-semibold text-gray-800">{{ item.label }}</p>
                                             <p class="text-xs text-gray-500 mt-1">
                                                 Qté: {{ formatQuantity(item.quantity) }} | 
                                             Total Retenu: <span class="font-bold text-blue-700">{{ 
                                                     props.fed.fournisseur_offres?.find(o => o.id === props.fed.offre_choisie_id)?.prix_unitaire 
                                                     ? formatAmount((props.fed.fournisseur_offres.find(o => o.id === props.fed.offre_choisie_id)?.prix_unitaire ?? 0) * (item.quantity ?? 1))
                                                     : formatAmount(item.total_price) 
                                                 }}</span>
                                             </p>
                                         </div>
                                         <div class="w-full sm:w-64">
                                             <select
                                                v-model="itemBudgetLines[item.id]"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                             >
                                                <option :value="0" disabled>— Sélectionner une ligne —</option>
                                                <option v-for="line in props.budgetLines" :key="line.id" :value="line.id">
                                                    {{ line.code }}
                                                </option>
                                             </select>
                                         </div>
                                     </div>
                                 </div>
                            </div>

                            <!-- Analyse automatique du budget -->
                            <div class="space-y-4">
                                <div v-for="(group, idx) in groupedItemsByLine" :key="idx" class="p-4 rounded-lg border bg-gray-50 shadow-sm border-gray-200">
                                    <div class="flex items-center justify-between mb-3 border-b border-gray-200 pb-2">
                                        <p class="text-xs font-black uppercase text-gray-900">{{ group.line.code }}</p>
                                        <span :class="group.total > (group.line.montant_estime ?? 0) ? 'text-red-600' : 'text-green-600'" class="flex items-center gap-1 font-black text-[10px] uppercase">
                                            <CheckCircle2 v-if="group.total <= (group.line.montant_estime ?? 0)" class="h-3 w-3" />
                                            <XCircle v-else class="h-3 w-3" />
                                            {{ group.total > (group.line.montant_estime ?? 0) ? 'Insuffisant' : 'Disponible' }}
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-[9px] font-bold uppercase text-gray-500">Solde ligne</p>
                                            <p class="text-sm font-black text-gray-900">{{ formatAmount(group.line.montant_estime) }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-[9px] font-bold uppercase text-gray-500">Total consommé</p>
                                            <p class="text-sm font-black text-blue-700">{{ formatAmount(group.total) }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="isOutOfBudget" class="rounded-lg border border-red-300 bg-red-100 p-4">
                                    <div class="flex items-start gap-3">
                                        <AlertCircle class="h-5 w-5 text-red-600 shrink-0 mt-0.5" />
                                        <div>
                                            <h3 class="font-bold text-red-900">Le budget est insuffisant</h3>
                                            <p class="text-sm text-red-800 mt-1">L'une des lignes budgétaires est en dépassement. Vous devriez effectuer un reclassement avant de valider.</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div v-else class="rounded-lg border border-green-300 bg-green-100 p-4 flex items-center gap-3">
                                    <CheckCircle2 class="h-5 w-5 text-green-600 shrink-0" />
                                    <div>
                                        <h3 class="font-bold text-green-900">Totalement dans le budget</h3>
                                        <p class="text-sm text-green-800">Toutes les lignes sollicitées ont un solde suffisant.</p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Commentaire (Optionnel)</label>
                                <textarea
                                    v-model="comment"
                                    rows="3"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-white shadow-sm"
                                    placeholder="Précisez un dépassement, une rallonge ou toute autre information utile..."
                                />
                            </div>
                        </div>

                        <div class="sticky bottom-0 bg-white flex items-center justify-end gap-3 pt-4 pb-2 mt-4 border-t border-gray-100">
                            <Button type="button" variant="outline" @click="isVerificationModalOpen = false">Annuler</Button>
                            <Button v-if="isOutOfBudget" type="button" variant="outline" class="border-red-300 text-red-700 hover:bg-red-50 bg-white" @click="submitDecision(true)">
                                Valider tout de même (Hors budget)
                            </Button>
                            <Button v-else type="button" class="bg-indigo-600 hover:bg-indigo-700 text-white" @click="submitDecision(false)">
                                Confirmer la vérification
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- L'encart statique "Vérification Budgétaire CG" a été retiré, son contenu 
                 (statut budgétaire, commentaire, date d'approbation) 
                 est désormais visible directement dans le composant "Historique des validations" -->

            <!-- ===== HISTORIQUE DES MODIFICATIONS ===== -->
            <div
                v-if="props.fed.budget_line_histories && props.fed.budget_line_histories.length > 0"
                class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm print:hidden"
            >
                <h2 class="mb-4 flex items-center gap-2 border-b border-gray-200 pb-2 text-base font-bold uppercase text-gray-800">
                    <History class="h-5 w-5 text-gray-500" />
                    Historique des modifications budgétaires
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase">
                            <tr>
                                <th class="px-3 py-2 text-left text-gray-600">Date</th>
                                <th class="px-3 py-2 text-left text-gray-600">Auteur</th>
                                <th class="px-3 py-2 text-left text-gray-600">Action</th>
                                <th class="px-3 py-2 text-left text-gray-600">Ligne source</th>
                                <th class="px-3 py-2 text-left text-gray-600">Ligne cible</th>
                                <th class="px-3 py-2 text-right text-gray-600">Montant transféré</th>
                                <th class="px-3 py-2 text-left text-gray-600">Note</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="h in props.fed.budget_line_histories"
                                :key="h.id"
                                class="hover:bg-gray-50"
                            >
                                <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ formatDateTime(h.created_at) }}</td>
                                <td class="px-3 py-2 font-medium text-gray-800">{{ h.user?.name || '—' }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        :class="h.action === 'transfer_amount'
                                            ? 'bg-orange-100 text-orange-800'
                                            : 'bg-blue-100 text-blue-800'"
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                    >
                                        {{ actionLabel(h.action) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-gray-700 text-xs">
                                    <span v-if="h.fromLine">
                                        {{ h.fromLine.code }} — {{ h.fromLine.label }}<br />
                                        <span class="text-gray-500">
                                            {{ formatAmount(h.from_montant_before) }} → {{ formatAmount(h.from_montant_after) }}
                                        </span>
                                    </span>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-3 py-2 text-gray-700 text-xs">
                                    <span v-if="h.toLine">
                                        {{ h.toLine.code }} — {{ h.toLine.label }}<br />
                                        <span class="text-gray-500">
                                            {{ formatAmount(h.to_montant_before) }} → {{ formatAmount(h.to_montant_after) }}
                                        </span>
                                    </span>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-3 py-2 text-right font-medium text-orange-700">
                                    {{ h.montant_transfere ? formatAmount(h.montant_transfere) : '—' }}
                                </td>
                                <td class="px-3 py-2 text-gray-500 italic">{{ h.note || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Boutons d'action en bas de page -->
            <div class="mt-6 flex flex-wrap items-center justify-end gap-3 print:hidden">
                <ValidationHistoryModal :fed="props.fed" />
                <Button
                    v-if="canTreat"
                    type="button"
                    class="flex items-center gap-2 bg-yellow-600 hover:bg-yellow-700 text-white"
                    @click="isVerificationModalOpen = true"
                >
                    <Calculator class="h-4 w-4" />
                    Vérification Budgétaire
                </Button>
            </div>

        </div>
    </AppLayout>
</template>

<style scoped>
@media print {
    .print\:hidden { display: none !important; }
    .print\:border { border-width: 1px; }
    .print\:shadow-none { box-shadow: none; }
}
</style>
