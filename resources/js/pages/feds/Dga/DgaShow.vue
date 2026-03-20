<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
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
    daf_comment?: string | null;
    daf_action_at?: string | null;
    dga_comment?: string | null;
    dga_action_at?: string | null;
    offre_choisie_id?: number | null;
    items: FedItem[];
    attachments: FedAttachment[];
    requester?: FedRequester | null;
    budget_line?: BudgetLine | null;
    budget_lines?: BudgetLine[];
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
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Validations DGA', href: '/feds/dga' },
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

const formatBudgetLines = (fed: Fed) => {
    const uniqueCodes = [...new Set(fed.items?.filter(item => item.budget_line?.code).map(item => item.budget_line!.code))];
    if (uniqueCodes.length > 0) {
        return uniqueCodes.join(' ; ');
    }
    return '—';
};

const formatDate = (value?: string | null) => {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const canValidate = computed(() => props.fed.status === 'daf_approved');
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

const comment = ref(props.fed.dga_comment ?? '');

const approve = () => {
    router.post(`/feds/dga/${props.fed.id}/approve`, { comment: comment.value }, { preserveScroll: true });
};

const reject = () => {
    if (confirm('Rejeter cette FED ?')) {
        router.post(`/feds/dga/${props.fed.id}/reject`, { comment: comment.value }, { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="`Validation DGA - ${props.fed.code}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl p-6">
            <div class="mb-6 flex flex-wrap justify-end gap-2 print:hidden">
                <Link href="/feds/dga">
                    <Button variant="outline">Retour</Button>
                </Link>
                <ValidationHistoryModal :fed="props.fed" />
                <span class="rounded-full bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700">
                    Statut : {{ props.fed.status }}
                </span>
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
                                <td colspan="3" class="px-2 py-2">Montant total estimé initial</td>
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
                        <h2 class="text-sm font-bold uppercase">Commentaire DGA</h2>
                        <span v-if="props.fed.dga_action_at" class="text-xs text-gray-500">
                            {{ new Date(props.fed.dga_action_at).toLocaleString('fr-FR') }}
                        </span>
                    </div>
                    <textarea
                        v-model="comment"
                        rows="3"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        :readonly="!canValidate"
                        :class="{ 'bg-gray-50 text-gray-600': !canValidate }"
                        placeholder="Ajouter un commentaire (optionnel)."
                    />
                </div>
            </div>

            <div class="mt-6 flex flex-wrap justify-end gap-2 print:hidden">
                <Button type="button" variant="outline" class="text-red-600 hover:text-red-700" @click="reject" :disabled="!canValidate">Rejeter</Button>
                <Button type="button" class="bg-green-600 hover:bg-green-700" @click="approve" :disabled="!canValidate">
                    Valider (bon de commande)
                </Button>
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
