<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { computed } from 'vue';
import { Eye } from 'lucide-vue-next';

interface FedRequester {
    name: string;
}

interface Fed {
    id: number;
    code: string;
    date?: string | null;
    demandeur?: string | null;
    department?: string | null;
    estimated_total?: number | string | null;
    status: string;
    created_at: string;
    requester?: FedRequester | null;
    offre_choisie_id?: number | null;
    fournisseur_offres?: any[];
    items?: any[];
}

interface Props {
    feds: { data: Fed[]; links: any[]; meta?: any; total?: number; current_page?: number; per_page?: number; last_page?: number };
    selectedStatus?: string | null;
    activeTab?: string;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Validations DAF', href: '/feds/daf' }];

const currentPage = computed(() => props.feds.current_page || props.feds.meta?.current_page || 1);
const totalItems = computed(() => props.feds.total || props.feds.meta?.total || 0);
const perPage = computed(() => props.feds.per_page || props.feds.meta?.per_page || 10);

const statusLabel = (status: string) => {
    const labels: Record<string, string> = {
        facilities_approved: 'En attente DAF',
        daf_approved: 'Validée DAF',
        daf_rejected: 'Rejetée DAF',
        waiting_daf_reclass_approval: 'Attente Reclassement',
        cg_treated: 'Validée Budget',
        expert_opinion_pending: 'Avis Expert en attente',
        expert_opinion_given: 'Avis Expert donné',
        bon_de_commande: 'Bon de commande généré',
    };
    return labels[status] ?? status;
};

const statusBadge = (status: string) => {
    const badges: Record<string, string> = {
        facilities_approved: 'bg-blue-100 text-blue-700',
        daf_approved: 'bg-green-100 text-green-700',
        daf_rejected: 'bg-red-100 text-red-700',
        waiting_daf_reclass_approval: 'bg-amber-100 text-amber-700',
        cg_treated: 'bg-cyan-100 text-cyan-700 font-bold',
        expert_opinion_pending: 'bg-purple-100 text-purple-700',
        expert_opinion_given: 'bg-purple-100 text-purple-700 ring-2 ring-purple-200',
        bon_de_commande: 'bg-emerald-100 text-emerald-800',
    };
    return badges[status] ?? 'bg-gray-100 text-gray-700';
};

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

const handlePageChange = (page: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set('page', page.toString());
    url.searchParams.set('per_page', perPage.value.toString());
    router.get(url.toString(), {}, { preserveScroll: true, preserveState: true, only: ['feds'] });
};

const handleItemsPerPageChange = (items: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', items.toString());
    url.searchParams.set('page', '1');
    router.visit(url.toString(), { preserveScroll: true });
};

const updateStatusFilter = (value: string) => {
    const url = new URL(window.location.href);
    if (value) url.searchParams.set('status', value);
    else url.searchParams.delete('status');
    url.searchParams.set('page', '1');
    router.get(url.toString(), {}, { preserveScroll: true, preserveState: true, only: ['feds'] });
};

const switchTab = (tab: string) => {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    url.searchParams.delete('status');
    url.searchParams.set('page', '1');
    router.visit(url.toString(), { preserveScroll: true });
};

const columns: Column[] = [
    { key: 'code', title: 'N° FED' },
    { key: 'date', title: 'Date' },
    { key: 'demandeur', title: 'Demandeur' },
    { key: 'department', title: 'Département' },
    { key: 'estimated_total', title: 'Montant' },
    { key: 'status', title: 'Statut' },
    { key: 'created_at', title: 'Création' },
    { key: 'actions', title: 'Actions' },
];

const tableData = computed(() =>
    props.feds.data.map(fed => {
        let displayAmount = fed.estimated_total;
        
        // Si le montant estimé est vide ou nul, et qu'on a une offre choisie
        if ((!displayAmount || displayAmount === '0.00') && fed.offre_choisie_id) {
            const selectedSupplierId = fed.fournisseur_offres?.find(o => o.id === fed.offre_choisie_id)?.fournisseur_id;
            
            if (selectedSupplierId) {
                const supplierOffers = fed.fournisseur_offres?.filter(o => o.fournisseur_id === selectedSupplierId) || [];
                let total = 0;
                supplierOffers.forEach(o => {
                    const item = fed.items?.find(i => i.id === o.fed_item_id);
                    const qty = item ? (item.quantity ?? 1) : 1;
                    total += (o.prix_unitaire ?? 0) * qty;
                });
                displayAmount = total;
            } else {
                // Si pas de fournisseur_id (vieux devis ou texte libre), on cherche l'offre par son ID
                const singleOffer = fed.fournisseur_offres?.find(o => o.id === fed.offre_choisie_id);
                if (singleOffer) {
                    displayAmount = singleOffer.montant;
                }
            }
        }

        return {
            id: fed.id,
            code: fed.code,
            date: fed.date ? new Date(fed.date).toLocaleDateString('fr-FR') : '-',
            demandeur: fed.demandeur || fed.requester?.name || '-',
            department: fed.department || '-',
            estimated_total: formatAmount(Number(displayAmount)),
            status: fed.status,
            created_at: new Date(fed.created_at).toLocaleDateString('fr-FR'),
        };
    })
);
</script>

<template>
    <Head title="Validations DAF" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-end justify-between gap-4 border-b border-gray-200 pb-4">
                <div class="space-y-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Validations DAF</h1>
                        <p class="text-sm text-gray-500">Gérez les validations standards et les transferts budgétaires.</p>
                    </div>

                    <div class="flex gap-4">
                        <button
                            @click="switchTab('validation')"
                            class="pb-2 text-sm font-medium transition-colors border-b-2"
                            :class="props.activeTab !== 'reclassement' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        >
                            Validations FED
                        </button>
                        <button
                            @click="switchTab('reclassement')"
                            class="pb-2 text-sm font-medium transition-colors border-b-2 inline-flex items-center gap-2"
                            :class="props.activeTab === 'reclassement' ? 'border-amber-600 text-amber-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        >
                            Reclassements budgétaires
                        </button>
                    </div>
                </div>

                <div v-if="props.activeTab !== 'reclassement'" class="w-full max-w-xs">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Statut</label>
                    <select
                        :value="props.selectedStatus || ''"
                        class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900"
                        @change="updateStatusFilter(($event.target as HTMLSelectElement).value)"
                    >
                        <option value="">Tous</option>
                        <option value="cg_treated">Attente DAF (Validé CG)</option>
                        <option value="daf_approved">Validée DAF</option>
                        <option value="daf_rejected">Rejetée DAF</option>
                    </select>
                </div>
            </div>
            <DataTable
                :headers="columns"
                :items="tableData"
                :current-page="currentPage"
                :items-per-page="perPage"
                :total-items="totalItems"
                :show-select="false"
                @page-change="handlePageChange"
                @items-per-page-change="handleItemsPerPageChange"
            >
                <template #item.status="{ item }">
                    <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', statusBadge(item.status)]">
                        {{ statusLabel(item.status) }}
                    </span>
                </template>
                <template #item.actions="{ item }">
                    <Link
                        :href="`/feds/daf/${item.id}`"
                        class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                        title="Voir"
                    >
                        <Eye class="h-5 w-5" />
                    </Link>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
