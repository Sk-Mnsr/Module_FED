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
    priority?: string | null;
    status: string;
    submitted_at?: string | null;
    requester?: FedRequester | null;
}

interface Props {
    feds: {
        data: Fed[];
        links: any[];
        meta?: any;
        total?: number;
        current_page?: number;
        per_page?: number;
        last_page?: number;
    };
    selectedStatus?: string | null;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Validations N+1', href: '/feds/n1' },
];

const currentPage = computed(() => props.feds.current_page || props.feds.meta?.current_page || 1);
const totalItems = computed(() => props.feds.total || props.feds.meta?.total || 0);
const perPage = computed(() => props.feds.per_page || props.feds.meta?.per_page || 10);

const statusLabel = (status: string) => {
    switch (status) {
        case 'pending_validation': return 'En attente';
        case 'n1_needs_info': return 'Complément demandé';
        case 'n1_rejected': return 'Rejetée';
        case 'n1_approved': return 'Validée';
        default: return status;
    }
};

const statusBadge = (status: string) => {
    switch (status) {
        case 'pending_validation': return 'bg-blue-100 text-blue-700 border border-blue-200';
        case 'n1_needs_info': return 'bg-orange-100 text-orange-700 border border-orange-200';
        case 'n1_rejected': return 'bg-red-100 text-red-700 border border-red-200';
        case 'n1_approved': return 'bg-green-100 text-green-700 border border-green-200';
        default: return 'bg-gray-100 text-gray-700 border border-gray-200';
    }
};

const priorityLabel = (priority?: string | null) => {
    switch (priority) {
        case 'low': return 'Faible';
        case 'normal': return 'Normal';
        case 'high': return 'Haute';
        case 'urgent': return 'Urgente';
        default: return '-';
    }
};

const priorityBadge = (priority?: string | null) => {
    switch (priority) {
        case 'urgent': return 'bg-red-100 text-red-700 border border-red-200';
        case 'high': return 'bg-orange-100 text-orange-700 border border-orange-200';
        case 'normal': return 'bg-blue-50 text-blue-600 border border-blue-200';
        case 'low': return 'bg-gray-100 text-gray-600 border border-gray-200';
        default: return 'bg-gray-100 text-gray-600 border border-gray-200';
    }
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
    if (value) {
        url.searchParams.set('status', value);
    } else {
        url.searchParams.delete('status');
    }
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

const tableData = computed(() => {
    return props.feds.data.map(fed => ({
        id: fed.id,
        code: fed.code,
        date: fed.date ? new Date(fed.date).toLocaleDateString('fr-FR') : '-',
        demandeur: fed.demandeur || fed.requester?.name || '-',
        department: fed.department || '-',
        motive: fed.motive && fed.motive.length > 45 ? fed.motive.substring(0, 45) + '…' : fed.motive || '-',
        priority: fed.priority,
        submitted_at: fed.submitted_at ? new Date(fed.submitted_at).toLocaleDateString('fr-FR') : '-',
        status: fed.status,
    }));
});
</script>

<template>
    <Head title="Validations N+1" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Validations N+1</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Demandes de dépense en attente de votre validation —
                        <span class="font-semibold text-blue-600">{{ totalItems }}</span> au total
                    </p>
                </div>

                <!-- Filtres -->
                <div class="flex items-end gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Filtrer par statut</label>
                        <select
                            :value="props.selectedStatus || ''"
                            class="flex h-9 w-52 rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 shadow-sm"
                            @change="updateStatusFilter(($event.target as HTMLSelectElement).value)"
                        >
                            <option value="">Tous les statuts</option>
                            <option value="pending_validation">En attente de validation</option>
                            <option value="n1_needs_info">Complément demandé</option>
                            <option value="n1_approved">Validée</option>
                            <option value="n1_rejected">Rejetée</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Statistiques rapides -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-center">
                    <p class="text-xs text-blue-600 font-medium">En attente</p>
                    <p class="text-2xl font-bold text-blue-700">
                        {{ props.feds.data.filter(f => f.status === 'pending_validation').length }}
                    </p>
                </div>
                <div class="rounded-lg border border-orange-200 bg-orange-50 p-3 text-center">
                    <p class="text-xs text-orange-600 font-medium">Complément</p>
                    <p class="text-2xl font-bold text-orange-700">
                        {{ props.feds.data.filter(f => f.status === 'n1_needs_info').length }}
                    </p>
                </div>
                <div class="rounded-lg border border-green-200 bg-green-50 p-3 text-center">
                    <p class="text-xs text-green-600 font-medium">Validées</p>
                    <p class="text-2xl font-bold text-green-700">
                        {{ props.feds.data.filter(f => f.status === 'n1_approved').length }}
                    </p>
                </div>
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-center">
                    <p class="text-xs text-red-600 font-medium">Rejetées</p>
                    <p class="text-2xl font-bold text-red-700">
                        {{ props.feds.data.filter(f => f.status === 'n1_rejected').length }}
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
                            :href="`/feds/n1/${item.id}`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            title="Voir la demande"
                        >
                            <Eye class="h-5 w-5" />
                        </Link>
                        <Link
                            v-if="item.status === 'pending_validation'"
                            :href="`/feds/n1/${item.id}`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-green-600 hover:bg-green-50 hover:text-green-700 transition-colors"
                            title="Valider cette demande"
                        >
                            <CheckCircle class="h-5 w-5" />
                        </Link>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
