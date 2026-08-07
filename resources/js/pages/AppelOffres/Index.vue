<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import DataTable, { type Column } from '@/components/DataTable.vue';
import { computed } from 'vue';
import { Eye, FileText, Pencil, Plus, Trash2 } from 'lucide-vue-next';

interface AppelOffre {
    id: number;
    reference: string;
    objet: string;
    date_lancement?: string | null;
    date_limite_soumission: string;
    statut: string;
}

interface Props {
    appelOffres: {
        data: AppelOffre[];
        links: any[];
        meta?: any;
        total?: number;
        current_page?: number;
        per_page?: number;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "Appels d'Offres",
        href: '/appel-offres',
    },
];

const currentPage = computed(() => {
    return props.appelOffres.current_page || props.appelOffres.meta?.current_page || 1;
});
const totalItems = computed(() => {
    return props.appelOffres.total || props.appelOffres.meta?.total || 0;
});
const perPage = computed(() => {
    return props.appelOffres.per_page || props.appelOffres.meta?.per_page || 10;
});

const handlePageChange = (page: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set('page', page.toString());
    url.searchParams.set('per_page', perPage.value.toString());
    router.get(url.toString(), {}, { preserveScroll: true, preserveState: true, only: ['appelOffres'] });
};

const handleItemsPerPageChange = (items: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', items.toString());
    url.searchParams.set('page', '1');
    router.visit(url.toString(), { preserveScroll: true });
};

const statusLabel = (status: string) => {
    switch (status) {
        case 'brouillon':
            return 'Brouillon';
        case 'publie':
            return 'Publié';
        case 'cloture':
            return 'Clôturé';
        case 'en_evaluation':
            return 'En évaluation';
        case 'attribue':
            return 'Attribué';
        default:
            return status;
    }
};

const statusBadge = (status: string) => {
    switch (status) {
        case 'brouillon':
            return 'bg-slate-100 text-slate-700 ring-1 ring-slate-200/80';
        case 'publie':
            return 'bg-primary/10 text-primary ring-1 ring-primary/20';
        case 'cloture':
            return 'bg-orange-100 text-orange-800 ring-1 ring-orange-200/80';
        case 'en_evaluation':
            return 'bg-amber-100 text-amber-800 ring-1 ring-amber-200/80';
        case 'attribue':
            return 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200/80';
        default:
            return 'bg-slate-100 text-slate-700 ring-1 ring-slate-200/80';
    }
};

const columns: Column[] = [
    { key: 'reference', title: 'Référence' },
    { key: 'objet', title: 'Objet' },
    { key: 'date_lancement', title: 'Lancement' },
    { key: 'date_limite_soumission', title: 'Limite' },
    { key: 'statut', title: 'Statut' },
    { key: 'actions', title: 'Actions' },
];

const tableData = computed(() => {
    return props.appelOffres.data.map((tender) => ({
        id: tender.id,
        reference: tender.reference,
        objet: tender.objet.length > 50 ? tender.objet.substring(0, 50) + '…' : tender.objet,
        date_lancement: tender.date_lancement
            ? new Date(tender.date_lancement).toLocaleDateString('fr-FR')
            : '—',
        date_limite_soumission: new Date(tender.date_limite_soumission).toLocaleDateString('fr-FR'),
        statut: tender.statut,
        tender,
    }));
});

const deleteTender = (id: number) => {
    if (confirm("Supprimer cet appel d'offres ?")) {
        router.delete(`/appel-offres/${id}`);
    }
};
</script>

<template>
    <Head title="Appels d'Offres" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-0 flex-1 flex-col gap-4 p-4 sm:p-6">
            <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                <div
                    class="border-b border-border/80 bg-gradient-to-r from-primary/5 via-card to-transparent px-5 py-5 sm:px-6 dark:from-primary/10"
                >
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm"
                            >
                                <FileText class="size-5" />
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-primary">
                                    Achats & consultations
                                </p>
                                <h1 class="text-xl font-semibold tracking-tight text-foreground">
                                    Appels d'Offres
                                </h1>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    TDR —
                                    <span class="font-semibold text-primary">{{ totalItems }}</span>
                                    appel{{ totalItems > 1 ? 's' : '' }}
                                </p>
                            </div>
                        </div>
                        <Button as-child>
                            <Link href="/appel-offres/create" class="inline-flex items-center gap-2">
                                <Plus class="size-4" />
                                Nouvel Appel
                            </Link>
                        </Button>
                    </div>
                </div>

                <div class="p-4 sm:p-5">
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
                        <template #item.statut="{ item }">
                            <span
                                :class="[
                                    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                                    statusBadge(item.statut),
                                ]"
                            >
                                {{ statusLabel(item.statut) }}
                            </span>
                        </template>

                        <template #item.actions="{ item }">
                            <div class="flex items-center gap-1">
                                <Link
                                    :href="`/appel-offres/${item.id}`"
                                    class="inline-flex items-center justify-center rounded-md p-2 text-slate-600 transition-colors hover:bg-primary/5 hover:text-primary"
                                    title="Voir"
                                >
                                    <Eye class="size-5" />
                                </Link>
                                <Link
                                    v-if="item.statut === 'brouillon'"
                                    :href="`/appel-offres/${item.id}/edit`"
                                    class="inline-flex items-center justify-center rounded-md p-2 text-slate-600 transition-colors hover:bg-primary/5 hover:text-primary"
                                    title="Modifier"
                                >
                                    <Pencil class="size-5" />
                                </Link>
                                <button
                                    v-if="item.statut === 'brouillon'"
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-md p-2 text-red-600 transition-colors hover:bg-red-50 hover:text-red-700"
                                    title="Supprimer"
                                    @click="deleteTender(item.id)"
                                >
                                    <Trash2 class="size-5" />
                                </button>
                            </div>
                        </template>
                    </DataTable>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
