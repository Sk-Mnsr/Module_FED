<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import DataTable, { type Column } from '@/components/DataTable.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { computed, ref } from 'vue';
import { Edit, Eye, Plus } from 'lucide-vue-next';

interface Comite {
    id: number;
    nom: string;
    statut: string;
    appel_offre_id: number;
    appel_offre?: { reference?: string; objet?: string } | null;
    membres?: unknown[];
}

interface AppelOffreOption {
    id: number;
    reference: string;
    objet?: string | null;
    statut?: string | null;
}

interface Props {
    comites: {
        data: Comite[];
        links: unknown[];
        meta?: { current_page?: number; total?: number; per_page?: number };
        total?: number;
        current_page?: number;
        per_page?: number;
    };
    appelOffresSansComite?: AppelOffreOption[];
}

const props = withDefaults(defineProps<Props>(), {
    appelOffresSansComite: () => [],
});

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Comités', href: '/comites' }];

const showCreateDialog = ref(false);
const selectedAppelOffreId = ref<number | ''>('');

const currentPage = computed(() => props.comites.current_page || props.comites.meta?.current_page || 1);
const totalItems = computed(() => props.comites.total || props.comites.meta?.total || 0);
const perPage = computed(() => props.comites.per_page || props.comites.meta?.per_page || 10);

const canCreate = computed(() => (props.appelOffresSansComite?.length ?? 0) > 0);

const handlePageChange = (page: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set('page', page.toString());
    url.searchParams.set('per_page', perPage.value.toString());
    router.get(url.toString(), {}, { preserveScroll: true, preserveState: true, only: ['comites'] });
};

const handleItemsPerPageChange = (items: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', items.toString());
    url.searchParams.set('page', '1');
    router.visit(url.toString(), { preserveScroll: true });
};

const openCreate = () => {
    selectedAppelOffreId.value = '';
    showCreateDialog.value = true;
};

const goToCreate = () => {
    if (!selectedAppelOffreId.value) return;
    router.visit(`/appel-offres/${selectedAppelOffreId.value}/comites/create`);
};

const columns: Column[] = [
    { key: 'nom', title: 'Nom du comité' },
    { key: 'appelOffre', title: "Appel d'Offres lié" },
    { key: 'membres', title: 'Membres' },
    { key: 'statut', title: 'Statut' },
    { key: 'actions', title: 'Actions' },
];

const tableData = computed(() =>
    props.comites.data.map((comite) => ({
        id: comite.id,
        nom: comite.nom,
        appelOffre: comite.appel_offre?.reference || '—',
        membres: `${comite.membres?.length || 0} membre(s)`,
        statut: comite.statut,
        comite,
    })),
);
</script>

<template>
    <Head title="Comités d'évaluation" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4 lg:p-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Achats & consultations
                    </p>
                    <h1 class="text-2xl font-semibold tracking-tight text-foreground lg:text-3xl">
                        Comités d'évaluation
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ totalItems }} comité{{ totalItems > 1 ? 's' : '' }}
                    </p>
                </div>
                <Button type="button" :disabled="!canCreate" @click="openCreate">
                    <Plus class="mr-1.5 size-4" />
                    Nouveau comité
                </Button>
            </div>

            <p
                v-if="!canCreate"
                class="rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm text-muted-foreground"
            >
                Aucun appel d’offres disponible sans comité. Créez d’abord un AO, ou tous les AO ont déjà un comité.
            </p>

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
                        class="inline-flex rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-800"
                    >
                        {{ item.statut }}
                    </span>
                </template>
                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <Link
                            :href="`/appel-offres/${item.comite.appel_offre_id}`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            title="Voir l'Appel d'Offres"
                        >
                            <Eye class="size-4" />
                        </Link>
                        <Link
                            :href="`/comites/${item.id}/edit`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            title="Modifier"
                        >
                            <Edit class="size-4" />
                        </Link>
                    </div>
                </template>
            </DataTable>
        </div>

        <Dialog :open="showCreateDialog" @update:open="showCreateDialog = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Nouveau comité</DialogTitle>
                    <DialogDescription>
                        Choisissez l’appel d’offres pour lequel créer le comité d’évaluation.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-2 py-2">
                    <Label for="appel-offre">Appel d’offres</Label>
                    <select
                        id="appel-offre"
                        v-model="selectedAppelOffreId"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    >
                        <option value="" disabled>-- Sélectionner --</option>
                        <option
                            v-for="ao in appelOffresSansComite"
                            :key="ao.id"
                            :value="ao.id"
                        >
                            {{ ao.reference }} — {{ ao.objet || 'Sans objet' }}
                        </option>
                    </select>
                </div>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button type="button" variant="outline" @click="showCreateDialog = false">
                        Annuler
                    </Button>
                    <Button type="button" :disabled="!selectedAppelOffreId" @click="goToCreate">
                        Continuer
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
