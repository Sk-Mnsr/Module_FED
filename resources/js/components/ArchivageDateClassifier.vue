<script lang="ts">
export type ArchivePiece = {
    id: number;
    description: string | null;
    original_name: string;
    url: string;
};

export type ArchiveFolder = {
    id: number;
    nom_classeur: string;
    numero_piece: string | null;
    numero_batch: string;
    statut: string;
    user_id: number;
    user_name: string | null;
    date_valeur: string | null;
    archive_date: string | null;
    archived_at: string | null;
    created_at: string | null;
    integrated_at: string | null;
    pdf_url: string;
    resume_url: string;
    pieces: ArchivePiece[];
};

export type ArchiveAgent = {
    id: number;
    name: string;
    flex_id: string;
};

export type ArchiveAgentNode = {
    agent: ArchiveAgent;
    classeurs: ArchiveFolder[];
};

export type ArchiveTree = Record<string, Record<string, Record<string, Record<string, ArchiveAgentNode>>>>;

export type SearchResultRow = {
    year: string;
    month: string;
    day: string;
    agent_id: number;
    agent_name: string;
    classeur: ArchiveFolder;
};

export type ArchiveFilters = {
    q?: string;
    nom_classeur?: string;
    numero_batch?: string;
    statut?: string;
    user_id?: string | number;
    annee?: string | number;
    mois?: string | number;
    jour?: string | number;
    archive_du?: string;
    archive_au?: string;
    date_valeur_du?: string;
    date_valeur_au?: string;
};
</script>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Calendar,
    ChevronRight,
    Download,
    FileText,
    Folder,
    FolderArchive,
    Search,
    Shield,
    ShieldCheck,
    User,
    X,
    FileSearch,
} from 'lucide-vue-next';

const props = defineProps<{
    tree?: ArchiveTree;
    agents?: ArchiveAgent[];
    filters?: ArchiveFilters;
    searchActive?: boolean;
    searchResults?: SearchResultRow[];
    canViewAllAgents?: boolean;
    totalClasseurs?: number;
    comptableImportApiConfigured?: boolean;
}>();

const validerEnCours = ref<number | null>(null);

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string; warning?: string } | undefined);

const MONTHS_FR = [
    'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
] as const;

const tree = computed<ArchiveTree>(() => props.tree ?? {});
const searchForm = ref({
    q: props.filters?.q ?? '',
    nom_classeur: props.filters?.nom_classeur ?? '',
    numero_batch: props.filters?.numero_batch ?? '',
    statut: props.filters?.statut ?? '',
    user_id: props.filters?.user_id ? String(props.filters.user_id) : '',
    archive_du: props.filters?.archive_du ?? '',
    archive_au: props.filters?.archive_au ?? '',
    date_valeur_du: props.filters?.date_valeur_du ?? '',
    date_valeur_au: props.filters?.date_valeur_au ?? '',
});

const showSearch = ref(props.searchActive ?? false);

const now = new Date();
const selectedYear = ref(String(now.getFullYear()));
const selectedMonth = ref(String(now.getMonth() + 1).padStart(2, '0'));
const selectedDay = ref(String(now.getDate()).padStart(2, '0'));
const selectedAgentId = ref<string | null>(null);

const yearKeys = computed(() => Object.keys(tree.value).sort((a, b) => Number(b) - Number(a)));

const monthKeys = computed(() => {
    const months = tree.value[selectedYear.value];
    return months ? Object.keys(months).sort((a, b) => Number(b) - Number(a)) : [];
});

const dayKeys = computed(() => {
    const days = tree.value[selectedYear.value]?.[selectedMonth.value];
    return days ? Object.keys(days).sort((a, b) => Number(b) - Number(a)) : [];
});

const agentNodes = computed<ArchiveAgentNode[]>(() => {
    const day = tree.value[selectedYear.value]?.[selectedMonth.value]?.[selectedDay.value];
    if (!day) {
        return [];
    }
    return Object.values(day).sort((a, b) => a.agent.name.localeCompare(b.agent.name));
});

const currentAgentNode = computed(() =>
    agentNodes.value.find((n) => String(n.agent.id) === selectedAgentId.value) ?? null,
);

const currentClasseurs = computed<ArchiveFolder[]>(() => currentAgentNode.value?.classeurs ?? []);

