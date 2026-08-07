<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { computed } from 'vue';
import { CheckCircle, Eye, UserCheck } from 'lucide-vue-next';

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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Validations N+1', href: '/feds/n1' }];

const currentPage = computed(() => props.feds.current_page || props.feds.meta?.current_page || 1);
const totalItems = computed(() => props.feds.total || props.feds.meta?.total || 0);
const perPage = computed(() => props.feds.per_page || props.feds.meta?.per_page || 10);

const selectClass =
    'flex h-10 w-52 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';

const countPending = computed(
    () => props.feds.data.filter((f) => f.status === 'pending_validation').length,
);
const countNeedsInfo = computed(
    () => props.feds.data.filter((f) => f.status === 'n1_needs_info').length,
);
const countApproved = computed(
    () => props.feds.data.filter((f) => f.status === 'n1_approved').length,
);
const countRejected = computed(
    () => props.feds.data.filter((f) => f.status === 'n1_rejected').length,
);

const statusLabel = (status: string) => {
    switch (status) {
        case 'pending_validation':
            return 'En attente';
        case 'n1_needs_info':
            return 'Complément demandé';
        case 'n1_rejected':
            return 'Rejetée';
        case 'n1_approved':
            return 'Validée';
        default:
            return status;
    }
};

const statusBadge = (status: string) => {
    switch (status) {
        case 'pending_validation':
            return 'bg-sky-100 text-sky-800 ring-1 ring-sky-200/80';
        case 'n1_needs_info':
            return 'bg-amber-100 text-amber-800 ring-1 ring-amber-200/80';
        case 'n1_rejected':
            return 'bg-red-100 text-red-800 ring-1 ring-red-200/80';
        case 'n1_approved':
            return 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200/80';
        default:
            return 'bg-slate-100 text-slate-700 ring-1 ring-slate-200/80';
    }
};

const priorityLabel = (priority?: string | null) => {
    switch (priority) {
        case 'low':
            return 'Faible';
        case 'normal':
            return 'Normal';
        case 'high':
            return 'Haute';
        case 'urgent':
            return 'Urgente';
        default:
            return '—';
    }
};

const priorityBadge = (priority?: string | null) => {
    switch (priority) {
        case 'urgent':
            return 'bg-red-100 text-red-800 ring-1 ring-red-200/80';
        case 'high':
            return 'bg-orange-100 text-orange-800 ring-1 ring-orange-200/80';
        case 'normal':
            return 'bg-sky-50 text-sky-700 ring-1 ring-sky-200/80';
        case 'low':
            return 'bg-slate-100 text-slate-600 ring-1 ring-slate-200/80';
        default:
            return 'bg-slate-100 text-slate-600 ring-1 ring-slate-200/80';
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
    return props.feds.data.map((fed) => ({
        id: fed.id,
        code: fed.code,
        date: fed.date ? new Date(fed.date).toLocaleDateString('fr-FR') : '—',
        demandeur: fed.demandeur || fed.requester?.name || '—',
        department: fed.department || '—',
        motive:
            fed.motive && fed.motive.length > 45
                ? fed.motive.substring(0, 45) + '…'
                : fed.motive || '—',
        priority: fed.priority,
        submitted_at: fed.submitted_at
            ? new Date(fed.submitted_at).toLocaleDateString('fr-FR')
            : '—',
        status: fed.status,
    }));
});
</script>

<template>
    <Head title="Validations N+1" />

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
                            <UserCheck class="size-5" />
                        </div>
                        <div>
                            <h1 class="text-xl font-semibold tracking-tight text-foreground">
                                Validations N+1
                            </h1>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Demandes en attente de votre validation —
                                <span class="font-semibold text-primary">{{ totalItems }}</span>
                                au total
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-muted-foreground">
                            Filtrer par statut
                        </label>
                        <select
                            :value="props.selectedStatus || ''"
                            :class="selectClass"
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

                <div class="grid gap-3 border-b border-border/80 p-5 sm:grid-cols-2 lg:grid-cols-4 sm:p-6">
                    <div
                        class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-center dark:border-sky-900 dark:bg-sky-950/30"
                    >
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-sky-700 dark:text-sky-300">
                            En attente
                        </p>
                        <p class="mt-1 text-2xl font-bold tabular-nums text-sky-800 dark:text-sky-200">
                            {{ countPending }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-center dark:border-amber-900 dark:bg-amber-950/30"
                    >
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-300">
                            Complément
                        </p>
                        <p class="mt-1 text-2xl font-bold tabular-nums text-amber-800 dark:text-amber-200">
                            {{ countNeedsInfo }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-center dark:border-emerald-900 dark:bg-emerald-950/30"
                    >
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">
                            Validées
                        </p>
                        <p class="mt-1 text-2xl font-bold tabular-nums text-emerald-800 dark:text-emerald-200">
                            {{ countApproved }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-red-200 bg-red-50 p-4 text-center dark:border-red-900 dark:bg-red-950/30"
                    >
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-red-700 dark:text-red-300">
                            Rejetées
                        </p>
                        <p class="mt-1 text-2xl font-bold tabular-nums text-red-800 dark:text-red-200">
                            {{ countRejected }}
                        </p>
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

                            <template #item.status="{ item }">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="statusBadge(item.status)"
                                >
                                    {{ statusLabel(item.status) }}
                                </span>
                            </template>

                            <template #item.priority="{ item }">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="priorityBadge(item.priority)"
                                >
                                    {{ priorityLabel(item.priority) }}
                                </span>
                            </template>

                            <template #item.actions="{ item }">
                                <div class="flex items-center gap-0.5">
                                    <Link
                                        :href="`/feds/n1/${item.id}`"
                                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-600 transition hover:bg-slate-100 hover:text-foreground dark:hover:bg-muted"
                                        title="Voir la demande"
                                    >
                                        <Eye class="size-4" />
                                    </Link>
                                    <Link
                                        v-if="item.status === 'pending_validation'"
                                        :href="`/feds/n1/${item.id}`"
                                        class="inline-flex size-8 items-center justify-center rounded-md text-emerald-600 transition hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-950/40"
                                        title="Valider cette demande"
                                    >
                                        <CheckCircle class="size-4" />
                                    </Link>
                                </div>
                            </template>
                        </DataTable>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
