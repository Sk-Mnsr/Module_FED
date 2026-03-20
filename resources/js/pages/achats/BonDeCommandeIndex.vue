<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { computed } from 'vue';
import { FileText } from 'lucide-vue-next';

interface FedRequester {
    name: string;
}

interface FedFournisseurOffre {
    fournisseur: string;
}

interface Fed {
    id: number;
    code: string;
    numero_bon_commande?: string | null;
    date_bon_commande?: string | null;
    date?: string | null;
    demandeur?: string | null;
    department?: string | null;
    estimated_total?: number | null;
    status: string;
    created_at: string;
    requester?: FedRequester | null;
    offre_choisie?: FedFournisseurOffre | null;
}

interface Props {
    feds: { data: Fed[]; links: any[]; meta?: any; total?: number; current_page?: number; per_page?: number; last_page?: number };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Bons de commande', href: '/bons-de-commande' }];

const currentPage = computed(() => props.feds.current_page || props.feds.meta?.current_page || 1);
const totalItems = computed(() => props.feds.total || props.feds.meta?.total || 0);
const perPage = computed(() => props.feds.per_page || props.feds.meta?.per_page || 10);

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

const columns: Column[] = [
    { key: 'numero_bon_commande', title: 'N° Bon de commande' },
    { key: 'code', title: 'N° FED' },
    { key: 'date_bon_commande', title: 'Date' },
    { key: 'demandeur', title: 'Demandeur' },
    { key: 'fournisseur', title: 'Fournisseur' },
    { key: 'estimated_total', title: 'Montant' },
    { key: 'actions', title: 'Actions' },
];

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

const tableData = computed(() =>
    props.feds.data.map(fed => ({
        id: fed.id,
        numero_bon_commande: fed.numero_bon_commande || '-',
        code: fed.code,
        date_bon_commande: fed.date_bon_commande ? new Date(fed.date_bon_commande).toLocaleDateString('fr-FR') : '-',
        demandeur: fed.demandeur || fed.requester?.name || '-',
        fournisseur: fed.offre_choisie?.fournisseur || '-',
        estimated_total: formatAmount(fed.estimated_total),
        status: fed.status,
    }))
);
</script>

<template>
    <Head title="Bons de commande" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Bons de commande</h1>
                <p class="text-sm text-gray-500">Liste des FED validées par le DGA, prêtes pour le bon de commande.</p>
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
                <template #item.actions="{ item }">
                    <Link
                        :href="`/bons-de-commande/${item.id}`"
                        class="inline-flex items-center justify-center rounded-md p-2 text-blue-600 hover:bg-blue-50 hover:text-blue-700 transition-colors"
                        title="Voir le bon de commande"
                    >
                        <FileText class="h-5 w-5" />
                    </Link>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
