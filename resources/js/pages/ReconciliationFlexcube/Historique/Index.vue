<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, History, ImageIcon, RotateCcw } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type RunRow = {
    id: number;
    partenaire_id: number;
    partenaire_identifiant: string;
    partenaire_nom: string;
    partenaire_icone_url: string | null;
    date_debut: string | null;
    date_fin: string | null;
    mode: string;
    taux_reussite: number | null;
    reconcilies: number | null;
    total: number | null;
    excel_filename: string | null;
    excel_url: string | null;
    status: string;
    user_name: string | null;
    created_at: string | null;
};

type Paginated = {
    data: RunRow[];
    current_page: number;
    per_page: number;
    total: number;
};

type PartenaireOption = {
    id: number;
    identifiant: string;
    nom: string;
};

const props = withDefaults(
    defineProps<{
        runs?: Paginated;
        partenaires?: PartenaireOption[];
        filters?: {
            q: string;
            partenaire_id: number | null;
            mode: string;
        };
    }>(),
    {
        runs: () => ({ data: [], current_page: 1, per_page: 15, total: 0 }),
        partenaires: () => [],
        filters: () => ({ q: '', partenaire_id: null, mode: '' }),
    },
);

const breadcrumbs = [
    { title: 'Reconciliation Flexcube', href: '/reconciliation-flexcube' },
    { title: 'Historique', href: '/reconciliation-flexcube/historique' },
];

const qLocal = ref(props.filters.q ?? '');
const partenaireLocal = ref(props.filters.partenaire_id ? String(props.filters.partenaire_id) : '');
const modeLocal = ref(props.filters.mode ?? '');

const columns = [
    { key: 'partenaire', title: 'Partenaire' },
    { key: 'periode', title: 'Période' },
    { key: 'mode', title: 'Mode' },
    { key: 'taux', title: 'Taux' },
    { key: 'status', title: 'Statut' },
    { key: 'user_name', title: 'Utilisateur' },
    { key: 'created_at', title: 'Date' },
    { key: 'actions', title: 'Actions' },
];

const rows = computed(() => props.runs?.data ?? []);

const listQuery = () => ({
    q: qLocal.value.trim() || undefined,
    partenaire_id: partenaireLocal.value || undefined,
    mode: modeLocal.value || undefined,
    per_page: props.runs.per_page,
});

function applyFilters() {
    router.get('/reconciliation-flexcube/historique', { ...listQuery(), page: 1 }, {
        preserveState: true,
        replace: true,
    });
}

function onPageChange(page: number) {
    router.get('/reconciliation-flexcube/historique', { ...listQuery(), page }, {
        preserveState: true,
        replace: true,
    });
}

function onItemsPerPageChange(perPage: number) {
    router.get('/reconciliation-flexcube/historique', { ...listQuery(), per_page: perPage, page: 1 }, {
        preserveState: true,
        replace: true,
    });
}

function reload() {
    router.reload({ only: ['runs', 'filters', 'partenaires'] });
}

function formatPeriode(row: RunRow): string {
    if (!row.date_debut && !row.date_fin) return '—';
    const d = (v: string | null) => (v ? v.split('-').reverse().join('/') : '…');
    return `${d(row.date_debut)} → ${d(row.date_fin)}`;
}

function formatTaux(row: RunRow): string {
    if (row.taux_reussite == null) return '—';
    const parts = [`${row.taux_reussite}%`];
    if (row.reconcilies != null && row.total != null) {
        parts.push(`(${row.reconcilies}/${row.total})`);
    }
    return parts.join(' ');
}
</script>

