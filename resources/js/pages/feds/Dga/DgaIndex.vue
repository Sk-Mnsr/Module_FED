<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { computed } from 'vue';
import { Eye, FileText } from 'lucide-vue-next';

interface FedRequester {
    name: string;
}

interface Fed {
    id: number;
    code: string;
    date?: string | null;
    demandeur?: string | null;
    department?: string | null;
    estimated_total?: number | null;
    status: string;
    created_at: string;
    requester?: FedRequester | null;
}

interface Props {
    feds: { data: Fed[]; links: any[]; meta?: any; total?: number; current_page?: number; per_page?: number; last_page?: number };
    selectedStatus?: string | null;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Validations DGA', href: '/feds/dga' }];

const currentPage = computed(() => props.feds.current_page || props.feds.meta?.current_page || 1);
const totalItems = computed(() => props.feds.total || props.feds.meta?.total || 0);
const perPage = computed(() => props.feds.per_page || props.feds.meta?.per_page || 10);

const statusLabel = (status: string) => {
    const labels: Record<string, string> = {
        daf_approved: 'En attente DGA',
        dga_rejected: 'Rejetée DGA',
        bon_de_commande: 'Bon de commande',
    };
    return labels[status] ?? status;
};

const statusBadge = (status: string) => {
    const badges: Record<string, string> = {
        daf_approved: 'bg-blue-100 text-blue-700',
        dga_rejected: 'bg-red-100 text-red-700',
        bon_de_commande: 'bg-green-100 text-green-700',
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
    props.feds.data.map(fed => ({
        id: fed.id,
        code: fed.code,
        date: fed.date ? new Date(fed.date).toLocaleDateString('fr-FR') : '-',
        demandeur: fed.demandeur || fed.requester?.name || '-',
        department: fed.department || '-',
        estimated_total: formatAmount(fed.estimated_total),
        status: fed.status,
        created_at: new Date(fed.created_at).toLocaleDateString('fr-FR'),
    }))
);
</script>

<template>
    <Head title="Validations DGA" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Validations DGA</h1>
                    <p class="text-sm text-gray-500">Liste des FED validées par le DAF, en attente de validation DGA pour bon de commande.</p>
                </div>
                <div class="w-full max-w-xs">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Statut</label>
                    <select
                        :value="props.selectedStatus || ''"
                        class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900"
                        @change="updateStatusFilter(($event.target as HTMLSelectElement).value)"
                    >
                        <option value="">Tous</option>
                        <option value="daf_approved">En attente DGA</option>
                        <option value="bon_de_commande">Bon de commande</option>
                        <option value="dga_rejected">Rejetée DGA</option>
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
                        :href="`/feds/dga/${item.id}`"
                        class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                        title="Voir"
                    >
                        <Eye class="h-5 w-5" />
                    </Link>
                    <Link
                        v-if="item.status === 'bon_de_commande'"
                        :href="`/bons-de-commande/${item.id}`"
                        class="inline-flex items-center justify-center rounded-md p-2 text-blue-600 hover:bg-blue-50 hover:text-blue-700 transition-colors"
                        title="Bon de commande"
                    >
                        <FileText class="h-5 w-5" />
                    </Link>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
