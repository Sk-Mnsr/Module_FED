<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { computed, ref, reactive } from 'vue';
import { Plus, Trash2, ChevronDown, ChevronRight } from 'lucide-vue-next';

interface BudgetLine {
    id: number;
    code?: string | null;
    label: string;
    montant_estime: number;
    montant_consomme?: number;
    montant_stock?: number;
    type?: string | null;
    categorie_depense_id?: number | null;
    sous_categorie_depense_id?: number | null;
    categorie_depense?: { categorie: string; code: string } | null;
    sous_categorie_depense?: { sous_categorie: string; code: string } | null;
    date_souhaitee_execution?: string | null;
    justification?: string | null;
    compte_gl?: string | null;
    responsable?: string | null;
    article_id?: number | null;
    is_global?: boolean;
    global_line_id?: number | null;
    agence_id?: number | null;
    agence?: { nom: string; code: string } | null;
    article?: {
        id: number;
        code: string;
        description: string;
        sous_categorie?: {
            id: number;
            nom: string;
            categorie?: {
                id: number;
                nom: string;
                famille?: {
                    id: number;
                    nom: string;
                };
            };
        };
    } | null;
    entity_lines?: BudgetLine[];
}

interface Budget {
    id: number;
    department_id: number;
    year: number;
    lines: BudgetLine[];
}

interface Props {
    departments: Array<{ id: number; name: string }>;
    years: number[];
    selectedDepartmentId?: number | null;
    selectedYear?: number | null;
    budget?: Budget | null;
    isN1View?: boolean;
    canEdit?: boolean;
    typologies?: Array<{ type: string; libelle: string }>;
    categories?: Array<{ id: number; categorie: string; code: string; sous_categories: Array<{ id: number; sous_categorie: string; code: string }> }>;
    articles?: Array<{
        id: number;
        code: string;
        description: string;
        responsable?: string;
        type_depense?: { id: number; nom_depense: string; compte_gl?: string };
        sous_categorie?: {
            id: number;
            sous_categorie: string;
            categorie_id: number;
            categorie?: { id: number; categorie: string; code: string };
        };
    }>;
    agences?: Array<{ id: number; code: string; nom: string }>;
}

const props = defineProps<Props>();
const canEdit = computed(() => props.canEdit !== false);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Budgets', href: '/budgets' }];

const selectedDepartmentId = computed({
    get: () => props.selectedDepartmentId ?? null,
    set: value => applyFilters(value, props.selectedYear ?? null),
});

const selectedYear = computed({
    get: () => props.selectedYear ?? null,
    set: value => applyFilters(props.selectedDepartmentId ?? null, value),
});

const applyFilters = (departmentId: number | null, year: number | null) => {
    const params = new URLSearchParams();
    if (departmentId) params.set('department_id', String(departmentId));
    if (year) params.set('year', String(year));
    const basePath = props.isN1View ? '/budgets/n1' : '/budgets';
    router.get(`${basePath}?${params.toString()}`, {}, { preserveScroll: true, preserveState: true });
};

const formatAmount = (value?: number | string | null, options: { showZeroAsDash?: boolean } = {}) => {
    const numeric = value === null || value === undefined ? NaN : Number(value);
    if (Number.isNaN(numeric)) return '-';
    if (options.showZeroAsDash && numeric === 0) return '-';
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', maximumFractionDigits: 0 }).format(numeric);
};

// Séparer lignes globales et lignes entités
const allLines = computed(() => props.budget?.lines ?? []);
const globalLines = computed(() => allLines.value.filter(l => l.is_global));
const entityLinesMap = computed(() => {
    const map: Record<number, BudgetLine[]> = {};
    allLines.value.filter(l => !l.is_global && l.global_line_id).forEach(l => {
        const pid = l.global_line_id!;
        if (!map[pid]) map[pid] = [];
        map[pid].push(l);
    });
    return map;
});

const totalEstime = computed(() => globalLines.value.reduce((sum, l) => sum + Number(l.montant_estime ?? 0), 0));

// Expand/collapse des lignes entité
const expandedLines = ref<Set<number>>(new Set());
const toggleExpand = (id: number) => {
    if (expandedLines.value.has(id)) expandedLines.value.delete(id);
    else expandedLines.value.add(id);
};

const exportQuery = computed(() => {
    const params = new URLSearchParams();
    if (props.selectedDepartmentId) params.set('department_id', String(props.selectedDepartmentId));
    if (props.selectedYear) params.set('year', String(props.selectedYear));
    return params.toString();
});

