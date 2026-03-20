<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { computed } from 'vue';
import { Eye, CheckCircle } from 'lucide-vue-next';

interface FedRequester {
    name: string;
}

interface Fed {
    id: number;
    code: string;
    date?: string | null;
    demandeur?: string | null;
    department?: string | null;
    motive?: string | null;
    estimated_total?: number | null;
    priority?: string | null;
    status: string;
    created_at: string;
    submitted_at?: string | null;
    requester?: FedRequester | null;
}

interface Props {
    feds: { data: Fed[]; links: any[]; meta?: any; total?: number; current_page?: number; per_page?: number; last_page?: number };
    selectedStatus?: string | null;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Validations Facilities', href: '/feds/facilities' }];

const currentPage = computed(() => props.feds.current_page || props.feds.meta?.current_page || 1);
const totalItems = computed(() => props.feds.total || props.feds.meta?.total || 0);
const perPage = computed(() => props.feds.per_page || props.feds.meta?.per_page || 10);

const statusLabel = (status: string) => {
    const labels: Record<string, string> = {
        achats_approved: 'En attente',
        facilities_needs_info: 'Complément',
        facilities_rejected: 'Rejetée',
        facilities_approved: 'Validée',
    };
    return labels[status] ?? status;
};

const statusBadge = (status: string) => {
    const badges: Record<string, string> = {
        achats_approved: 'bg-blue-100 text-blue-700 border border-blue-200',
        facilities_needs_info: 'bg-orange-100 text-orange-700 border border-orange-200',
        facilities_rejected: 'bg-red-100 text-red-700 border border-red-200',
        facilities_approved: 'bg-green-100 text-green-700 border border-green-200',
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
    return priority ? (badges[priority] ?? 'bg-gray-100 text-gray-600 border border-gray-200') : 'bg-gray-100 text-gray-600 border border-gray-200';
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
    router.visit(url.toString(), { preserveScroll: true });
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
    { key: 'date', title: 'Date' },
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
        demandeur: fed.demandeur || fed.requester?.name || '-',
        department: fed.department || '-',
        motive: fed.motive && fed.motive.length > 45 ? fed.motive.substring(0, 45) + '…' : fed.motive || '-',
        priority: fed.priority,
        submitted_at: fed.submitted_at ? new Date(fed.submitted_at).toLocaleDateString('fr-FR') : '-',
        status: fed.status,
    }))
);
</script>

<template>
    <Head title="Validations Facilities" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            
            <!-- Header -->
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Validations Facilities</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Liste des demandes à traiter par le responsable facilities —
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
                        <option value="achats_approved">En attente (Nouvelles)</option>
                        <option value="facilities_needs_info">Complément demandé</option>
                        <option value="facilities_approved">Validées</option>
                        <option value="facilities_rejected">Rejetées</option>
                    </select>
                </div>
            </div>

            <!-- Statistiques -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-center">
                    <p class="text-xs font-medium text-blue-600">En attente</p>
                    <p class="text-2xl font-bold text-blue-700">
                        {{ props.feds.data.filter(f => f.status === 'achats_approved').length }}
                    </p>
                </div>
                <div class="rounded-lg border border-orange-200 bg-orange-50 p-3 text-center">
                    <p class="text-xs font-medium text-orange-600">Complément</p>
                    <p class="text-2xl font-bold text-orange-700">
                        {{ props.feds.data.filter(f => f.status === 'facilities_needs_info').length }}
                    </p>
                </div>
                <div class="rounded-lg border border-green-200 bg-green-50 p-3 text-center">
                    <p class="text-xs font-medium text-green-600">Validées (page crt.)</p>
                    <p class="text-2xl font-bold text-green-700">
                        {{ props.feds.data.filter(f => f.status === 'facilities_approved').length }}
                    </p>
                </div>
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-center">
                    <p class="text-xs font-medium text-red-600">Rejetées (page crt.)</p>
                    <p class="text-2xl font-bold text-red-700">
                        {{ props.feds.data.filter(f => f.status === 'facilities_rejected').length }}
                    </p>
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
                <template #item.priority="{ item }">
                    <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', priorityBadge(item.priority)]">
                        {{ priorityLabel(item.priority) }}
                    </span>
                </template>
                <template #item.status="{ item }">
                    <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadge(item.status)]">
                        {{ statusLabel(item.status) }}
                    </span>
                </template>
                <template #item.actions="{ item }">
                    <div class="flex items-center gap-2">
                        <Link
                            :href="`/feds/facilities/${item.id}?section=demande`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Détail (Demande et Articles)"
                        >
                            <Eye class="h-5 w-5" />
                        </Link>
                        <Link
                            v-if="['achats_approved', 'facilities_needs_info'].includes(item.status)"
                            :href="`/feds/facilities/${item.id}?section=tableau`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-green-600 hover:bg-green-50 hover:text-green-700 transition-colors"
                            title="Validation (Tableau comparatif)"
                        >
                            <CheckCircle class="h-5 w-5" />
                        </Link>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
