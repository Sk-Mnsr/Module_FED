<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { computed } from 'vue';
import { Eye, FileText, ShieldCheck } from 'lucide-vue-next';

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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Validations DGA', href: '/feds/dga' }];

const currentPage = computed(() => props.feds.current_page || props.feds.meta?.current_page || 1);
const totalItems = computed(() => props.feds.total || props.feds.meta?.total || 0);
const perPage = computed(() => props.feds.per_page || props.feds.meta?.per_page || 10);

const selectClass =
    'flex h-10 w-52 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';

const countPending = computed(
    () => props.feds.data.filter((f) => f.status === 'daf_approved').length,
);
const countBonDeCommande = computed(
    () => props.feds.data.filter((f) => f.status === 'bon_de_commande').length,
);
const countRejected = computed(
    () => props.feds.data.filter((f) => f.status === 'dga_rejected').length,
);

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
        daf_approved: 'bg-sky-100 text-sky-800 ring-1 ring-sky-200/80',
        dga_rejected: 'bg-red-100 text-red-800 ring-1 ring-red-200/80',
        bon_de_commande: 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200/80',
    };
    return badges[status] ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200/80';
};

const formatAmount = (value?: number | null) => {
    if (value === null || value === undefined) return '—';
    const formattedNum = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Number(value) || 0);
    return `${formattedNum} FCFA`;
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
    props.feds.data.map((fed) => ({
        id: fed.id,
        code: fed.code,
        date: fed.date ? new Date(fed.date).toLocaleDateString('fr-FR') : '—',
        demandeur: fed.demandeur || fed.requester?.name || '—',
        department: fed.department || '—',
        estimated_total: formatAmount(fed.estimated_total),
        status: fed.status,
        created_at: new Date(fed.created_at).toLocaleDateString('fr-FR'),
    })),
);
</script>

<template>
    <Head title="Validations DGA" />

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
                            <ShieldCheck class="size-5" />
                        </div>
                        <div>
                            <h1 class="text-xl font-semibold tracking-tight text-foreground">
                                Validations DGA
                            </h1>
                            <p class="mt-1 text-sm text-muted-foreground">
                                FED validées DAF, en attente de validation DGA —
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
                            <option value="daf_approved">En attente DGA</option>
                            <option value="bon_de_commande">Bon de commande</option>
                            <option value="dga_rejected">Rejetée DGA</option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-3 border-b border-border/80 p-5 sm:grid-cols-3 sm:p-6">
                    <div
                        class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-center dark:border-sky-900 dark:bg-sky-950/30"
                    >
                        <p
                            class="text-[11px] font-semibold uppercase tracking-wider text-sky-700 dark:text-sky-300"
                        >
                            En attente
                        </p>
                        <p class="mt-1 text-2xl font-bold tabular-nums text-sky-800 dark:text-sky-200">
                            {{ countPending }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-center dark:border-emerald-900 dark:bg-emerald-950/30"
                    >
                        <p
                            class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300"
                        >
                            Bon de commande
                        </p>
                        <p
                            class="mt-1 text-2xl font-bold tabular-nums text-emerald-800 dark:text-emerald-200"
                        >
                            {{ countBonDeCommande }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-red-200 bg-red-50 p-4 text-center dark:border-red-900 dark:bg-red-950/30"
                    >
                        <p
                            class="text-[11px] font-semibold uppercase tracking-wider text-red-700 dark:text-red-300"
                        >
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

                            <template #item.actions="{ item }">
                                <div class="flex items-center gap-0.5">
                                    <Link
                                        :href="`/feds/dga/${item.id}`"
                                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-600 transition hover:bg-slate-100 hover:text-foreground dark:hover:bg-muted"
                                        title="Voir"
                                    >
                                        <Eye class="size-4" />
                                    </Link>
                                    <Link
                                        v-if="item.status === 'bon_de_commande'"
                                        :href="`/bons-de-commande/${item.id}`"
                                        class="inline-flex size-8 items-center justify-center rounded-md text-sky-600 transition hover:bg-sky-50 hover:text-sky-700 dark:hover:bg-sky-950/40"
                                        title="Bon de commande"
                                    >
                                        <FileText class="size-4" />
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
