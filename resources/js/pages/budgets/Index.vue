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
import { Plus, Trash2 } from 'lucide-vue-next';

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
    rubrique?: string | null;
    sous_rubrique?: string | null;
    date_souhaitee_execution?: string | null;
    justification?: string | null;
    compte_gl?: string | null;
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
    rubriqueSuggestions?: string[];
}

const canEdit = computed(() => props.canEdit !== false);

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Budgets', href: '/budgets' },
];

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
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'XOF',
        maximumFractionDigits: 0,
    }).format(numeric);
};

const lines = computed(() => props.budget?.lines ?? []);
const totalEstime = computed(() => lines.value.reduce((sum, line) => sum + Number(line.montant_estime ?? 0), 0));

const exportQuery = computed(() => {
    const params = new URLSearchParams();
    if (props.selectedDepartmentId) params.set('department_id', String(props.selectedDepartmentId));
    if (props.selectedYear) params.set('year', String(props.selectedYear));
    return params.toString();
});

const canExport = computed(() => Boolean(props.selectedDepartmentId && props.selectedYear && lines.value.length));

const modalOpen = ref(false);
const selectedLine = ref<BudgetLine | null>(null);
const editForm = reactive({
    label: '',
    type: '',
    categorie_depense_id: null as number | null,
    sous_categorie: '',
    rubrique: '',
    sous_rubrique: '',
    montant_estime: null as number | null,
    date_souhaitee_execution: '',
    justification: '',
    compte_gl: '',
});
const isSubmitting = ref(false);
const showDeleteConfirm = ref(false);

const typologies = computed(() => props.typologies ?? []);
const categories = computed(() => props.categories ?? []);
const rubriqueSuggestions = computed(() => props.rubriqueSuggestions ?? []);

const getSousCategories = (categorieId: number | null) => {
    if (!categorieId) return [];
    const cat = categories.value.find(c => c.id === categorieId);
    return cat?.sous_categories ?? [];
};

const openLineModal = (line: BudgetLine) => {
    selectedLine.value = line;
    editForm.label = line.label;
    editForm.type = line.type ?? '';
    editForm.categorie_depense_id = line.categorie_depense_id ?? null;
    editForm.sous_categorie = line.sous_categorie_depense?.sous_categorie ?? '';
    editForm.rubrique = line.rubrique ?? '';
    editForm.sous_rubrique = line.sous_rubrique ?? '';
    editForm.montant_estime = line.montant_estime != null ? Number(line.montant_estime) : null;
    editForm.date_souhaitee_execution = line.date_souhaitee_execution ?? '';
    editForm.justification = line.justification ?? '';
    editForm.compte_gl = line.compte_gl ?? '';
    showDeleteConfirm.value = false;
    modalOpen.value = true;
};

const closeModal = () => {
    modalOpen.value = false;
    selectedLine.value = null;
    showDeleteConfirm.value = false;
};