function initSelectionFromTree() {
    const y = yearKeys.value[0];
    if (!y) {
        return;
    }
    selectedYear.value = y;
    const m = Object.keys(tree.value[y] ?? {}).sort((a, b) => Number(b) - Number(a))[0];
    if (!m) {
        return;
    }
    selectedMonth.value = m;
    const d = Object.keys(tree.value[y][m] ?? {}).sort((a, b) => Number(b) - Number(a))[0];
    if (!d) {
        return;
    }
    selectedDay.value = d;
    const agents = Object.values(tree.value[y][m][d] ?? {});
    if (agents.length > 0) {
        selectedAgentId.value = String(agents[0].agent.id);
    }
}

onMounted(initSelectionFromTree);

watch(tree, initSelectionFromTree);

watch([selectedYear, selectedMonth, selectedDay], () => {
    const agents = agentNodes.value;
    if (agents.length === 0) {
        selectedAgentId.value = null;
        return;
    }
    const still = agents.some((n) => String(n.agent.id) === selectedAgentId.value);
    if (!still) {
        selectedAgentId.value = String(agents[0].agent.id);
    }
});

const breadcrumbPath = computed(() => {
    const m = MONTHS_FR[parseInt(selectedMonth.value, 10) - 1] ?? selectedMonth.value;
    const agent = currentAgentNode.value?.agent.name ?? '—';
    return `${selectedYear.value} › ${m} › ${selectedDay.value} › ${agent}`;
});

function monthLabel(m: string): string {
    const idx = parseInt(m, 10) - 1;
    return MONTHS_FR[idx] ?? m;
}

function agentClasseurCount(node: ArchiveAgentNode): number {
    return node.classeurs.length;
}