const canExport = computed(() => Boolean(props.selectedDepartmentId && props.selectedYear && globalLines.value.length));

// Modal édition
const modalOpen = ref(false);
const selectedLine = ref<BudgetLine | null>(null);
const editForm = reactive({
    label: '',
    type: '',
    categorie_depense_id: null as number | null,
    montant_estime: undefined as number | undefined,
    date_souhaitee_execution: '',
    justification: '',
    compte_gl: '',
    responsable: '' as string,
    article_id: null as number | null,
    article_categorie_name: '',
    article_sous_categorie_name: '',
});
const isSubmitting = ref(false);
const showDeleteConfirm = ref(false);

const typologies = computed(() => props.typologies ?? []);
const categories = computed(() => props.categories ?? []);

const getSousCategories = (categorieId: number | null) => {
    if (!categorieId) return [];
    return categories.value.find(c => c.id === categorieId)?.sous_categories ?? [];
};

const openLineModal = (line: BudgetLine) => {
    selectedLine.value = line;
    editForm.label = line.label;
    editForm.type = line.type ?? '';
    editForm.categorie_depense_id = line.categorie_depense_id ?? null;
    editForm.montant_estime = line.montant_estime != null ? Number(line.montant_estime) : undefined;
    editForm.date_souhaitee_execution = line.date_souhaitee_execution ?? '';
    editForm.justification = line.justification ?? '';
    editForm.compte_gl = line.compte_gl ?? '';
    editForm.responsable = line.responsable ?? '';
    editForm.article_id = line.article_id ?? null;
    editForm.article_categorie_name = (line as any).article?.sous_categorie?.categorie?.categorie || '--';
    editForm.article_sous_categorie_name = (line as any).article?.sous_categorie?.sous_categorie || '--';
    
    showDeleteConfirm.value = false;
    modalOpen.value = true;
};

const onArticleChange = () => {
    if (!editForm.article_id || !props.articles) return;
    const article = (props.articles as any[]).find(a => a.id === editForm.article_id);
    if (article) {
        if (article.responsable) editForm.responsable = article.responsable;
        
        // Article categorization (pre-defined)
        editForm.article_categorie_name = article.sous_categorie?.categorie?.categorie || '--';
        editForm.article_sous_categorie_name = article.sous_categorie?.sous_categorie || '--';

        if (article.type_depense && props.typologies) {
            const typology = props.typologies.find(t =>
                t.type.toLowerCase() === article.type_depense?.nom_depense.toLowerCase() ||
                t.libelle.toLowerCase() === article.type_depense?.nom_depense.toLowerCase()
            );
            if (typology) editForm.type = typology.type;
        }
        if (article.type_depense?.compte_gl) {
            editForm.compte_gl = article.type_depense.compte_gl;
        }
    } else {
        editForm.article_categorie_name = '';
        editForm.article_sous_categorie_name = '';
    }
};

const closeModal = () => { modalOpen.value = false; selectedLine.value = null; showDeleteConfirm.value = false; };

const saveLine = () => {
    if (!selectedLine.value) return;
    isSubmitting.value = true;
    router.put(`/budget-lines/${selectedLine.value.id}`, {
        label: editForm.label,
        type: editForm.type || null,
        categorie_depense_id: editForm.categorie_depense_id,
        montant_estime: editForm.montant_estime ?? 0,
        date_souhaitee_execution: editForm.date_souhaitee_execution || null,
        justification: editForm.justification || null,
        compte_gl: editForm.compte_gl || null,
        responsable: editForm.responsable || null,
        article_id: editForm.article_id,
    }, {
        preserveScroll: true,
        onFinish: () => { isSubmitting.value = false; closeModal(); },
    });
};

const deleteLine = () => {
    if (!selectedLine.value) return;
    isSubmitting.value = true;
    router.delete(`/budget-lines/${selectedLine.value.id}`, {
        preserveScroll: true,
        onFinish: () => { isSubmitting.value = false; closeModal(); },
    });
};

const onRowDoubleClick = (line: BudgetLine) => {
    if (canEdit.value && !props.isN1View) openLineModal(line);
};

const responsableBadge = (r?: string | null) => {
    const map: Record<string, string> = {
        IT: 'bg-blue-100 text-blue-800',
        Facilities: 'bg-amber-100 text-amber-800',
        RH: 'bg-green-100 text-green-800',
    };
    return r ? (map[r] ?? 'bg-gray-100 text-gray-700') : '';
};
</script>

