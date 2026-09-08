<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Download, History, ImageIcon, Loader2, RotateCcw } from 'lucide-vue-next';
import { computed, onUnmounted, ref } from 'vue';

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
    can_relancer: boolean;
    relancer_url: string;
    ouvrir_url: string;
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

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string; warning?: string } | undefined);

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

type RelancePhase = 'confirm' | 'running' | 'done' | 'error';

type RelanceResult = {
    message: string;
    taux_reussite?: number | null;
    reconcilies?: number | null;
    total?: number | null;
    ouvrir_url?: string | null;
};

const relaunchOpen = ref(false);
const relaunchPhase = ref<RelancePhase>('confirm');
const relaunchRow = ref<RunRow | null>(null);
const relaunchLabel = ref('');
const relaunchProgress = ref(0);
const relaunchResult = ref<RelanceResult | null>(null);
let relaunchProgressTimer: ReturnType<typeof setInterval> | null = null;

function readCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
    return match ? decodeURIComponent(match[1]) : null;
}

function csrfHeaders(): Record<string, string> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const xsrf = readCookie('XSRF-TOKEN');
    if (xsrf) {
        headers['X-XSRF-TOKEN'] = xsrf;
        return headers;
    }
    const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (meta) {
        headers['X-CSRF-TOKEN'] = meta;
    }
    return headers;
}

function clearRelaunchProgressTimer() {
    if (relaunchProgressTimer) {
        clearInterval(relaunchProgressTimer);
        relaunchProgressTimer = null;
    }
}

function startRelaunchProgress(label: string) {
    clearRelaunchProgressTimer();
    relaunchPhase.value = 'running';
    relaunchLabel.value = label;
    relaunchProgress.value = 12;
    relaunchProgressTimer = setInterval(() => {
        if (relaunchProgress.value < 90) {
            relaunchProgress.value = Math.min(
                90,
                relaunchProgress.value + Math.max(0.4, (90 - relaunchProgress.value) * 0.035),
            );
        }
    }, 180);
}

function finishRelaunchProgress() {
    clearRelaunchProgressTimer();
    relaunchProgress.value = 100;
}

function closeRelaunchModal() {
    if (relaunchPhase.value === 'running') return;
    clearRelaunchProgressTimer();
    relaunchOpen.value = false;
    relaunchRow.value = null;
    relaunchResult.value = null;
    relaunchPhase.value = 'confirm';
    relaunchLabel.value = '';
    relaunchProgress.value = 0;
}

function relancer(row: RunRow) {
    if (!row.can_relancer) {
        router.visit(row.ouvrir_url);
        return;
    }
    relaunchRow.value = row;
    relaunchPhase.value = 'confirm';
    relaunchResult.value = null;
    relaunchLabel.value = '';
    relaunchProgress.value = 0;
    relaunchOpen.value = true;
}

async function confirmRelancer() {
    const row = relaunchRow.value;
    if (!row) return;

    startRelaunchProgress(`Chargement des fichiers — ${row.partenaire_nom}`);

    // Étape visuelle : charger puis lancer (le backend enchaîne les deux).
    window.setTimeout(() => {
        if (relaunchPhase.value === 'running') {
            relaunchLabel.value = `Lancement de la réconciliation — ${row.partenaire_nom}`;
        }
    }, 1200);

    try {
        const res = await fetch(row.relancer_url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: csrfHeaders(),
        });
        const data = (await res.json().catch(() => ({}))) as RelanceResult & {
            ok?: boolean;
            ouvrir_url?: string;
        };

        finishRelaunchProgress();

        if (!res.ok || data.ok === false) {
            relaunchPhase.value = 'error';
            relaunchResult.value = {
                message: data.message || `Échec du relancement (HTTP ${res.status}).`,
                ouvrir_url: data.ouvrir_url ?? row.ouvrir_url,
            };
            return;
        }

        relaunchPhase.value = 'done';
        relaunchResult.value = {
            message: data.message || 'Réconciliation relancée (non ajoutée à l’historique).',
            taux_reussite: data.taux_reussite,
            reconcilies: data.reconcilies,
            total: data.total,
            ouvrir_url: data.ouvrir_url ?? `/reconciliation-flexcube/reconciliation/${row.partenaire_id}`,
        };
    } catch (e) {
        finishRelaunchProgress();
        relaunchPhase.value = 'error';
        relaunchResult.value = {
            message: e instanceof Error ? e.message : 'Erreur réseau lors du relancement.',
            ouvrir_url: row.ouvrir_url,
        };
    }
}

function formatTaux(row: RunRow): string {
    if (row.taux_reussite == null) return '—';
    const parts = [`${row.taux_reussite}%`];
    if (row.reconcilies != null && row.total != null) {
        parts.push(`(${row.reconcilies}/${row.total})`);
    }
    return parts.join(' ');
}

onUnmounted(() => {
    clearRelaunchProgressTimer();
});
</script>