function horodatage(iso: string | null): string {
    if (!iso) {
        return '—';
    }
    const d = new Date(iso);
    return d.toLocaleString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

function dateValeurFmt(iso: string | null): string {
    if (!iso) {
        return '—';
    }
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}

function applySearch() {
    const params: Record<string, string> = {};
    Object.entries(searchForm.value).forEach(([k, v]) => {
        if (v) {
            params[k] = v;
        }
    });

    router.get('/operations-diverses/archivage', params, {
        preserveScroll: true,
        preserveState: true,
    });
}

function validerClasseur(id: number) {
    if (validerEnCours.value !== null) {
        return;
    }
    validerEnCours.value = id;
    router.post(`/operations-diverses/piece-comptable/${id}/valider`, {}, {
        preserveScroll: true,
        onFinish: () => {
            validerEnCours.value = null;
        },
    });
}

function resetSearch() {
    searchForm.value = {
        q: '',
        nom_classeur: '',
        numero_batch: '',
        statut: '',
        user_id: '',
        archive_du: '',
        archive_au: '',
        date_valeur_du: '',
        date_valeur_au: '',
    };
    router.get('/operations-diverses/archivage', {}, { preserveScroll: true });
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <div v-if="flash?.success" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
            {{ flash.success }}
        </div>
        <div v-if="flash?.warning" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
            {{ flash.warning }}
        </div>
        <div v-if="flash?.error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
            {{ flash.error }}
        </div>

        <!-- En-tête sécurité / horodatage -->
        <div class="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-border bg-card px-4 py-3 shadow-sm">
            <div class="flex items-start gap-2">
                <Shield class="size-5 shrink-0 text-violet-600 dark:text-violet-400" />
                <div>
                    <p class="text-sm font-semibold text-foreground">Archivage électronique sécurisé</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Classement automatique année › mois › journée › agent. Chaque enregistrement est horodaté à la création.
                    </p>
                </div>
            </div>
            <span class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">
                {{ totalClasseurs ?? 0 }} classeur(s) archivé(s)
            </span>
        </div>

        <!-- Recherche multicritère -->
        <div class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
            <button
                type="button"
                class="flex w-full items-center justify-between border-b border-border bg-muted/50 px-4 py-3 text-left"
                @click="showSearch = !showSearch"
            >
                <span class="flex items-center gap-2 text-sm font-semibold text-foreground">
                    <Search class="size-4" />
                    Moteur de recherche multicritère
                </span>
                <span v-if="searchActive" class="rounded bg-violet-100 px-2 py-0.5 text-[10px] font-semibold text-violet-700 dark:bg-violet-950 dark:text-violet-300">
                    Filtres actifs
                </span>
            </button>

            <div v-show="showSearch" class="space-y-4 p-4">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="search-q">Recherche globale</Label>
                        <Input id="search-q" v-model="searchForm.q" placeholder="Classeur, batch, agent, fichier…" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="search-nom">Nom du classeur</Label>
                        <Input id="search-nom" v-model="searchForm.nom_classeur" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="search-batch">N° batch</Label>
                        <Input id="search-batch" v-model="searchForm.numero_batch" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="search-statut">Statut</Label>
                        <select
                            id="search-statut"
                            v-model="searchForm.statut"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs"
                        >
                            <option value="">Tous</option>
                            <option value="brouillon">Brouillon</option>
                            <option value="integre">Intégré</option>
                        </select>
                    </div>
                    <div v-if="canViewAllAgents" class="space-y-1.5">
                        <Label for="search-agent">Agent</Label>
                        <select
                            id="search-agent"
                            v-model="searchForm.user_id"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs"
                        >
                            <option value="">Tous les agents</option>
                            <option v-for="a in agents" :key="a.id" :value="String(a.id)">
                                {{ a.name }}{{ a.flex_id ? ` (${a.flex_id})` : '' }}
                            </option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="search-archive-du">Archivage du</Label>
                        <Input id="search-archive-du" v-model="searchForm.archive_du" type="date" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="search-archive-au">Archivage au</Label>
                        <Input id="search-archive-au" v-model="searchForm.archive_au" type="date" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="search-dv-du">Date valeur du</Label>
                        <Input id="search-dv-du" v-model="searchForm.date_valeur_du" type="date" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="search-dv-au">Date valeur au</Label>
                        <Input id="search-dv-au" v-model="searchForm.date_valeur_au" type="date" />
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button type="button" class="bg-violet-700 text-white hover:bg-violet-800" @click="applySearch">
                        <Search class="size-4" />
                        Rechercher
                    </Button>
                    <Button v-if="searchActive" type="button" variant="outline" @click="resetSearch">
                        <X class="size-4" />
                        Effacer les filtres
                    </Button>
                </div>
            </div>
        </div>

        <!-- Résultats recherche -->
        <div v-if="searchActive && searchResults && searchResults.length > 0" class="rounded-lg border border-border bg-card shadow-sm">
            <div class="border-b border-border px-4 py-3">
                <p class="text-sm font-semibold text-foreground">{{ searchResults.length }} résultat(s)</p>
            </div>
            <div class="divide-y divide-border">
                <div v-for="(row, i) in searchResults" :key="i" class="px-4 py-3">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        <span>{{ row.year }} › {{ monthLabel(row.month) }} › {{ row.day }}</span>
                        <span>› {{ row.agent_name }}</span>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <Folder class="size-4 text-violet-600" />
                        <span class="font-medium text-foreground">{{ row.classeur.nom_classeur }}</span>
                        <span class="text-xs text-muted-foreground">Date valeur : {{ dateValeurFmt(row.classeur.date_valeur) }}</span>
                        <span class="text-xs text-muted-foreground">Archivé : {{ horodatage(row.classeur.archived_at) }}</span>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <a
                            :href="row.classeur.pdf_url"
                            class="inline-flex items-center gap-1 text-xs font-medium text-violet-700 hover:underline dark:text-violet-300"
                        >
                            <Download class="size-3.5" /> Pièce comptable
                        </a>
                        <template v-if="row.classeur.statut !== 'integre'">
                            <Link
                                :href="row.classeur.resume_url"
                                class="inline-flex items-center gap-1 text-xs font-medium text-foreground hover:underline"
                            >
                                <FileSearch class="size-3.5" /> Résumé
                            </Link>
                            <Button
                                type="button"
                                size="sm"
                                class="h-7 bg-violet-700 text-xs text-white hover:bg-violet-800"
                                :disabled="validerEnCours === row.classeur.id"
                                @click="validerClasseur(row.classeur.id)"
                            >
                                <ShieldCheck class="size-3.5" />
                                {{ validerEnCours === row.classeur.id ? 'Validation…' : 'Valider' }}
                            </Button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div v-else-if="searchActive" class="rounded-lg border border-dashed border-border bg-muted/20 p-6 text-center text-sm text-muted-foreground">
            Aucun résultat pour ces critères.
        </div>

        <!-- Navigateur hiérarchique -->
        <div class="rounded-lg border border-border bg-card text-card-foreground shadow-sm">
            <div class="border-b border-border px-4 py-3">
                <div class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <FolderArchive class="size-4 shrink-0" />
                    <span>Classement :</span>
                    <span class="font-medium text-foreground">{{ breadcrumbPath }}</span>
                </div>
            </div>

            <div class="grid divide-border md:grid-cols-4 md:divide-x">
                <!-- Année -->
                <div class="flex max-h-80 flex-col border-b border-border md:border-b-0">
                    <div class="sticky top-0 border-b border-border bg-muted/50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <Calendar class="mr-1 inline size-3.5" /> Année
                    </div>
                    <div class="max-h-64 overflow-y-auto p-2">
                        <Button
                            v-for="y in yearKeys"
                            :key="y"
                            variant="ghost"
                            size="sm"
                            class="mb-0.5 h-9 w-full justify-between px-3 font-normal"
                            :class="selectedYear === y ? 'bg-primary/10 font-medium text-primary' : ''"
                            @click="selectedYear = y"
                        >
                            {{ y }}
                            <ChevronRight v-if="selectedYear === y" class="size-4 opacity-70" />
                        </Button>
                        <p v-if="yearKeys.length === 0" class="px-2 py-4 text-center text-xs text-muted-foreground">Aucune année</p>
                    </div>
                </div>

                <!-- Mois -->
                <div class="flex max-h-80 flex-col border-b border-border md:border-b-0">
                    <div class="sticky top-0 border-b border-border bg-muted/50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Mois
                    </div>
                    <div class="max-h-64 overflow-y-auto p-2">
                        <Button
                            v-for="m in monthKeys"
                            :key="m"
                            variant="ghost"
                            size="sm"
                            class="mb-0.5 h-9 w-full justify-between px-3 font-normal"
                            :class="selectedMonth === m ? 'bg-primary/10 font-medium text-primary' : ''"
                            @click="selectedMonth = m"
                        >
                            {{ monthLabel(m) }}
                            <ChevronRight v-if="selectedMonth === m" class="size-4 opacity-70" />
                        </Button>
                        <p v-if="monthKeys.length === 0" class="px-2 py-4 text-center text-xs text-muted-foreground">Aucun mois</p>
                    </div>
                </div>

                <!-- Journée -->
                <div class="flex max-h-80 flex-col border-b border-border md:border-b-0">
                    <div class="sticky top-0 border-b border-border bg-muted/50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Journée
                    </div>
                    <div class="max-h-64 overflow-y-auto p-2">
                        <Button
                            v-for="d in dayKeys"
                            :key="d"
                            variant="ghost"
                            size="sm"
                            class="mb-0.5 h-9 w-full justify-between px-3 font-normal tabular-nums"
                            :class="selectedDay === d ? 'bg-primary/10 font-medium text-primary' : ''"
                            @click="selectedDay = d"
                        >
                            {{ d }}
                            <ChevronRight v-if="selectedDay === d" class="size-4 opacity-70" />
                        </Button>
                        <p v-if="dayKeys.length === 0" class="px-2 py-4 text-center text-xs text-muted-foreground">Aucun jour</p>
                    </div>
                </div>

                <!-- Agent -->
                <div class="flex max-h-80 flex-col">
                    <div class="sticky top-0 border-b border-border bg-muted/50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <User class="mr-1 inline size-3.5" /> Agent
                    </div>
                    <div class="max-h-64 overflow-y-auto p-2">
                        <Button
                            v-for="node in agentNodes"
                            :key="node.agent.id"
                            variant="ghost"
                            size="sm"
                            class="mb-0.5 h-auto min-h-9 w-full justify-between px-3 py-2 font-normal"
                            :class="selectedAgentId === String(node.agent.id) ? 'bg-primary/10 font-medium text-primary' : ''"
                            @click="selectedAgentId = String(node.agent.id)"
                        >
                            <span class="truncate text-left">
                                <span class="block truncate">{{ node.agent.name }}</span>
                                <span v-if="node.agent.flex_id" class="block truncate text-[10px] text-muted-foreground">{{ node.agent.flex_id }}</span>
                            </span>
                            <span class="ml-2 shrink-0 rounded bg-muted px-1.5 text-[10px] tabular-nums">{{ agentClasseurCount(node) }}</span>
                        </Button>
                        <p v-if="agentNodes.length === 0" class="px-2 py-4 text-center text-xs text-muted-foreground">Aucun agent</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Classeurs de l'agent (date valeur pointée) -->
        <div class="rounded-lg border border-border bg-card p-4 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-sm font-medium text-foreground">
                        Classeurs — {{ currentAgentNode?.agent.name ?? 'Sélectionnez un agent' }}
                    </p>
                    <p v-if="currentAgentNode" class="text-xs text-muted-foreground">
                        {{ currentClasseurs.length }} classeur(s) pour le {{ selectedDay }}/{{ selectedMonth }}/{{ selectedYear }}
                    </p>
                </div>
            </div>

            <div v-if="currentClasseurs.length === 0" class="rounded-lg border border-dashed border-border bg-muted/20 p-6 text-center">
                <p class="text-sm text-muted-foreground">Aucun classeur pour cette sélection.</p>
            </div>

            <div v-else class="grid gap-3">
                <div
                    v-for="folder in currentClasseurs"
                    :key="folder.id"
                    class="overflow-hidden rounded-lg border border-border bg-background dark:bg-card/40"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-muted/40 px-4 py-2.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <Folder class="size-4 text-violet-600 dark:text-violet-400" />
                            <span class="text-sm font-semibold text-foreground">{{ folder.nom_classeur }}</span>
                            <span class="rounded bg-violet-100 px-1.5 py-0.5 text-[10px] font-semibold text-violet-700 dark:bg-violet-950 dark:text-violet-300">
                                N° {{ folder.numero_piece ?? folder.numero_batch }}
                            </span>
                            <span
                                class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                :class="folder.statut === 'integre'
                                    ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300'
                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'"
                            >
                                {{ folder.statut === 'integre' ? 'Intégré' : 'Brouillon' }}
                            </span>
                        </div>
                        <div class="flex flex-col items-end text-xs">
                            <span class="font-medium text-foreground">Date valeur : {{ dateValeurFmt(folder.date_valeur) }}</span>
                            <span class="text-muted-foreground">Archivé le {{ horodatage(folder.archived_at) }}</span>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 px-4 py-3">
                        <div class="flex flex-wrap gap-2">
                            <a
                                :href="folder.pdf_url"
                                class="inline-flex items-center gap-1.5 rounded-md border border-violet-200 bg-background px-2.5 py-1.5 text-xs font-medium text-violet-800 hover:bg-violet-50 dark:border-violet-900 dark:text-violet-200"
                            >
                                <Download class="size-3.5" /> Pièce comptable (PDF)
                            </a>
                            <template v-if="folder.statut !== 'integre'">
                                <Link
                                    :href="folder.resume_url"
                                    class="inline-flex items-center gap-1.5 rounded-md border border-border bg-background px-2.5 py-1.5 text-xs font-medium text-foreground hover:bg-muted"
                                >
                                    <FileSearch class="size-3.5" /> Voir le résumé
                                </Link>
                                <Button
                                    type="button"
                                    class="h-8 bg-violet-700 text-xs text-white hover:bg-violet-800 dark:bg-violet-600"
                                    :disabled="validerEnCours === folder.id"
                                    @click="validerClasseur(folder.id)"
                                >
                                    <ShieldCheck class="size-3.5" />
                                    {{ validerEnCours === folder.id ? 'Validation…' : 'Valider l’intégration' }}
                                </Button>
                            </template>
                        </div>
                        <p
                            v-if="folder.statut !== 'integre' && comptableImportApiConfigured === false"
                            class="text-xs text-amber-700 dark:text-amber-300"
                        >
                            API plateforme non configurée : la validation échouera tant que l’URL n’est pas accessible.
                        </p>

                        <div v-if="folder.pieces.length" class="mt-1 space-y-1">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Pièces justificatives</p>
                            <a
                                v-for="p in folder.pieces"
                                :key="p.id"
                                :href="p.url"
                                class="flex items-center gap-2 rounded px-2 py-1 text-xs text-foreground hover:bg-muted/50"
                            >
                                <FileText class="size-3.5 text-muted-foreground" />
                                <span>{{ p.description || p.original_name }}</span>
                                <Download class="ml-auto size-3.5 text-muted-foreground" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
