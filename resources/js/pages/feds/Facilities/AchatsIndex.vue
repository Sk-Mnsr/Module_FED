<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { computed } from 'vue';
import { Eye, FileSpreadsheet, CheckCircle } from 'lucide-vue-next';

interface Fed {
    id: number;
    code: string;
    date?: string | null;
    demandeur?: string | null;
    department?: string | null;
    motive?: string | null;
    priority?: string | null;
    status: string;
    submitted_at?: string | null;
}

interface Props {
    feds: { data: Fed[]; links: any[]; meta?: any; total?: number; current_page?: number; per_page?: number; last_page?: number };
    selectedStatus?: string | null;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Demandes en cours', href: '/feds/achats' }];

const currentPage = computed(() => props.feds.current_page || props.feds.meta?.current_page || 1);
const totalItems = computed(() => props.feds.total || props.feds.meta?.total || 0);
const perPage = computed(() => props.feds.per_page || props.feds.meta?.per_page || 10);

const statusLabel = (status: string) => {
    const labels: Record<string, string> = {
        pending_validation: 'En attente N+1',
        n1_needs_info: 'N+1 : Complément',
        n1_rejected: 'N+1 : Rejetée',
        n1_approved: 'En attente Achats',
        achats_needs_info: 'Achats : Complément',
        achats_rejected: 'Achats : Rejetée',
        achats_approved: 'Transmise Facilities',
        expert_opinion_pending: 'En attente avis expert',
        expert_opinion_given: 'Avis expert reçu',
        facilities_needs_info: 'Facilities : Complément',
        facilities_rejected: 'Facilities : Rejetée',
        facilities_approved: 'En attente Budget (CG)',
        cg_treated: 'En attente DAF/DGA',
        waiting_daf_reclass_approval: 'Attente Validation Reclassement',
        daf_rejected: 'DAF : Rejetée',
        daf_approved: 'DGA : Approuvée',
        dga_rejected: 'DGA : Rejetée',
        bon_de_commande: 'Bon de Commande',
    };
    return labels[status] ?? status;
};

const statusBadge = (status: string) => {
    const badges: Record<string, string> = {
        pending_validation: 'bg-yellow-50 text-yellow-700 border border-yellow-200',
        n1_needs_info: 'bg-orange-50 text-orange-700 border border-orange-200',
        n1_rejected: 'bg-red-50 text-red-700 border border-red-200',
        n1_approved: 'bg-blue-100 text-blue-700 border border-blue-200',
        achats_needs_info: 'bg-orange-100 text-orange-700 border border-orange-200',
        achats_rejected: 'bg-red-100 text-red-700 border border-red-200',
        achats_approved: 'bg-green-100 text-green-700 border border-green-200',
        expert_opinion_pending: 'bg-purple-100 text-purple-700 border border-purple-200',
        expert_opinion_given: 'bg-purple-50 text-purple-600 border border-purple-100',
        facilities_needs_info: 'bg-orange-50 text-orange-700 border border-orange-200',
        facilities_rejected: 'bg-red-100 text-red-700 border border-red-200',
        facilities_approved: 'bg-blue-50 text-blue-700 border border-blue-200',
        cg_treated: 'bg-indigo-50 text-indigo-700 border border-indigo-200',
        waiting_daf_reclass_approval: 'bg-yellow-100 text-yellow-800 border-yellow-200',
        daf_rejected: 'bg-red-50 text-red-700 border border-red-200',
        daf_approved: 'bg-green-150 text-green-800 border-green-300',
        dga_rejected: 'bg-red-150 text-red-800 border-red-300',
        bon_de_commande: 'bg-cyan-100 text-cyan-800 border border-cyan-200',
    };
    return badges[status] ?? 'bg-gray-100 text-gray-700 border border-gray-200';
};

const priorityLabel = (priority?: string | null) => {
    const labels: Record<string, string> = { low: 'Faible', normal: 'Normal', high: 'Haute', urgent: 'Urgente' };
    return priority ? (labels[priority] ?? priority) : '—';
};

const priorityBadge = (priority?: string | null) => {
    const badges: Record<string, string> = {
        urgent: 'bg-red-100 text-red-700 border border-red-200',
        high: 'bg-orange-100 text-orange-700 border border-orange-200',
        normal: 'bg-blue-50 text-blue-600 border border-blue-200',
        low: 'bg-gray-100 text-gray-600 border border-gray-200',
    };
    return priority ? (badges[priority] ?? 'bg-gray-100 text-gray-600') : 'bg-gray-100 text-gray-600';
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
    router.visit(url.toString(), { preserveScroll: false });
};

const columns: Column[] = [
    { key: 'code', title: 'N° FED' },
    { key: 'demandeur', title: 'Demandeur' },
    { key: 'department', title: 'Département' },
    { key: 'motive', title: 'Motif' },
    { key: 'priority', title: 'Priorité' },
    { key: 'submitted_at', title: 'Soumis le' },
    { key: 'status', title: 'Statut' },
    { key: 'actions', title: 'Actions' },
];

const tableData = computed(() =>
    props.feds.data.map(fed => ({
        id: fed.id,
        code: fed.code,
        date: fed.date ? new Date(fed.date).toLocaleDateString('fr-FR') : '-',
        demandeur: fed.demandeur || '-',
        department: fed.department || '-',
        motive: fed.motive && fed.motive.length > 45 ? fed.motive.substring(0, 45) + '…' : fed.motive || '-',
        priority: fed.priority,
        submitted_at: fed.submitted_at ? new Date(fed.submitted_at).toLocaleDateString('fr-FR') : '-',
        status: fed.status,
    }))
);
</script>

<template>
    <Head title="Demandes en cours – Achats" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">