const saveLine = () => {
    if (!selectedLine.value) return;
    isSubmitting.value = true;
    router.put(`/budget-lines/${selectedLine.value.id}`, {
        label: editForm.label,
        type: editForm.type || null,
        categorie_depense_id: editForm.categorie_depense_id,
        sous_categorie: editForm.sous_categorie || null,
        rubrique: editForm.rubrique || null,
        sous_rubrique: editForm.sous_rubrique || null,
        montant_estime: editForm.montant_estime ?? 0,
        date_souhaitee_execution: editForm.date_souhaitee_execution || null,
        justification: editForm.justification || null,
        compte_gl: editForm.compte_gl || null,
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

</script>

<template>
    <Head title="Budgets" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
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
                        >
                            Export Excel
                        </a>
                        <a
                            :href="canExport ? `/budgets/export/pdf?${exportQuery}` : undefined"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50"
                            :class="{ 'pointer-events-none opacity-50': !canExport }"
                        >
                            Export PDF
                        </a>
                    </template>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-base font-medium text-gray-700">Département</label>
                    <select
                        v-model.number="selectedDepartmentId"
                        class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900"
                    >
                        <option :value="null">-- Sélectionner --</option>
                        <option v-for="department in props.departments" :key="department.id" :value="department.id">
                            {{ department.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-base font-medium text-gray-700">Année</label>
                    <select
                        v-model.number="selectedYear"
                        class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900"
                    >
                        <option :value="null">-- Sélectionner --</option>
                        <option v-for="year in props.years" :key="year" :value="year">
                            {{ year }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                <p v-if="canEdit && !props.isN1View && lines.length > 0" class="px-4 py-2 text-xs text-gray-500">
                    Double-cliquez sur une ligne pour la modifier ou la supprimer.
                </p>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-900 text-white">
                            <tr class="text-xs uppercase">
                                <th class="px-4 py-3 text-left">N°</th>
                                <th class="px-4 py-3 text-left">Code ligne budgétaire</th>
                                <th class="px-4 py-3 text-left">Libellé de la dépense</th>
                                <th class="px-4 py-3 text-left">Type</th>
                                <th class="px-4 py-3 text-left">Catégorie dépense</th>
                                <th class="px-4 py-3 text-left">Sous catégorie</th>
                                <th class="px-4 py-3 text-left">Rubrique dépenses</th>
                                <th class="px-4 py-3 text-left">Sous rubrique</th>
                                <th class="px-4 py-3 text-left">Montant estimé</th>
                                <th class="px-4 py-3 text-left">Montant consommé</th>
                                <th class="px-4 py-3 text-left">Montant stock</th>
                                <th class="px-4 py-3 text-left">Date souhaitée</th>
                                <th class="px-4 py-3 text-left">Justifications</th>
                                <th class="px-4 py-3 text-left">Compte GL</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-if="lines.length === 0">
                                <td colspan="14" class="px-4 py-6 text-center text-sm text-gray-500">
                                    Veuillez sélectionner un département et une année pour afficher les lignes budgétaires.
                                </td>
                            </tr>
                            <tr
                                v-for="(line, index) in lines"
                                :key="line.id"
                                class="bg-green-50/40"
                                :class="{ 'cursor-pointer hover:bg-green-100/60': canEdit && !props.isN1View }"
                                @dblclick="onRowDoubleClick(line)"
                            >
                                <td class="px-4 py-3 text-sm text-gray-900">{{ index + 1 }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ line.code || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ line.label }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ line.type || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ line.categorie_depense?.categorie || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ line.sous_categorie_depense?.sous_categorie || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ line.rubrique || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ line.sous_rubrique || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ formatAmount(line.montant_estime, { showZeroAsDash: true }) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ formatAmount(line.montant_consomme, { showZeroAsDash: true }) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ formatAmount(line.montant_stock, { showZeroAsDash: true }) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ line.date_souhaitee_execution || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 max-w-[200px] truncate" :title="line.justification || undefined">{{ line.justification || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ line.compte_gl || '-' }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="lines.length > 0" class="bg-gray-900 text-white">
                            <tr class="text-sm font-semibold">
                                <td class="px-4 py-3" colspan="8">TOTAL BUDGET</td>
                                <td class="px-4 py-3">{{ formatAmount(totalEstime) }}</td>
                                <td class="px-4 py-3">{{ formatAmount(lines.reduce((s, l) => s + Number(l.montant_consomme ?? 0), 0)) }}</td>
                                <td class="px-4 py-3">{{ formatAmount(lines.reduce((s, l) => s + Number(l.montant_stock ?? 0), 0)) }}</td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3"></td>
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
                        <DialogDescription>
                            Double-cliquez sur une ligne pour l'éditer ou la supprimer.
                        </DialogDescription>
                    </DialogHeader>
                    <div v-if="selectedLine" class="space-y-4 py-4">
                        <p v-if="selectedLine.code" class="text-sm text-gray-500">
                            Code ligne : <strong>{{ selectedLine.code }}</strong>
                        </p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <Label for="edit-label">Libellé de la dépense</Label>
                                <Input id="edit-label" v-model="editForm.label" class="mt-1.5" required />
                            </div>
                            <div>
                                <Label for="edit-type">Type</Label>
                                <select
                                    id="edit-type"
                                    v-model="editForm.type"
                                    class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1"
                                >
                                    <option value="">-- Sélectionner --</option>
                                    <option v-for="t in typologies" :key="t.type" :value="t.type">
                                        {{ t.type }} - {{ t.libelle }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <Label for="edit-categorie">Catégorie dépense</Label>
                                <select
                                    id="edit-categorie"
                                    v-model="editForm.categorie_depense_id"
                                    class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1"
                                    @change="editForm.sous_categorie = ''"
                                >
                                    <option :value="null">-- Sélectionner --</option>
                                    <option v-for="c in categories" :key="c.id" :value="c.id">
                                        {{ c.categorie }} ({{ c.code }})
                                    </option>
                                </select>
                            </div>
                            <div>
                                <Label for="edit-sous-categorie">Sous catégorie</Label>
                                <Input
                                    id="edit-sous-categorie"
                                    v-model="editForm.sous_categorie"
                                    type="text"
                                    class="mt-1.5"
                                    :list="'edit-sous-categorie-list'"
                                    placeholder="Saisir ou sélectionner une sous-catégorie..."
                                    :disabled="!editForm.categorie_depense_id"
                                />
                                <datalist id="edit-sous-categorie-list">
                                    <option v-for="sc in getSousCategories(editForm.categorie_depense_id)" :key="sc.id" :value="sc.sous_categorie" />
                                </datalist>
                            </div>
                            <div>
                                <Label for="edit-rubrique">Rubrique dépenses</Label>
                                <Input
                                    id="edit-rubrique"
                                    v-model="editForm.rubrique"
                                    :list="'edit-rubrique-list'"
                                    class="mt-1.5"
                                    placeholder="Saisir une rubrique..."
                                />
                                <datalist id="edit-rubrique-list">
                                    <option v-for="s in rubriqueSuggestions" :key="s" :value="s" />
                                </datalist>
                            </div>
                            <div>
                                <Label for="edit-sous-rubrique">Sous rubrique</Label>
                                <Input id="edit-sous-rubrique" v-model="editForm.sous_rubrique" class="mt-1.5" />
                            </div>
                            <div>
                                <Label for="edit-montant">Montant estimé</Label>
                                <Input
                                    id="edit-montant"
                                    v-model="editForm.montant_estime"
                                    type="number"
                                    step="0.01"
                                    class="mt-1.5"
                                />
                            </div>
                            <div>
                                <Label for="edit-date">Date souhaitée d'exécution</Label>
                                <textarea
                                    id="edit-date"
                                    v-model="editForm.date_souhaitee_execution"
                                    rows="2"
                                    class="mt-1.5 w-full rounded-md border border-gray-300 px-3 py-2"
                                />
                            </div>
                        </div>
                        <div>
                            <Label for="edit-justification">Justifications</Label>
                            <textarea
                                id="edit-justification"
                                v-model="editForm.justification"
                                rows="2"
                                class="mt-1.5 w-full rounded-md border border-gray-300 px-3 py-2"
                            />
                        </div>
                        <div>
                            <Label for="edit-compte-gl">Compte GL</Label>
                            <Input id="edit-compte-gl" v-model="editForm.compte_gl" class="mt-1.5" />
                        </div>
                        <div v-if="showDeleteConfirm" class="rounded-md border border-red-200 bg-red-50 p-4">
                            <p class="text-sm font-medium text-red-800">Confirmer la suppression de cette ligne ?</p>
                            <div class="mt-2 flex gap-2">
                                <Button variant="destructive" size="sm" :disabled="isSubmitting" @click="deleteLine">
                                    Oui, supprimer
                                </Button>
                                <Button variant="outline" size="sm" @click="showDeleteConfirm = false">
                                    Annuler
                                </Button>
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            v-if="!showDeleteConfirm"
                            variant="destructive"
                            class="mr-auto"
                            @click="showDeleteConfirm = true"
                        >
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