<template>
    <Head title="Budgets" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <!-- En-tête -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Budgets</h1>
                    <p class="text-sm text-gray-500">Paramétrage annuel et suivi par département.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <template v-if="canEdit && !props.isN1View">
                        <Link href="/budgets/create">
                            <Button class="bg-purple-600 hover:bg-purple-700">
                                <Plus class="mr-2 h-4 w-4" /> Nouveau
                            </Button>
                        </Link>
                        <a
                            :href="canExport ? `/budgets/export/excel?${exportQuery}` : undefined"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50"
                            :class="{ 'pointer-events-none opacity-50': !canExport }"
                        >Export Excel</a>
                        <a
                            :href="canExport ? `/budgets/export/pdf?${exportQuery}` : undefined"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50"
                            :class="{ 'pointer-events-none opacity-50': !canExport }"
                        >Export PDF</a>
                    </template>
                </div>
            </div>

            <!-- Filtres -->
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-base font-medium text-gray-700">Département</label>
                    <select v-model.number="selectedDepartmentId"
                        class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900">
                        <option :value="null">-- Sélectionner --</option>
                        <option v-for="dept in props.departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-base font-medium text-gray-700">Année</label>
                    <select v-model.number="selectedYear"
                        class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900">
                        <option :value="null">-- Sélectionner --</option>
                        <option v-for="year in props.years" :key="year" :value="year">{{ year }}</option>
                    </select>
                </div>
            </div>

            <!-- Tableau des lignes budgétaires -->
            <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-900 text-white">
                            <tr class="text-xs uppercase">
                                <th class="px-4 py-3 text-left w-8"></th>
                                <th class="px-4 py-3 text-left">N°</th>
                                <th class="px-4 py-3 text-left">Code ligne</th>
                                <th class="px-4 py-3 text-left">Libellé de la dépense</th>
                                <th class="px-4 py-3 text-left">Responsable</th>
                                <th class="px-4 py-3 text-left">Famille</th>
                                <th class="px-4 py-3 text-left">Catégorie</th>
                                <th class="px-4 py-3 text-left">Montant estimé</th>
                                <th class="px-4 py-3 text-left">Montant consommé</th>
                                <th class="px-4 py-3 text-left">Montant stock</th>
                                <th class="px-4 py-3 text-left">Compte GL</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-if="globalLines.length === 0">
                                <td colspan="11" class="px-4 py-6 text-center text-sm text-gray-500">
                                    Veuillez sélectionner un département et une année pour afficher les lignes budgétaires.
                                </td>
                            </tr>
                            <template v-for="(line, index) in globalLines" :key="line.id">
                                <!-- Ligne globale -->
                                <tr
                                    class="bg-purple-50/40 font-medium"
                                    :class="{ 'cursor-pointer hover:bg-purple-100/60': canEdit && !props.isN1View }"
                                    @dblclick="onRowDoubleClick(line)"
                                >
                                    <td class="px-2 py-3 text-center">
                                        <button
                                            v-if="entityLinesMap[line.id]?.length"
                                            class="text-gray-400 hover:text-gray-700"
                                            @click.stop="toggleExpand(line.id)"
                                        >
                                            <ChevronDown v-if="expandedLines.has(line.id)" class="h-4 w-4" />
                                            <ChevronRight v-else class="h-4 w-4" />
                                        </button>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ index + 1 }}</td>
                                    <td class="px-4 py-3 text-sm font-mono text-purple-700 whitespace-nowrap">{{ line.code || '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ line.label }}</td>
                                    <td class="px-4 py-3">
                                        <span v-if="line.responsable" :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', responsableBadge(line.responsable)]">
                                            {{ line.responsable }}
                                        </span>
                                        <span v-else class="text-gray-400 text-sm">—</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 italic">
                                        {{ line.article?.sous_categorie?.categorie?.famille?.nom || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ line.article?.sous_categorie?.categorie?.nom || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ formatAmount(line.montant_estime, { showZeroAsDash: true }) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ formatAmount(line.montant_consomme, { showZeroAsDash: true }) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ formatAmount(line.montant_stock, { showZeroAsDash: true }) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ line.compte_gl || '-' }}</td>
                                </tr>
                                <!-- Lignes entité et Détails (expandables) -->
                                <template v-if="expandedLines.has(line.id)">
                                    <!-- Bloc Détails de la ligne globale -->
                                    <tr class="bg-gray-50 border-l-4 border-l-blue-400">
                                        <td class="px-2 py-3"></td>
                                        <td class="px-4 py-3" colspan="12">
                                            <div class="grid grid-cols-2 gap-4 text-xs">
                                                <div>
                                                    <span class="font-semibold text-gray-500 uppercase tracking-wider">Type :</span>
                                                    <span class="ml-2 text-gray-900">{{ line.type || '-' }}</span>
                                                </div>
                                                <div>
                                                    <span class="font-semibold text-gray-500 uppercase tracking-wider">Catégorie dépense :</span>
                                                    <span class="ml-2 text-gray-900">{{ line.categorie_depense?.categorie || '-' }}</span>
                                                </div>
                                                <div v-if="line.date_souhaitee_execution" class="col-span-2">
                                                    <span class="font-semibold text-gray-500 uppercase tracking-wider">Date souhaitée :</span>
                                                    <span class="ml-2 text-gray-900 whitespace-pre-wrap">{{ line.date_souhaitee_execution }}</span>
                                                </div>
                                                <div v-if="line.justification" class="col-span-2">
                                                    <span class="font-semibold text-gray-500 uppercase tracking-wider">Justification :</span>
                                                    <span class="ml-2 text-gray-900 whitespace-pre-wrap">{{ line.justification }}</span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <!-- Lignes entité -->
                                    <tr
                                        v-for="entityLine in entityLinesMap[line.id]"
                                        :key="entityLine.id"
                                        class="bg-gray-50 border-l-4 border-l-purple-300"
                                    >
                                        <td class="px-2 py-2"></td>
                                        <td class="px-4 py-2"></td>
                                        <td class="px-4 py-2 text-xs font-mono text-gray-500 whitespace-nowrap">{{ entityLine.code || '-' }}</td>
                                        <td class="px-4 py-2 text-xs text-gray-600">
                                            <span class="mr-1.5 inline-flex items-center rounded bg-gray-200 px-1.5 py-0.5 text-xs font-medium text-gray-700">
                                                {{ entityLine.agence?.nom ?? entityLine.agence?.code ?? '?' }}
                                            </span>
                                            {{ entityLine.label }}
                                        </td>
                                        <td class="px-4 py-2">
                                            <span v-if="entityLine.responsable" :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', responsableBadge(entityLine.responsable)]">
                                                {{ entityLine.responsable }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-xs text-gray-500 italic">
                                            {{ entityLine.article?.sous_categorie?.categorie?.famille?.nom || '-' }}
                                        </td>
                                        <td class="px-4 py-2 text-xs text-gray-500">
                                            {{ entityLine.article?.sous_categorie?.categorie?.nom || '-' }}
                                        </td>
                                        <td class="px-4 py-2 text-xs text-gray-500">{{ formatAmount(entityLine.montant_estime, { showZeroAsDash: true }) }}</td>
                                        <td class="px-4 py-2 text-xs text-gray-500">{{ formatAmount(entityLine.montant_consomme, { showZeroAsDash: true }) }}</td>
                                        <td class="px-4 py-2 text-xs text-gray-500">{{ formatAmount(entityLine.montant_stock, { showZeroAsDash: true }) }}</td>
                                        <td class="px-4 py-2 text-xs text-gray-500">{{ entityLine.compte_gl || '-' }}</td>
                                    </tr>
                                </template>
                            </template>
                        </tbody>
                        <tfoot v-if="globalLines.length > 0" class="bg-gray-900 text-white">
                            <tr class="text-sm font-semibold">
                                <td class="px-4 py-3" colspan="7">TOTAL BUDGET</td>
                                <td class="px-4 py-3">{{ formatAmount(totalEstime) }}</td>
                                <td class="px-4 py-3">{{ formatAmount(globalLines.reduce((s, l) => s + Number(l.montant_consomme ?? 0), 0)) }}</td>
                                <td class="px-4 py-3">{{ formatAmount(globalLines.reduce((s, l) => s + Number(l.montant_stock ?? 0), 0)) }}</td>
                                <td class="px-4 py-3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Modal Modifier / Supprimer ligne -->
            <Dialog v-model:open="modalOpen" @update:open="(v: boolean) => !v && closeModal()">
                <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Modifier ou supprimer la ligne</DialogTitle>
                        <DialogDescription>Double-cliquez sur une ligne globale pour l'éditer ou la supprimer.</DialogDescription>
                    </DialogHeader>
                    <div v-if="selectedLine" class="space-y-4 py-4">
                        <p v-if="selectedLine.code" class="text-sm text-gray-500">
                            Code ligne : <strong class="font-mono text-purple-700">{{ selectedLine.code }}</strong>
                        </p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <Label for="edit-label">Libellé de la dépense</Label>
                                <Input id="edit-label" v-model="editForm.label" class="mt-1.5" required />
                            </div>
                            <div>
                                <Label for="edit-type">Type</Label>
                                <select id="edit-type" v-model="editForm.type"
                                    class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1">
                                    <option value="">-- Sélectionner --</option>
                                    <option v-for="t in typologies" :key="t.type" :value="t.type">{{ t.type }} - {{ t.libelle }}</option>
                                </select>
                            </div>
                             <div>
                                <Label for="edit-article">Article (code ligne)</Label>
                                <select id="edit-article" v-model="editForm.article_id"
                                    class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1"
                                    @change="onArticleChange">
                                    <option :value="null">-- Sélectionner --</option>
                                    <option v-for="art in props.articles" :key="art.id" :value="art.id">
                                        {{ art.code }} — {{ art.description }}
                                    </option>
                                </select>
                                <div v-if="editForm.article_id" class="mt-1.5 flex flex-wrap gap-2 text-xs text-blue-600 italic">
                                    <span>Cat. Article: {{ editForm.article_categorie_name }}</span>
                                    <span>/</span>
                                    <span>Sous-cat. Article: {{ editForm.article_sous_categorie_name }}</span>
                                </div>
                            </div>
                             <div>
                                <Label for="edit-responsable">Responsable</Label>
                                <select id="edit-responsable" v-model="editForm.responsable"
                                    class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1">
                                    <option value="">-- Sélectionner --</option>
                                    <option value="IT">IT</option>
                                    <option value="Facilities">Facilities</option>
                                    <option value="RH">RH</option>
                                </select>
                            </div>
                            <div>
                                <Label for="edit-categorie">Catégorie dépense</Label>
                                <select id="edit-categorie" v-model="editForm.categorie_depense_id"
                                    class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1">
                                    <option :value="null">-- Sélectionner --</option>
                                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.categorie }} ({{ c.code }})</option>
                                </select>
                            </div>
                            <div>
                                <Label for="edit-montant">Montant estimé</Label>
                                <Input id="edit-montant" v-model="editForm.montant_estime" type="number" step="0.01" class="mt-1.5" />
                            </div>
                            <div>
                                <Label for="edit-date">Date souhaitée d'exécution</Label>
                                <textarea id="edit-date" v-model="editForm.date_souhaitee_execution" rows="2"
                                    class="mt-1.5 w-full rounded-md border border-gray-300 px-3 py-2" />
                            </div>
                        </div>
                        <div>
                            <Label for="edit-justification">Justifications</Label>
                            <textarea id="edit-justification" v-model="editForm.justification" rows="2"
                                class="mt-1.5 w-full rounded-md border border-gray-300 px-3 py-2" />
                        </div>
                        <div>
                            <Label for="edit-compte-gl">Compte GL</Label>
                            <Input id="edit-compte-gl" v-model="editForm.compte_gl" class="mt-1.5" />
                        </div>
                        <div v-if="showDeleteConfirm" class="rounded-md border border-red-200 bg-red-50 p-4">
                            <p class="text-sm font-medium text-red-800">Confirmer la suppression de cette ligne et toutes ses lignes entité ?</p>
                            <div class="mt-2 flex gap-2">
                                <Button variant="destructive" size="sm" :disabled="isSubmitting" @click="deleteLine">Oui, supprimer</Button>
                                <Button variant="outline" size="sm" @click="showDeleteConfirm = false">Annuler</Button>
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button v-if="!showDeleteConfirm" variant="destructive" class="mr-auto" @click="showDeleteConfirm = true">
                            <Trash2 class="mr-2 h-4 w-4" /> Supprimer
                        </Button>
                        <div class="flex gap-2">
                            <Button variant="outline" @click="closeModal">Annuler</Button>
                            <Button :disabled="isSubmitting" @click="saveLine">
                                {{ isSubmitting ? 'Enregistrement...' : 'Enregistrer' }}
                            </Button>
                        </div>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