            <!-- Header -->
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Demandes en cours</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        FED validées par le N+1 — en attente de cotation fournisseur —
                        <span class="font-semibold text-blue-600">{{ totalItems }}</span> au total
                    </p>
                </div>

                <!-- Filtre -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Filtrer par statut</label>
                    <select
                        :value="props.selectedStatus || ''"
                        class="flex h-9 w-52 rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 shadow-sm"
                        @change="updateStatusFilter(($event.target as HTMLSelectElement).value)"
                    >
                        <option value="">Tous les statuts</option>
                        <optgroup label="Initial">
                            <option value="pending_validation">En attente N+1</option>
                            <option value="n1_approved">En attente Achats</option>
                        </optgroup>
                        <optgroup label="Achats">
                            <option value="achats_needs_info">Complément demandé</option>
                            <option value="achats_approved">Transmise Facilities</option>
                            <option value="achats_rejected">Rejetée</option>
                        </optgroup>
                        <optgroup label="Facilities & Expert">
                            <option value="expert_opinion_pending">En attente avis expert</option>
                            <option value="expert_opinion_given">Avis expert reçu</option>
                            <option value="facilities_approved">Validée Facilities</option>
                        </optgroup>
                        <optgroup label="Final">
                            <option value="cg_treated">Validée Budget</option>
                            <option value="daf_approved">Validée DAF/DGA</option>
                            <option value="bon_de_commande">Bon de Commande</option>
                        </optgroup>
                    </select>
                </div>
            </div>

            <!-- Statistiques -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-center">
                    <p class="text-xs font-medium text-blue-600">En attente</p>
                    <p class="text-2xl font-bold text-blue-700">
                        {{ props.feds.data.filter(f => f.status === 'n1_approved').length }}
                    </p>
                </div>
                <div class="rounded-lg border border-orange-200 bg-orange-50 p-3 text-center">
                    <p class="text-xs font-medium text-orange-600">Complément</p>
                    <p class="text-2xl font-bold text-orange-700">
                        {{ props.feds.data.filter(f => f.status === 'achats_needs_info').length }}
                    </p>
                </div>
                <div class="rounded-lg border border-green-200 bg-green-50 p-3 text-center">
                    <p class="text-xs font-medium text-green-600">Transmises</p>
                    <p class="text-2xl font-bold text-green-700">
                        {{ props.feds.data.filter(f => f.status === 'achats_approved').length }}
                    </p>
                </div>
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-center">
                    <p class="text-xs font-medium text-red-600">Rejetées</p>
                    <p class="text-2xl font-bold text-red-700">
                        {{ props.feds.data.filter(f => f.status === 'achats_rejected').length }}
                    </p>
                </div>
            </div>

            <!-- Table -->
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
                    <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadge(item.status)]">
                        {{ statusLabel(item.status) }}
                    </span>
                </template>

                <template #item.priority="{ item }">
                    <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', priorityBadge(item.priority)]">
                        {{ priorityLabel(item.priority) }}
                    </span>
                </template>

                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <Link
                            :href="`/feds/achats/${item.id}`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Voir la demande"
                        >
                            <Eye class="h-5 w-5" />
                        </Link>
                        <Link
                            v-if="['n1_approved', 'achats_needs_info'].includes(item.status)"
                            :href="`/feds/achats/${item.id}/cotation`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-blue-600 hover:bg-blue-50 hover:text-blue-700 transition-colors"
                            title="Tableau comparatif / Cotation"
                        >
                            <FileSpreadsheet class="h-5 w-5" />
                        </Link>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