<template>
    <Head title="Historique — Reconciliation Flexcube" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-cyan-50 p-2 text-cyan-700">
                        <History class="size-6" />
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold text-foreground">Historique des réconciliations</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Runs enregistrés après chaque lancement, avec fichier Excel téléchargeable.
                        </p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button type="button" variant="outline" @click="reload">
                        <RotateCcw class="size-4" />
                        Actualiser
                    </Button>
                    <Link
                        href="/reconciliation-flexcube/reconciliation"
                        class="inline-flex h-9 items-center justify-center rounded-md bg-cyan-700 px-4 text-sm font-medium text-white hover:bg-cyan-800"
                    >
                        Nouvelle réconciliation
                    </Link>
                </div>
            </div>

            <div class="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-4">
                <div class="space-y-1.5 sm:col-span-2">
                    <Label for="q">Recherche</Label>
                    <Input
                        id="q"
                        v-model="qLocal"
                        placeholder="Partenaire, fichier…"
                        @keydown.enter="applyFilters"
                    />
                </div>
                <div class="space-y-1.5">
                    <Label for="partenaire">Partenaire</Label>
                    <select
                        id="partenaire"
                        v-model="partenaireLocal"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                    >
                        <option value="">Tous</option>
                        <option v-for="p in partenaires" :key="p.id" :value="String(p.id)">
                            {{ p.nom }} ({{ p.identifiant }})
                        </option>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <Label for="mode">Mode</Label>
                    <select
                        id="mode"
                        v-model="modeLocal"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                    >
                        <option value="">Tous</option>
                        <option value="two_pointers">two_pointers</option>
                        <option value="agence">agence</option>
                    </select>
                </div>
                <div class="flex items-end sm:col-span-4">
                    <Button type="button" class="bg-slate-900 text-white hover:bg-slate-800" @click="applyFilters">
                        Filtrer
                    </Button>
                </div>
            </div>

            <DataTable
                :headers="columns"
                :items="rows"
                :current-page="runs.current_page"
                :items-per-page="runs.per_page"
                :total-items="runs.total"
                :show-select="false"
                :on-page-change="onPageChange"
                :on-items-per-page-change="onItemsPerPageChange"
            >
                <template #item.partenaire="{ item }">
                    <div class="flex items-center gap-2">
                        <div class="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded border border-border bg-white">
                            <img
                                v-if="item.partenaire_icone_url"
                                :src="item.partenaire_icone_url"
                                :alt="item.partenaire_nom"
                                class="size-full object-contain p-0.5"
                            />
                            <ImageIcon v-else class="size-3.5 text-muted-foreground" />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate font-medium text-foreground">{{ item.partenaire_nom }}</p>
                            <p class="font-mono text-xs text-muted-foreground">{{ item.partenaire_identifiant }}</p>
                        </div>
                    </div>
                </template>
                <template #item.periode="{ item }">
                    <span class="text-sm">{{ formatPeriode(item) }}</span>
                </template>
                <template #item.mode="{ item }">
                    <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs">{{ item.mode }}</span>
                </template>
                <template #item.taux="{ item }">
                    <span class="text-sm font-medium">{{ formatTaux(item) }}</span>
                </template>
                <template #item.status="{ item }">
                    <span
                        class="rounded px-1.5 py-0.5 text-xs font-medium"
                        :class="
                            item.status === 'success'
                                ? 'bg-green-100 text-green-800'
                                : 'bg-red-100 text-red-800'
                        "
                    >
                        {{ item.status === 'success' ? 'Succès' : 'Échec' }}
                    </span>
                </template>
                <template #item.user_name="{ item }">
                    <span class="text-sm">{{ item.user_name ?? '—' }}</span>
                </template>
                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <a
                            v-if="item.excel_url"
                            :href="`/reconciliation-flexcube/historique/${item.id}/download`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-cyan-800 hover:bg-cyan-50"
                            title="Télécharger l’Excel"
                        >
                            <Download class="size-4" />
                        </a>
                        <Link
                            :href="`/reconciliation-flexcube/reconciliation/${item.partenaire_id}`"
                            class="inline-flex items-center justify-center rounded-md p-2 text-muted-foreground hover:bg-muted hover:text-foreground"
                            title="Relancer sur ce partenaire"
                        >
                            <RotateCcw class="size-4" />
                        </Link>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
