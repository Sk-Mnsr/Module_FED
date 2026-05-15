<script setup lang="ts">
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Check, Download, Eye, History, Plus, RotateCcw } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Transferts', href: '/monetique/transferts/historique' },
    { title: 'Historique', href: '/monetique/transferts/historique' },
];

type TransferStatus = 'validé' | 'rejeté' | 'en attente' | 'annulé';

type TransferRow = {
    id?: number;
    initiateur: string;
    receptionniste: string;
    commentaire: string;
    date_transfert: string;
    statut: TransferStatus;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    per_page: number;
    total: number;
};

const props = withDefaults(
    defineProps<{
        transfers?: Paginated<TransferRow>;
    }>(),
    {
        transfers: () => ({
            data: [],
            current_page: 1,
            per_page: 15,
            total: 0,
        }),
    },
);

const initiateur = ref('');
const receptionniste = ref('');
const statut = ref<'' | TransferStatus>('');
const search = ref('');

const rows = computed(() => props.transfers?.data ?? []);

const initiateurs = computed(() => {
    const unique = Array.from(new Set(rows.value.map((r) => r.initiateur))).filter(Boolean);
    return unique.sort((a, b) => a.localeCompare(b));
});

const receptionnistes = computed(() => {
    const unique = Array.from(new Set(rows.value.map((r) => r.receptionniste))).filter(Boolean);
    return unique.sort((a, b) => a.localeCompare(b));
});

const filteredRows = computed(() => {
    const q = search.value.trim().toLowerCase();
    return rows.value.filter((r) => {
        const byInit = initiateur.value ? r.initiateur === initiateur.value : true;
        const byRec = receptionniste.value ? r.receptionniste === receptionniste.value : true;
        const byStatus = statut.value ? r.statut === statut.value : true;
        const bySearch = !q
            ? true
            : [r.initiateur, r.receptionniste, r.commentaire, r.date_transfert, r.statut].some((x) =>
                  x.toLowerCase().includes(q),
              );
        return byInit && byRec && byStatus && bySearch;
    });
});

const columns = [
    { key: 'initiateur', title: 'Initiateur' },
    { key: 'receptionniste', title: 'Receptionniste' },
    { key: 'commentaire', title: 'Commentaire' },
    { key: 'date_transfert', title: 'Date de transfert' },
    { key: 'statut', title: 'Statut' },
    { key: 'actions', title: 'Actions' },
];

const statusLabel = (s: TransferStatus) => {
    switch (s) {
        case 'validé':
            return 'Validé';
        case 'rejeté':
            return 'Rejeté';
        case 'en attente':
            return 'En attente';
        case 'annulé':
            return 'Annulé';
    }
};

const statusClass = (s: TransferStatus) => {
    switch (s) {
        case 'validé':
            return 'bg-emerald-50 text-emerald-700 border-emerald-100';
        case 'rejeté':
            return 'bg-rose-50 text-rose-700 border-rose-100';
        case 'en attente':
            return 'bg-amber-50 text-amber-700 border-amber-100';
        case 'annulé':
            return 'bg-slate-100 text-slate-600 border-slate-200';
    }
};

const goToNew = () => router.visit('/monetique/transferts/nouveau');
const reload = () => router.reload({ only: ['transfers'] });

const voirDetail = (row: TransferRow) => {
    if (!row.id) {
        return;
    }
    router.visit(`/monetique/transferts/${row.id}?from=historique`);
};
</script>

<template>
    <Head title="Monétique - Transferts - Historique" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center gap-3">
                <div class="p-3 bg-violet-100 text-violet-700 rounded-xl">
                    <History class="h-6 w-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Liste des transferts</h1>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
                <div>
                    <p class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Filtres</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <Label for="initiateur" class="text-xs font-medium text-gray-600">Initiateur</Label>
                        <select
                            id="initiateur"
                            v-model="initiateur"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900"
                        >
                            <option value="">-- Tous --</option>
                            <option v-for="i in initiateurs" :key="i" :value="i">{{ i }}</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <Label for="receptionniste" class="text-xs font-medium text-gray-600">Receptionniste</Label>
                        <select
                            id="receptionniste"
                            v-model="receptionniste"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900"
                        >
                            <option value="">-- Tous --</option>
                            <option v-for="r in receptionnistes" :key="r" :value="r">{{ r }}</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <Label for="statut" class="text-xs font-medium text-gray-600">Statut</Label>
                        <select
                            id="statut"
                            v-model="statut"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900"
                        >
                            <option value="">-- Tous --</option>
                            <option value="validé">Validé</option>
                            <option value="en attente">En attente</option>
                            <option value="rejeté">Rejeté</option>
                            <option value="annulé">Annulé</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-4 flex-wrap pt-2">
                    <div class="w-full max-w-sm">
                        <Input v-model="search" placeholder="Rechercher un transfert" class="border-gray-300" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Button variant="outline" class="bg-white">
                            <Download class="h-4 w-4 mr-2" />
                            Export
                        </Button>
                        <Button class="bg-violet-600 hover:bg-violet-700" @click="goToNew">
                            <Plus class="h-4 w-4 mr-2" />
                            Nouveau
                        </Button>
                        <Button class="bg-violet-600 hover:bg-violet-700" @click="reload">
                            <RotateCcw class="h-4 w-4 mr-2" />
                            Recharger
                        </Button>
                    </div>
                </div>
            </div>

            <DataTable
                :headers="columns"
                :items="filteredRows"
                :show-select="false"
                :current-page="transfers.current_page"
                :items-per-page="transfers.per_page"
                :total-items="transfers.total"
            >
                <template #item.statut="{ item }">
                    <span
                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold border"
                        :class="statusClass(item.statut)"
                    >
                        <Check v-if="item.statut === 'validé'" class="h-3.5 w-3.5" />
                        {{ statusLabel(item.statut) }}
                    </span>
                </template>

                <template #item.actions="{ item }">
                    <div class="flex items-center justify-end gap-1">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                            title="Voir le détail"
                            @click="voirDetail(item)"
                        >
                            <Eye class="h-4 w-4" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
