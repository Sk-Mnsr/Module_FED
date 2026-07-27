<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { computed, ref, watch } from 'vue';
import { Eye, FileSpreadsheet, Search, RotateCcw } from 'lucide-vue-next';

interface Fed {
    id: number;
    code: string;
    date?: string | null;
    demandeur?: string | null;
    department?: string | null;
    motive?: string | null;
    priority?: string | null;
    status: string;
    offres_count?: number;
    fournisseurs_count?: number;
    last_saved_at?: string | null;
    requester?: { name: string } | null;
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
    filters?: {
        search?: string;
        status?: string | null;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Achats', href: '/feds/achats' },
    { title: 'Tableaux comparatifs', href: '#' },
];

const search = ref(props.filters?.search ?? '');
const statusFilter = ref(props.filters?.status ?? '');

watch(
    () => props.filters,
    (f) => {
        search.value = f?.search ?? '';
        statusFilter.value = f?.status ?? '';
    },
);

const currentPage = computed(() => props.feds.current_page || props.feds.meta?.current_page || 1);
const totalItems = computed(() => props.feds.total || props.feds.meta?.total || 0);
const perPage = computed(() => props.feds.per_page || props.feds.meta?.per_page || 10);

const statusLabel = (status: string) => {
    const labels: Record<string, string> = {
        n1_approved: 'En attente Achats',
        achats_needs_info: 'Complément demandé',
        achats_rejected: 'Rejetée',
        achats_approved: 'Transmise Facilities',
        expert_opinion_pending: 'En attente avis expert',
        expert_opinion_given: 'Avis expert reçu',
        facilities_needs_info: 'Facilities : Complément',
        facilities_rejected: 'Facilities : Rejetée',
        facilities_approved: 'En attente Budget (CG)',
        cg_treated: 'En attente DAF/DGA',
        waiting_daf_reclass_approval: 'Attente reclassement',
        daf_rejected: 'DAF : Rejetée',
        daf_approved: 'DGA : Approuvée',
        dga_rejected: 'DGA : Rejetée',
        bon_de_commande: 'Bon de commande',
    };
    return labels[status] ?? status;
};

const statusBadge = (status: string) => {
    const badges: Record<string, string> = {
        n1_approved: 'bg-blue-100 text-blue-700 border border-blue-200',
        achats_needs_info: 'bg-orange-100 text-orange-700 border border-orange-200',
        achats_rejected: 'bg-red-100 text-red-700 border border-red-200',
        achats_approved: 'bg-green-100 text-green-700 border border-green-200',
        expert_opinion_pending: 'bg-purple-100 text-purple-700 border border-purple-200',
        expert_opinion_given: 'bg-purple-50 text-purple-600 border border-purple-100',
        facilities_approved: 'bg-blue-50 text-blue-700 border border-blue-200',
        cg_treated: 'bg-indigo-50 text-indigo-700 border border-indigo-200',
        bon_de_commande: 'bg-cyan-100 text-cyan-800 border border-cyan-200',
    };
    return badges[status] ?? 'bg-gray-100 text-gray-700 border border-gray-200';
};

const canEditCotation = (status: string) =>
    ['n1_approved', 'achats_needs_info', 'facilities_needs_info'].includes(status);

const applyFilters = () => {
    router.get(
        '/achats/tableaux-comparatifs',
        {
            search: search.value || undefined,
            status: statusFilter.value || undefined,
            per_page: perPage.value,
            page: 1,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

const resetFilters = () => {
    search.value = '';
    statusFilter.value = '';
    router.get('/achats/tableaux-comparatifs', { per_page: perPage.value }, { preserveScroll: true });
};

const handlePageChange = (page: number) => {
    router.get(
        '/achats/tableaux-comparatifs',
        {
            search: search.value || undefined,
            status: statusFilter.value || undefined,
            per_page: perPage.value,
            page,
        },
        { preserveScroll: true, preserveState: true, only: ['feds'] },
    );
};

const handleItemsPerPageChange = (items: number) => {
    router.get(
        '/achats/tableaux-comparatifs',
        {
            search: search.value || undefined,
            status: statusFilter.value || undefined,
            per_page: items,
            page: 1,
        },
        { preserveScroll: true },
    );
};

const columns: Column[] = [
    { key: 'code', title: 'N° FED' },
    { key: 'demandeur', title: 'Demandeur' },
    { key: 'department', title: 'Département' },
    { key: 'motive', title: 'Motif' },
    { key: 'fournisseurs_count', title: 'Fournisseurs' },
    { key: 'last_saved_at', title: 'Enregistré le' },
    { key: 'status', title: 'Statut' },
    { key: 'actions', title: 'Actions' },
];

const tableData = computed(() =>
    props.feds.data.map((fed) => ({
        id: fed.id,
        code: fed.code,
        demandeur: fed.demandeur || fed.requester?.name || '—',
        department: fed.department || '—',
        motive: fed.motive && fed.motive.length > 50 ? fed.motive.substring(0, 50) + '…' : fed.motive || '—',
        fournisseurs_count: fed.fournisseurs_count ?? 0,
        offres_count: fed.offres_count ?? 0,
        last_saved_at: fed.last_saved_at
            ? new Date(fed.last_saved_at).toLocaleDateString('fr-FR', {
                  day: '2-digit',
                  month: '2-digit',
                  year: 'numeric',
                  hour: '2-digit',
                  minute: '2-digit',
              })
            : '—',
        status: fed.status,
    })),
);

const hasFilters = computed(() => !!(search.value.trim() || statusFilter.value));
</script>

<template>
    <Head title="Tableaux comparatifs" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-0 flex-1 flex-col gap-4 p-4 lg:p-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Achats & consultations
                    </p>
                    <h1 class="text-2xl font-semibold tracking-tight text-foreground lg:text-3xl">
                        Tableaux comparatifs
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Cotations enregistrées —
                        <span class="font-medium text-foreground">{{ totalItems }}</span>
                        tableau{{ totalItems > 1 ? 'x' : '' }}
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-3 rounded-xl border border-border bg-card p-3 shadow-sm sm:flex-row sm:items-center sm:p-4">
                <div class="relative min-w-0 flex-1">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="search"
                        placeholder="Rechercher par FED, demandeur, motif…"
                        class="h-10 pl-10"
                        @keyup.enter="applyFilters"
                    />
                </div>
                <select
                    v-model="statusFilter"
                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-56"
                    @change="applyFilters"
                >
                    <option value="">Tous les statuts</option>
                    <option value="n1_approved">En attente Achats</option>
                    <option value="achats_needs_info">Complément demandé</option>
                    <option value="achats_approved">Transmise Facilities</option>
                    <option value="expert_opinion_pending">Avis expert</option>
                    <option value="facilities_approved">Validée Facilities</option>
                    <option value="bon_de_commande">Bon de commande</option>
                </select>
                <div class="flex gap-2">
                    <Button type="button" variant="outline" class="h-10" @click="applyFilters">
                        Filtrer
                    </Button>
                    <Button
                        v-if="hasFilters"
                        type="button"
                        variant="ghost"
                        class="h-10"
                        @click="resetFilters"
                    >
                        <RotateCcw class="mr-1.5 size-4" />
                        Réinitialiser
                    </Button>
                </div>
            </div>

            <div
                v-if="totalItems === 0"
                class="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed border-border bg-muted/20 px-6 py-16 text-center"
            >
                <FileSpreadsheet class="mb-3 size-10 text-muted-foreground/60" />
                <h2 class="text-base font-semibold text-foreground">Aucun tableau enregistré</h2>
                <p class="mt-1 max-w-md text-sm text-muted-foreground">
                    <template v-if="hasFilters">
                        Aucun résultat pour ces critères.
                    </template>
                    <template v-else>
                        Les tableaux apparaissent ici dès qu’une cotation fournisseur est enregistrée sur une FED.
                    </template>
                </p>
                <div class="mt-4 flex gap-2">
                    <Button v-if="hasFilters" type="button" variant="outline" @click="resetFilters">
                        Réinitialiser
                    </Button>
                    <Button as-child>
                        <Link href="/feds/achats">Voir les demandes en cours</Link>
                    </Button>
                </div>
            </div>

            <DataTable
                v-else
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
                    <span class="font-mono text-sm font-semibold text-foreground">{{ item.code }}</span>
                </template>

                <template #item.fournisseurs_count="{ item }">
                    <span class="tabular-nums text-sm text-foreground">
                        {{ item.fournisseurs_count }}
                        <span class="text-muted-foreground">
                            fournisseur{{ item.fournisseurs_count > 1 ? 's' : '' }}
                        </span>
                    </span>
                </template>

                <template #item.status="{ item }">
                    <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadge(item.status)]">
                        {{ statusLabel(item.status) }}
                    </span>
                </template>

                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <Link
                            :href="`/feds/achats/${item.id}`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            title="Voir la demande"
                        >
                            <Eye class="size-4" />
                        </Link>
                        <Link
                            :href="`/feds/achats/${item.id}/cotation`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-blue-600 transition-colors hover:bg-blue-50 hover:text-blue-700"
                            :title="canEditCotation(item.status) ? 'Modifier le tableau' : 'Consulter le tableau'"
                        >
                            <FileSpreadsheet class="size-4" />
                        </Link>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
