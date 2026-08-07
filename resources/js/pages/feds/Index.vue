<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { computed } from 'vue';
import { ClipboardList, Eye, Pencil, Plus, Trash2 } from 'lucide-vue-next';

interface Fed {
    id: number;
    code: string;
    date?: string | null;
    demandeur?: string | null;
    motive?: string | null;
    status: string;
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
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Fiches de dépense',
        href: '/feds',
    },
];

const currentPage = computed(() => {
    return props.feds.current_page || props.feds.meta?.current_page || 1;
});
const totalItems = computed(() => {
    return props.feds.total || props.feds.meta?.total || 0;
});
const perPage = computed(() => {
    return props.feds.per_page || props.feds.meta?.per_page || 10;
});

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

const STATUS_LABELS: Record<string, string> = {
    pending_validation: 'En attente N+1',
    n1_needs_info: 'Complément demandé',
    n1_rejected: 'Rejetée N+1',
    n1_approved: 'Validée N+1',
    facilities_needs_info: 'Complément Facilities',
    facilities_approved: 'Approuvée Facilities',
    facilities_rejected: 'Rejetée Facilities',
    cg_treated: 'Traitée CG',
    daf_approved: 'Validée DAF',
    daf_rejected: 'Rejetée DAF',
    dga_approved: 'Validée DGA',
    dga_rejected: 'Rejetée DGA',
    waiting_daf_reclass_approval: 'Attente reclassement',
    expert_opinion_pending: 'Avis expert en attente',
    expert_opinion_given: 'Avis expert donné',
    bon_de_commande: 'Bon de commande',
};

const STATUS_BADGES: Record<string, string> = {
    pending_validation: 'bg-sky-100 text-sky-800 ring-1 ring-sky-200/80',
    n1_needs_info: 'bg-amber-100 text-amber-800 ring-1 ring-amber-200/80',
    n1_rejected: 'bg-red-100 text-red-800 ring-1 ring-red-200/80',
    n1_approved: 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200/80',
    facilities_needs_info: 'bg-amber-100 text-amber-800 ring-1 ring-amber-200/80',
    facilities_approved: 'bg-cyan-100 text-cyan-800 ring-1 ring-cyan-200/80',
    facilities_rejected: 'bg-red-100 text-red-800 ring-1 ring-red-200/80',
    cg_treated: 'bg-indigo-100 text-indigo-800 ring-1 ring-indigo-200/80',
    daf_approved: 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200/80',
    daf_rejected: 'bg-red-100 text-red-800 ring-1 ring-red-200/80',
    dga_approved: 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200/80',
    dga_rejected: 'bg-red-100 text-red-800 ring-1 ring-red-200/80',
    waiting_daf_reclass_approval: 'bg-amber-100 text-amber-800 ring-1 ring-amber-200/80',
    expert_opinion_pending: 'bg-primary/10 text-primary ring-1 ring-primary/20',
    expert_opinion_given: 'bg-primary/10 text-primary ring-1 ring-primary/25',
    bon_de_commande: 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200/80',
};

const statusLabel = (status: string) => STATUS_LABELS[status] ?? status;
const statusBadge = (status: string) =>
    STATUS_BADGES[status] ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200/80';

const columns: Column[] = [
    { key: 'code', title: 'N° FED' },
    { key: 'date', title: 'Date' },
    { key: 'demandeur', title: 'Demandeur' },
    { key: 'motive', title: 'Motif' },
    { key: 'status', title: 'Statut' },
    { key: 'actions', title: 'Actions' },
];

const tableData = computed(() => {
    return props.feds.data.map((fed) => ({
        id: fed.id,
        code: fed.code,
        date: fed.date ? new Date(fed.date).toLocaleDateString('fr-FR') : '—',
        demandeur: fed.demandeur || '—',
        motive:
            fed.motive && fed.motive.length > 50
                ? fed.motive.substring(0, 50) + '…'
                : fed.motive || '—',
        status: fed.status,
        fed,
    }));
});

const deleteFed = (id: number) => {
    if (confirm('Supprimer cette FED ?')) {
        router.delete(`/feds/${id}`);
    }
};
</script>

<template>
    <Head title="Mes FED" />

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
                            <ClipboardList class="size-5" />
                        </div>
                        <div>
                            <h1 class="text-xl font-semibold tracking-tight text-foreground">
                                Mes fiches de dépense
                            </h1>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Suivez vos demandes et créez une nouvelle fiche d’engagement.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm text-slate-700 dark:border-slate-700 dark:bg-muted/30 dark:text-foreground"
                        >
                            <span class="font-semibold tabular-nums">{{ totalItems }}</span>
                            <span>fiche(s)</span>
                        </div>
                        <Link href="/feds/create">
                            <Button class="bg-primary text-primary-foreground hover:bg-primary/90">
                                <Plus class="size-4" />
                                Nouvelle demande
                            </Button>
                        </Link>
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
                                    class="inline-flex max-w-[14rem] truncate rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="statusBadge(item.status)"
                                    :title="statusLabel(item.status)"
                                >
                                    {{ statusLabel(item.status) }}
                                </span>
                            </template>

                            <template #item.actions="{ item }">
                                <div class="flex items-center gap-0.5">
                                    <Link
                                        :href="`/feds/${item.id}`"
                                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-600 transition hover:bg-slate-100 hover:text-foreground dark:hover:bg-muted"
                                        title="Voir"
                                    >
                                        <Eye class="size-4" />
                                    </Link>
                                    <Link
                                        :href="`/feds/${item.id}/edit`"
                                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-600 transition hover:bg-slate-100 hover:text-foreground dark:hover:bg-muted"
                                        title="Modifier"
                                    >
                                        <Pencil class="size-4" />
                                    </Link>
                                    <button
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-md text-red-600 transition hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/40"
                                        title="Supprimer"
                                        @click="deleteFed(item.id)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </template>
                        </DataTable>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
