<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { computed } from 'vue';
import { Eye, Landmark } from 'lucide-vue-next';

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

const selectClass =
    'flex h-10 w-full max-w-xs rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';

const statusLabel = (status: string) => {
    const labels: Record<string, string> = {
        facilities_approved: 'En attente DAF',
        daf_approved: 'Validée DAF → DGA',
        daf_rejected: 'Rejetée DAF',
        dga_rejected: 'Rejetée DGA',
        waiting_daf_reclass_approval: 'Attente Reclassement',
        cg_treated: 'Attente DAF (Validé CG)',
        expert_opinion_pending: 'Avis Expert en attente',
        expert_opinion_given: 'Avis Expert donné',
        bon_de_commande: 'Bon de commande',
    };
    return labels[status] ?? status;
};

const statusBadge = (status: string) => {
    const badges: Record<string, string> = {
        facilities_approved: 'bg-sky-100 text-sky-800 ring-1 ring-sky-200/80',
        daf_approved: 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200/80',
        daf_rejected: 'bg-red-100 text-red-800 ring-1 ring-red-200/80',
        dga_rejected: 'bg-red-100 text-red-800 ring-1 ring-red-200/80',
        waiting_daf_reclass_approval: 'bg-amber-100 text-amber-800 ring-1 ring-amber-200/80',
        cg_treated: 'bg-cyan-100 text-cyan-800 ring-1 ring-cyan-200/80 font-bold',
        expert_opinion_pending: 'bg-primary/10 text-primary ring-1 ring-primary/20',
        expert_opinion_given: 'bg-primary/10 text-primary ring-1 ring-primary/30',
        bon_de_commande: 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200/80',
    };
    return badges[status] ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200/80';
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
        <div class="flex flex-col gap-6 p-4 sm:p-6">
            <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                <div
                    class="flex flex-wrap items-start justify-between gap-4 border-b border-border/80 bg-gradient-to-r from-primary/5 via-card to-transparent px-5 py-5 sm:px-6 dark:from-primary/10"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm"
                        >
                            <Landmark class="size-5" />
                        </div>
                        <div>
                            <h1 class="text-xl font-semibold tracking-tight text-foreground">
                                Validations DAF
                            </h1>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Gérez les validations standards et les transferts budgétaires —
                                <span class="font-semibold text-primary">{{ totalItems }}</span>
                                au total
                            </p>

                            <div class="mt-4 flex gap-4">
                                <button
                                    type="button"
                                    @click="switchTab('validation')"
                                    class="pb-2 text-sm font-medium transition-colors border-b-2"
                                    :class="
                                        props.activeTab !== 'reclassement'
                                            ? 'border-primary text-primary'
                                            : 'border-transparent text-muted-foreground hover:text-foreground'
                                    "
                                >
                                    Validations FED
                                </button>
                                <button
                                    type="button"
                                    @click="switchTab('reclassement')"
                                    class="pb-2 text-sm font-medium transition-colors border-b-2 inline-flex items-center gap-2"
                                    :class="
                                        props.activeTab === 'reclassement'
                                            ? 'border-amber-600 text-amber-700'
                                            : 'border-transparent text-muted-foreground hover:text-foreground'
                                    "
                                >
                                    Reclassements budgétaires
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="props.activeTab !== 'reclassement'">
                        <label class="mb-1.5 block text-xs font-medium text-muted-foreground">
                            Filtrer par statut
                        </label>
                        <select
                            :value="props.selectedStatus || ''"
                            :class="selectClass"
                            @change="updateStatusFilter(($event.target as HTMLSelectElement).value)"
                        >
                            <option value="">Tous (attente + déjà traitées)</option>
                            <option value="cg_treated">Attente DAF (Validé CG)</option>
                            <option value="daf_approved">Validée DAF → DGA</option>
                            <option value="daf_rejected">Rejetée DAF</option>
                            <option value="bon_de_commande">Bon de commande (validées)</option>
                            <option value="dga_rejected">Rejetée DGA</option>
                        </select>
                    </div>
                </div>

                <div class="p-4 sm:p-5">
                    <div
                        class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-card"
                    >
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
                            <template #item.code="{ item }">
                                <span class="font-semibold tabular-nums text-foreground">{{
                                    item.code
                                }}</span>
                            </template>

                            <template #item.estimated_total="{ item }">
                                <span class="tabular-nums">{{ item.estimated_total }}</span>
                            </template>

                            <template #item.status="{ item }">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="statusBadge(item.status)"
                                >
                                    {{ statusLabel(item.status) }}
                                </span>
                            </template>

                            <template #item.actions="{ item }">
                                <Link
                                    :href="`/feds/daf/${item.id}`"
                                    class="inline-flex size-8 items-center justify-center rounded-md text-slate-600 transition hover:bg-slate-100 hover:text-foreground dark:hover:bg-muted"
                                    title="Voir"
                                >
                                    <Eye class="size-4" />
                                </Link>
                            </template>
                        </DataTable>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
