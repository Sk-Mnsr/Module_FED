<script lang="ts">
export type ArchivePiece = {
    id: number | string;
    description: string | null;
    original_name: string;
    url: string;
    preview_url?: string | null;
    is_piece_comptable?: boolean;
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
    resume_url: string;
    piece_folder_name?: string;
    piece_display_name?: string;
    department?: string;
    folder_path?: string;
    pieces: ArchivePiece[];
};

export type ArchiveAgent = {
    id: number;
    name: string;
    flex_id: string;
    folder_name?: string;
};

export type ArchiveAgentNode = {
    agent: ArchiveAgent;
    folder_labels?: { year: string; month: string; day: string };
    classeurs: ArchiveFolder[];
};

export type ArchiveTree = Record<string, Record<string, Record<string, Record<string, Record<string, ArchiveAgentNode>>>>>;

export type SearchResultRow = {
    dept?: string;
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

type TreeNodeKind = 'root' | 'dept' | 'year' | 'month' | 'day' | 'agent' | 'piece';

type TreeNode = {
    id: string;
    label: string;
    kind: TreeNodeKind;
    children: TreeNode[];
    piece?: ArchiveFolder;
};

type FlatNode = TreeNode & { depth: number };
</script>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    CalendarDays,
    ChevronDown,
    ChevronRight,
    Download,
    Eye,
    FileText,
    Folder,
    FolderOpen,
    Search,
    SlidersHorizontal,
    User,
    X,
    FileSearch,
    Clock,
    Hash,
    Archive,
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

const tree = computed<ArchiveTree>(() => normalizeTree(props.tree ?? {}));
const showFilters = ref(false);
const selectedId = ref<string | null>(null);
const selectedPiece = ref<ArchiveFolder | null>(null);
const expanded = ref<Set<string>>(new Set(['root']));

const fieldClass =
    'h-10 border-slate-300 bg-white text-slate-900 shadow-sm placeholder:text-slate-400 focus-visible:border-primary focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground dark:placeholder:text-slate-500';

const selectClass =
    'flex h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string; warning?: string } | undefined);

const MONTHS_FR = [
    'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
] as const;

const DEPT_ORDER = ['finance', 'operations'] as const;

function deptLabel(key: string): string {
    return key === 'finance' ? 'Finance' : 'Operations';
}

/** Nouveau format : finance|operations › année › … — Ancien : année directement à la racine. */
function isDeptTreeFormat(raw: ArchiveTree): boolean {
    const keys = Object.keys(raw);
    if (keys.length === 0) {
        return true;
    }

    return keys.every((k) => k === 'finance' || k === 'operations');
}

function normalizeTree(raw: ArchiveTree): ArchiveTree {
    if (isDeptTreeFormat(raw)) {
        return {
            finance: raw.finance ?? {},
            operations: raw.operations ?? {},
        };
    }

    return {
        finance: {},
        operations: raw,
    };
}

const searchForm = ref({
    q: props.filters?.q ?? '',
    nom_classeur: props.filters?.nom_classeur ?? '',
    numero_batch: props.filters?.numero_batch ?? '',
    user_id: props.filters?.user_id ? String(props.filters.user_id) : '',
    archive_du: props.filters?.archive_du ?? '',
    archive_au: props.filters?.archive_au ?? '',
});

const stats = computed(() => ({
    total: props.totalClasseurs ?? 0,
}));

const treeRoot = computed<TreeNode>(() => {
    return {
        id: 'root',
        label: 'Dossiers Comptables',
        kind: 'root',
        children: [...DEPT_ORDER].map((dept) => ({
            id: `dept-${dept}`,
            label: deptLabel(dept),
            kind: 'dept' as const,
            children: Object.keys(tree.value[dept] ?? {})
                .sort((a, b) => Number(b) - Number(a))
                .map((year) => ({
                    id: `dept-${dept}-y-${year}`,
                    label: year,
                    kind: 'year' as const,
                    children: Object.keys(tree.value[dept]?.[year] ?? {})
                        .sort((a, b) => Number(b) - Number(a))
                        .map((month) => ({
                            id: `dept-${dept}-y-${year}-m-${month}`,
                            label: monthFolderLabel(dept, year, month),
                            kind: 'month' as const,
                            children: Object.keys(tree.value[dept]?.[year]?.[month] ?? {})
                                .sort((a, b) => Number(b) - Number(a))
                                .map((day) => ({
                                    id: `dept-${dept}-y-${year}-m-${month}-d-${day}`,
                                    label: dayFolderLabel(dept, year, month, day),
                                    kind: 'day' as const,
                                    children: Object.values(tree.value[dept]?.[year]?.[month]?.[day] ?? {})
                                        .sort((a, b) => a.agent.name.localeCompare(b.agent.name))
                                        .map((node) => ({
                                            id: `dept-${dept}-y-${year}-m-${month}-d-${day}-a-${node.agent.id}`,
                                            label: node.agent.name,
                                            kind: 'agent' as const,
                                            children: node.classeurs.map((c) => ({
                                                id: `p-${c.id}`,
                                                label: c.nom_classeur,
                                                kind: 'piece' as const,
                                                children: [],
                                                piece: c,
                                            })),
                                        })),
                                })),
                        })),
                })),
        })),
    };
});

const flatTree = computed<FlatNode[]>(() => flattenVisible(treeRoot.value, expanded.value));

function flattenVisible(node: TreeNode, expandedSet: Set<string>, depth = 0): FlatNode[] {
    const rows: FlatNode[] = [{ ...node, depth }];
    if (!node.children.length || !expandedSet.has(node.id)) {
        return rows;
    }
    for (const child of node.children) {
        rows.push(...flattenVisible(child, expandedSet, depth + 1));
    }
    return rows;
}

function monthFolderLabel(dept: string, year: string, month: string): string {
    const node = tree.value[dept]?.[year]?.[month];
    const fallback = (MONTHS_FR[parseInt(month, 10) - 1] ?? month).toUpperCase();
    if (!node) return fallback;
    const first = Object.values(Object.values(node)[0] ?? {})[0];
    return first?.folder_labels?.month ?? fallback;
}

function dayFolderLabel(dept: string, year: string, month: string, day: string): string {
    const node = tree.value[dept]?.[year]?.[month]?.[day];
    const fallback = `Journée du ${day.padStart(2, '0')}`;
    if (!node) return fallback;
    const first = Object.values(node)[0];
    return first?.folder_labels?.day ?? fallback;
}

function toggleExpand(id: string) {
    const next = new Set(expanded.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    expanded.value = next;
}

function expandAll() {
    const ids = new Set<string>();
    const walk = (node: TreeNode) => {
        if (node.children.length) {
            ids.add(node.id);
            node.children.forEach(walk);
        }
    };
    walk(treeRoot.value);
    expanded.value = ids;
}

function collapseAll() {
    expanded.value = new Set(['root']);
}

function selectNode(node: FlatNode) {
    selectedId.value = node.id;
    if (node.kind === 'piece' && node.piece) {
        selectedPiece.value = node.piece;
        return;
    }
    selectedPiece.value = null;
}

function onNodeClick(node: FlatNode) {
    if (node.kind === 'piece') {
        selectNode(node);
        return;
    }
    if (node.children.length) {
        if (!expanded.value.has(node.id)) {
            const next = new Set(expanded.value);
            next.add(node.id);
            expanded.value = next;
        }
    }
    selectNode(node);
}

function expandPathIds(...ids: string[]) {
    const next = new Set(expanded.value);
    ids.forEach((id) => next.add(id));
    expanded.value = next;
}

function horodatage(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('fr-FR', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

function dateValeurFmt(iso: string | null): string {
    if (!iso) return '—';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}

function applySearch() {
    const params: Record<string, string> = {};
    Object.entries(searchForm.value).forEach(([k, v]) => { if (v) params[k] = v; });
    router.get('/operations-diverses/archivage', params, { preserveScroll: true, preserveState: true });
}

function resetSearch() {
    searchForm.value = {
        q: '', nom_classeur: '', numero_batch: '', user_id: '',
        archive_du: '', archive_au: '',
    };
    router.get('/operations-diverses/archivage', {}, { preserveScroll: true });
}

function openSearchResult(row: SearchResultRow) {
    const dept = row.dept ?? 'operations';
    expandPathIds(
        'root',
        `dept-${dept}`,
        `dept-${dept}-y-${row.year}`,
        `dept-${dept}-y-${row.year}-m-${row.month}`,
        `dept-${dept}-y-${row.year}-m-${row.month}-d-${row.day}`,
        `dept-${dept}-y-${row.year}-m-${row.month}-d-${row.day}-a-${row.agent_id}`,
    );
    selectedId.value = `p-${row.classeur.id}`;
    selectedPiece.value = row.classeur;
    router.get('/operations-diverses/archivage', {}, { preserveScroll: true, preserveState: true, replace: true });
}

function hasChildren(node: FlatNode): boolean {
    return node.children.length > 0;
}

function isExpanded(node: FlatNode): boolean {
    return expanded.value.has(node.id);
}

function collectExpandableIds(node: TreeNode, ids: string[] = []): string[] {
    if (node.children.length > 0) {
        ids.push(node.id);
        for (const child of node.children) {
            collectExpandableIds(child, ids);
        }
    }

    return ids;
}

watch(
    treeRoot,
    (root) => {
        if ((props.totalClasseurs ?? 0) > 0) {
            expanded.value = new Set(collectExpandableIds(root));
        } else if (expanded.value.size <= 1) {
            expanded.value = new Set(['root', 'dept-finance', 'dept-operations']);
        }
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-hidden">
        <div
            v-if="flash?.success"
            class="shrink-0 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800"
        >
            {{ flash.success }}
        </div>
        <div
            v-if="flash?.warning"
            class="shrink-0 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800"
        >
            {{ flash.warning }}
        </div>

        <section
            class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm"
        >
            <!-- En-tête -->
            <div
                class="shrink-0 border-b border-border/80 bg-gradient-to-r from-primary/5 via-card to-transparent px-5 py-5 sm:px-6 dark:from-primary/10"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm"
                        >
                            <Archive class="size-5" />
                        </div>
                        <div>
                            <h1 class="text-xl font-semibold tracking-tight text-foreground">
                                Archivage
                            </h1>
                            <p class="mt-1 max-w-xl text-sm text-muted-foreground">
                                Intégrations validées — OPS voit les pièces OPS ; Finance voit les
                                pièces Finance.
                            </p>
                        </div>
                    </div>
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"
                    >
                        <Archive class="size-3.5" />
                        <span class="font-semibold tabular-nums">{{ stats.total }}</span>
                        <span>pièce(s) archivée(s)</span>
                    </div>
                </div>
            </div>

            <!-- Recherche -->
            <div class="shrink-0 space-y-3 border-b border-border/80 p-5 sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="relative flex-1">
                        <Search
                            class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"
                        />
                        <Input
                            v-model="searchForm.q"
                            :class="[fieldClass, 'pl-9']"
                            placeholder="Rechercher par classeur, batch, agent…"
                            @keydown.enter="applySearch"
                        />
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <Button
                            variant="outline"
                            class="h-10 border-slate-300 text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-foreground"
                            @click="showFilters = !showFilters"
                        >
                            <SlidersHorizontal class="size-4" />
                            Filtres
                            <span
                                v-if="searchActive"
                                class="ml-0.5 size-1.5 rounded-full bg-primary"
                            />
                        </Button>
                        <Button
                            class="h-10 bg-primary text-primary-foreground hover:bg-primary/90"
                            @click="applySearch"
                        >
                            Rechercher
                        </Button>
                    </div>
                </div>

                <div
                    v-show="showFilters"
                    class="space-y-4 rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-muted/20"
                >
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1.5">
                            <Label
                                for="filtre-nom-classeur"
                                class="text-xs text-muted-foreground"
                            >
                                Nom du classeur
                            </Label>
                            <Input
                                id="filtre-nom-classeur"
                                v-model="searchForm.nom_classeur"
                                placeholder="Nom du classeur"
                                :class="fieldClass"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="filtre-batch" class="text-xs text-muted-foreground">
                                N° batch
                            </Label>
                            <Input
                                id="filtre-batch"
                                v-model="searchForm.numero_batch"
                                placeholder="N° batch"
                                :class="fieldClass"
                            />
                        </div>
                        <div v-if="canViewAllAgents" class="space-y-1.5">
                            <Label for="filtre-agent" class="text-xs text-muted-foreground">
                                Agent
                            </Label>
                            <select
                                id="filtre-agent"
                                v-model="searchForm.user_id"
                                :class="selectClass"
                            >
                                <option value="">Tous agents</option>
                                <option
                                    v-for="a in agents"
                                    :key="a.id"
                                    :value="String(a.id)"
                                >
                                    {{ a.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="space-y-1.5">
                            <Label for="filtre-archive-du" class="text-xs text-muted-foreground">
                                Archivé du
                            </Label>
                            <Input
                                id="filtre-archive-du"
                                v-model="searchForm.archive_du"
                                type="date"
                                :class="fieldClass"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="filtre-archive-au" class="text-xs text-muted-foreground">
                                Archivé au
                            </Label>
                            <Input
                                id="filtre-archive-au"
                                v-model="searchForm.archive_au"
                                type="date"
                                :class="fieldClass"
                            />
                        </div>
                        <div v-if="searchActive" class="flex items-end sm:col-span-2">
                            <Button
                                variant="ghost"
                                class="h-10 text-muted-foreground"
                                @click="resetSearch"
                            >
                                <X class="size-4" /> Effacer les filtres
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Résultats recherche -->
            <div
                v-if="searchActive"
                class="flex min-h-0 flex-1 flex-col overflow-hidden"
            >
                <div class="border-b border-border/80 px-5 py-3 sm:px-6">
                    <p class="text-sm font-medium">
                        {{ searchResults?.length ?? 0 }} résultat(s)
                    </p>
                </div>
                <div
                    v-if="!searchResults?.length"
                    class="flex flex-1 items-center justify-center py-16 text-sm text-muted-foreground"
                >
                    Aucun résultat pour ces critères.
                </div>
                <div v-else class="min-h-0 flex-1 space-y-2 overflow-y-auto p-4 sm:p-5">
                    <button
                        v-for="(row, i) in searchResults"
                        :key="i"
                        type="button"
                        class="flex w-full items-center gap-4 rounded-xl border border-slate-200 bg-white px-4 py-3 text-left shadow-sm transition hover:border-primary/30 hover:shadow-md dark:border-slate-700 dark:bg-card"
                        @click="openSearchResult(row)"
                    >
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <FileText class="size-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ row.classeur.nom_classeur }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ row.agent_name }} · {{ row.classeur.numero_batch }}
                            </p>
                        </div>
                        <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
                    </button>
                </div>
            </div>

            <!-- Explorateur arbre + aperçu -->
            <div
                v-else
                class="flex min-h-0 flex-1 flex-col overflow-hidden lg:flex-row"
            >
                <div
                    class="flex min-h-0 w-full flex-col border-b border-border/80 lg:w-[min(420px,35%)] lg:shrink-0 lg:border-b-0 lg:border-r"
                >
                    <div
                        class="flex items-center justify-between border-b border-border/80 bg-slate-50/80 px-3 py-2.5 dark:bg-muted/30"
                    >
                        <p
                            class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground"
                        >
                            Arborescence
                        </p>
                        <div class="flex gap-1">
                            <button
                                type="button"
                                class="rounded-md px-2 py-0.5 text-[10px] font-medium text-muted-foreground hover:bg-white hover:text-foreground dark:hover:bg-muted"
                                @click="expandAll"
                            >
                                Tout déplier
                            </button>
                            <button
                                type="button"
                                class="rounded-md px-2 py-0.5 text-[10px] font-medium text-muted-foreground hover:bg-white hover:text-foreground dark:hover:bg-muted"
                                @click="collapseAll"
                            >
                                Replier
                            </button>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto py-1 font-mono text-[13px]">
                        <div
                            v-if="treeRoot.children.length === 0"
                            class="px-4 py-16 text-center text-sm text-muted-foreground"
                        >
                            Aucun dossier archivé.
                        </div>
                        <div
                            v-for="node in flatTree"
                            v-else
                            :key="node.id"
                            class="group flex w-full min-w-0 items-center gap-0.5 pr-2 transition"
                            :class="
                                selectedId === node.id
                                    ? 'bg-primary/5 dark:bg-primary/10'
                                    : 'hover:bg-slate-50 dark:hover:bg-muted/40'
                            "
                            :style="{ paddingLeft: `${8 + node.depth * 18}px` }"
                        >
                            <button
                                v-if="hasChildren(node)"
                                type="button"
                                class="flex h-6 w-5 shrink-0 items-center justify-center rounded text-muted-foreground hover:bg-muted"
                                @click.stop="toggleExpand(node.id)"
                            >
                                <ChevronDown v-if="isExpanded(node)" class="size-3.5" />
                                <ChevronRight v-else class="size-3.5" />
                            </button>
                            <span v-else class="inline-block w-5 shrink-0" />

                            <button
                                type="button"
                                class="flex min-w-0 flex-1 items-center gap-2 rounded py-1.5 text-left"
                                @click="onNodeClick(node)"
                            >
                                <FolderOpen
                                    v-if="
                                        node.kind !== 'piece' &&
                                        isExpanded(node) &&
                                        hasChildren(node)
                                    "
                                    class="size-4 shrink-0 text-amber-500"
                                />
                                <Folder
                                    v-else-if="node.kind !== 'piece'"
                                    class="size-4 shrink-0 text-amber-500"
                                />
                                <FileText v-else class="size-4 shrink-0 text-primary" />
                                <span
                                    class="truncate"
                                    :class="
                                        node.kind === 'root'
                                            ? 'font-semibold text-foreground'
                                            : 'text-foreground/90'
                                    "
                                >
                                    {{ node.label }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex min-h-0 flex-1 flex-col bg-slate-50/40 dark:bg-muted/10">
                    <div
                        v-if="!selectedPiece"
                        class="flex flex-1 flex-col items-center justify-center px-8 py-16 text-center"
                    >
                        <div
                            class="mb-4 flex size-16 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                        >
                            <FolderOpen class="size-8" />
                        </div>
                        <p class="text-sm font-medium text-foreground">
                            Sélectionnez un dossier ou une pièce comptable
                        </p>
                        <p class="mt-1 max-w-sm text-xs text-muted-foreground">
                            Dépliez l’arborescence à gauche puis cliquez sur une pièce comptable.
                        </p>
                        <p
                            class="mt-4 rounded-lg border border-dashed border-slate-300 px-3 py-2 text-[11px] text-muted-foreground dark:border-slate-600"
                        >
                            Finance / Operations → Année → Mois → Journée → Agent → Pièce
                        </p>
                    </div>

                    <div v-else class="flex flex-1 flex-col overflow-y-auto">
                        <div class="border-b border-border/80 bg-card px-6 py-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div
                                        class="mb-2 flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                    >
                                        <FileText class="size-5" />
                                    </div>
                                    <h2 class="text-lg font-semibold leading-tight">
                                        {{ selectedPiece.nom_classeur }}
                                    </h2>
                                    <p
                                        v-if="selectedPiece.numero_batch"
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        N° batch {{ selectedPiece.numero_batch }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200"
                                >
                                    Intégré · Archivé
                                </span>
                            </div>
                        </div>

                        <div class="grid gap-px bg-border sm:grid-cols-2">
                            <div class="bg-card px-6 py-4">
                                <p
                                    class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground"
                                >
                                    N° batch
                                </p>
                                <p class="mt-1 flex items-center gap-1.5 text-sm font-medium">
                                    <Hash class="size-3.5 text-primary" />
                                    {{ selectedPiece.numero_batch }}
                                </p>
                            </div>
                            <div class="bg-card px-6 py-4">
                                <p
                                    class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground"
                                >
                                    Date valeur
                                </p>
                                <p class="mt-1 flex items-center gap-1.5 text-sm font-medium">
                                    <CalendarDays class="size-3.5 text-primary" />
                                    {{ dateValeurFmt(selectedPiece.date_valeur) }}
                                </p>
                            </div>
                            <div class="bg-card px-6 py-4">
                                <p
                                    class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground"
                                >
                                    Agent
                                </p>
                                <p class="mt-1 flex items-center gap-1.5 text-sm font-medium">
                                    <User class="size-3.5 text-primary" />
                                    {{ selectedPiece.user_name }}
                                </p>
                            </div>
                            <div class="bg-card px-6 py-4">
                                <p
                                    class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground"
                                >
                                    Archivé le
                                </p>
                                <p class="mt-1 flex items-center gap-1.5 text-sm font-medium">
                                    <Clock class="size-3.5 text-primary" />
                                    {{ horodatage(selectedPiece.archived_at) }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 border-b border-border/80 bg-card px-6 py-4">
                            <Link
                                :href="selectedPiece.resume_url"
                                class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-300 bg-white px-4 text-sm font-medium transition hover:bg-slate-50 dark:border-slate-600 dark:bg-card dark:hover:bg-muted"
                            >
                                <FileSearch class="size-4" />
                                Voir le résumé
                            </Link>
                        </div>

                        <div v-if="selectedPiece.pieces.length" class="flex-1 bg-card px-6 py-4">
                            <p
                                class="mb-3 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                            >
                                Justificatifs ({{ selectedPiece.pieces.length }})
                            </p>
                            <div class="space-y-1">
                                <div
                                    v-for="p in selectedPiece.pieces"
                                    :key="p.id"
                                    class="flex items-center gap-3 rounded-lg border border-transparent px-3 py-2.5 text-sm transition hover:border-slate-200 hover:bg-slate-50 dark:hover:border-slate-700 dark:hover:bg-muted/30"
                                >
                                    <FileText
                                        class="size-4 shrink-0"
                                        :class="
                                            p.is_piece_comptable
                                                ? 'text-primary'
                                                : 'text-muted-foreground'
                                        "
                                    />
                                    <span class="min-w-0 flex-1 truncate">{{
                                        p.description || p.original_name
                                    }}</span>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <a
                                            v-if="p.preview_url"
                                            :href="p.preview_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                                            title="Visualiser"
                                        >
                                            <Eye class="size-3.5" /> Voir
                                        </a>
                                        <a
                                            :href="p.url"
                                            class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground"
                                            title="Télécharger"
                                        >
                                            <Download class="size-3.5" /> Télécharger
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