<template>
    <Head title="Historique — Reconciliation Flexcube" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div
                v-if="flash?.success"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            >
                {{ flash.success }}
            </div>
            <div
                v-if="flash?.error"
                class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"
            >
                {{ flash.error }}
            </div>
            <div
                v-if="flash?.warning"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"
            >
                {{ flash.warning }}
            </div>

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
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-md p-2 text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-50"
                            :title="
                                item.can_relancer
                                    ? 'Relancer automatiquement (fichier + dates)'
                                    : 'Ouvrir avec les dates préremplies (fichier source non conservé)'
                            "
                            :disabled="relaunchPhase === 'running' && relaunchRow?.id === item.id"
                            @click="relancer(item)"
                        >
                            <RotateCcw
                                class="size-4"
                                :class="
                                    relaunchPhase === 'running' && relaunchRow?.id === item.id
                                        ? 'animate-spin'
                                        : ''
                                "
                            />
                        </button>
                    </div>
                </template>
            </DataTable>

            <Dialog :open="relaunchOpen" @update:open="(v) => { if (!v) closeRelaunchModal(); }">
                <DialogContent
                    class="sm:max-w-md"
                    :hide-close="relaunchPhase === 'running'"
                    @interact-outside="(e: Event) => { if (relaunchPhase === 'running') e.preventDefault(); }"
                    @escape-key-down="(e: Event) => { if (relaunchPhase === 'running') e.preventDefault(); }"
                >
                    <DialogHeader>
                        <DialogTitle>
                            <template v-if="relaunchPhase === 'confirm'">Relancer la réconciliation</template>
                            <template v-else-if="relaunchPhase === 'running'">Avancement</template>
                            <template v-else-if="relaunchPhase === 'done'">Relancement terminé</template>
                            <template v-else>Échec du relancement</template>
                        </DialogTitle>
                    </DialogHeader>

                    <div v-if="relaunchPhase === 'confirm' && relaunchRow" class="space-y-2 text-sm text-muted-foreground">
                        <p>
                            Relancer « <span class="font-medium text-foreground">{{ relaunchRow.partenaire_nom }}</span> »
                            avec le même fichier et la même période
                            ({{ formatPeriode(relaunchRow) }}) ?
                        </p>
                        <p class="text-xs">
                            Aucune nouvelle ligne ne sera ajoutée à l’historique.
                        </p>
                    </div>

                    <div v-else-if="relaunchPhase === 'running'" class="space-y-3" role="status" aria-live="polite">
                        <div class="flex items-center gap-3">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-cyan-100 text-cyan-800">
                                <Loader2 class="size-4 animate-spin" />
                            </div>
                            <div class="min-w-0 flex-1 space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-medium text-cyan-950">{{ relaunchLabel }}</p>
                                    <span class="tabular-nums text-xs font-semibold text-cyan-800">
                                        {{ Math.round(relaunchProgress) }} %
                                    </span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-cyan-100/90 ring-1 ring-cyan-200/60">
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-sky-600 transition-[width] duration-200 ease-out"
                                        :style="{ width: `${relaunchProgress}%` }"
                                    />
                                </div>
                                <p class="text-[11px] text-cyan-800/70">
                                    Traitement en cours — merci de patienter…
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else-if="relaunchResult"
                        class="space-y-2 rounded-lg border px-3 py-2.5 text-sm"
                        :class="
                            relaunchPhase === 'done'
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-950'
                                : 'border-rose-200 bg-rose-50 text-rose-950'
                        "
                    >
                        <p>{{ relaunchResult.message }}</p>
                        <p
                            v-if="relaunchPhase === 'done' && relaunchResult.taux_reussite != null"
                            class="text-xs opacity-90"
                        >
                            Taux :
                            {{ relaunchResult.taux_reussite }}%
                            <template v-if="relaunchResult.reconcilies != null && relaunchResult.total != null">
                                ({{ relaunchResult.reconcilies }}/{{ relaunchResult.total }})
                            </template>
                        </p>
                    </div>

                    <DialogFooter class="gap-2 sm:gap-2">
                        <Button
                            v-if="relaunchPhase === 'confirm'"
                            type="button"
                            variant="outline"
                            @click="closeRelaunchModal"
                        >
                            Annuler
                        </Button>
                        <Button
                            v-if="relaunchPhase === 'confirm'"
                            type="button"
                            class="bg-cyan-700 text-white hover:bg-cyan-800"
                            @click="confirmRelancer"
                        >
                            Relancer
                        </Button>
                        <Button
                            v-if="relaunchPhase === 'done' || relaunchPhase === 'error'"
                            type="button"
                            variant="outline"
                            @click="closeRelaunchModal"
                        >
                            Fermer
                        </Button>
                        <Link
                            v-if="
                                (relaunchPhase === 'done' || relaunchPhase === 'error') &&
                                relaunchResult?.ouvrir_url
                            "
                            :href="relaunchResult.ouvrir_url"
                            class="inline-flex h-9 items-center justify-center rounded-md bg-cyan-700 px-4 text-sm font-medium text-white hover:bg-cyan-800"
                        >
                            Ouvrir la page partenaire
                        </Link>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
