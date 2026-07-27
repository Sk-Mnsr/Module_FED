<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import DataTable from '@/components/DataTable.vue';
import { Pencil, Trash2, Plus, Upload, Download, Search, X } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from '@/components/ui/dialog';

// ─── Types ────────────────────────────────────────────────────────────────────
const RESPONSABLES = ['IT', 'Facilities', 'RH', 'ALL'] as const;
type Responsable = typeof RESPONSABLES[number];

interface SousCategorie { id: number; nom: string; categorie_id: number; }
interface Categorie { id: number; nom: string; famille_id: number; sous_categories: SousCategorie[]; }
interface Famille { id: number; nom: string; categories: Categorie[]; }
interface TypeDepense { id: number; nom_depense: string; }

const props = defineProps<{
    articles: { data: Array<any>; current_page: number; per_page: number; total: number; };
    familles: Famille[];
    typeDepenses: TypeDepense[];
    filters?: {
        q?: string;
        responsable?: string;
        famille_id?: number | null;
        type_depense_id?: number | null;
        per_page?: number;
    };
}>();

const breadcrumbs = [
    { title: 'Configuration', href: '#' },
    { title: 'Articles', href: '/articles' },
];

const searchForm = ref({
    q: props.filters?.q ?? '',
    responsable: props.filters?.responsable ?? '',
    famille_id: props.filters?.famille_id ? String(props.filters.famille_id) : '',
    type_depense_id: props.filters?.type_depense_id ? String(props.filters.type_depense_id) : '',
});

const hasActiveFilters = computed(() =>
    Boolean(searchForm.value.q || searchForm.value.responsable || searchForm.value.famille_id || searchForm.value.type_depense_id),
);

function filterParams(extra: Record<string, string | number> = {}) {
    const params: Record<string, string | number> = {
        per_page: props.filters?.per_page ?? props.articles.per_page ?? 10,
        ...extra,
    };
    if (searchForm.value.q.trim()) params.q = searchForm.value.q.trim();
    if (searchForm.value.responsable) params.responsable = searchForm.value.responsable;
    if (searchForm.value.famille_id) params.famille_id = searchForm.value.famille_id;
    if (searchForm.value.type_depense_id) params.type_depense_id = searchForm.value.type_depense_id;
    return params;
}

function applyFilters() {
    router.get('/articles', filterParams({ page: 1 }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters() {
    searchForm.value = { q: '', responsable: '', famille_id: '', type_depense_id: '' };
    router.get('/articles', {
        per_page: props.filters?.per_page ?? props.articles.per_page ?? 10,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function onPageChange(page: number) {
    router.get('/articles', filterParams({ page }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function onItemsPerPageChange(items: number) {
    router.get('/articles', filterParams({ page: 1, per_page: items }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const columns = [
    { key: 'code', title: 'Code', sortable: true },
    { key: 'description', title: 'Description', sortable: true },
    { key: 'responsable', title: 'Responsable Dépenses' },
    { key: 'categorie', title: 'Classification' },
    { key: 'type_depense', title: 'Type de dépense' },
    { key: 'actions', title: 'Actions' }
];

const badgeColor = (r: string) => {
    const map: Record<string, string> = {
        IT: 'bg-blue-100 text-blue-800',
        Facilities: 'bg-amber-100 text-amber-800',
        RH: 'bg-green-100 text-green-800',
        ALL: 'bg-gray-100 text-gray-700',
    };
    return map[r] ?? 'bg-gray-100 text-gray-700';
};

// ─── Formulaire ───────────────────────────────────────────────────────────────
const showModal = ref(false);
const isEditing = ref(false);
const form = ref({
    id: null as number | null,
    code: '',
    description: '',
    responsable: 'ALL' as Responsable,
    famille_id: null as number | null,
    categorie_id: null as number | null,
    sous_categorie_id: null as number | null,
    type_depense_id: null as number | null,
    stock_actuel: 0,
    seuil_alerte: 5,
});

// Filtrage dynamique en cascade
const filteredCategories = computed<Categorie[]>(() => {
    if (!form.value.famille_id) return [];
    return props.familles.find(f => f.id === form.value.famille_id)?.categories ?? [];
});

const filteredSousCategories = computed<SousCategorie[]>(() => {
    if (!form.value.categorie_id) return [];
    return filteredCategories.value.find(c => c.id === form.value.categorie_id)?.sous_categories ?? [];
});

watch(() => form.value.famille_id, () => {
    form.value.categorie_id = null;
    form.value.sous_categorie_id = null;
});
watch(() => form.value.categorie_id, () => {
    form.value.sous_categorie_id = null;
});

// Initialiser les selects depuis l'article existant (remonte la hiérarchie)
const resolveHierarchy = (article: any) => {
    const sc = article.sous_categorie;
    if (sc) {
        form.value.categorie_id = sc.categorie_id;
        const cat = props.familles.flatMap(f => f.categories).find(c => c.id === sc.categorie_id);
        form.value.famille_id = cat?.famille_id ?? null;
    }
};

const openCreateModal = () => {
    isEditing.value = false;
    form.value = { id: null, code: '', description: '', responsable: 'ALL', famille_id: null, categorie_id: null, sous_categorie_id: null, type_depense_id: null, stock_actuel: 0, seuil_alerte: 5 };
    showModal.value = true;
};

const openEditModal = (article: any) => {
    isEditing.value = true;
    form.value = {
        id: article.id,
        code: article.code,
        description: article.description,
        responsable: article.responsable,
        famille_id: null,
        categorie_id: null,
        sous_categorie_id: article.sous_categorie_id ?? null,
        type_depense_id: article.type_depense_id ?? null,
        stock_actuel: article.stock_actuel ?? 0,
        seuil_alerte: article.seuil_alerte ?? 5,
    };
    resolveHierarchy(article);
    showModal.value = true;
};

const deleteArticle = (id: number) => {
    if (confirm('Supprimer cet article ?'))
        router.delete(`/articles/${id}`, { preserveScroll: true });
};

const submitForm = () => {
    const payload = {
        code: form.value.code,
        description: form.value.description,
        responsable: form.value.responsable,
        sous_categorie_id: form.value.sous_categorie_id,
        type_depense_id: form.value.type_depense_id,
        stock_actuel: form.value.stock_actuel,
        seuil_alerte: form.value.seuil_alerte,
    };
    if (isEditing.value) {
        router.put(`/articles/${form.value.id}`, payload, { onSuccess: () => showModal.value = false });
    } else {
        router.post('/articles', payload, { onSuccess: () => showModal.value = false });
    }
};

const breadcrumbLabel = (article: any) => {
    const sc = article.sous_categorie;
    if (!sc) return '—';
    const cat = props.familles.flatMap(f => f.categories).find(c => c.id === sc.categorie_id);
    const fam = cat ? props.familles.find(f => f.id === cat.famille_id) : null;
    return [fam?.nom, cat?.nom, sc.nom].filter(Boolean).join(' / ');
};

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string; warning?: string } | undefined);

const showImportModal = ref(false);
const importFile = ref<File | null>(null);
const importProcessing = ref(false);
const importFileKey = ref(0);

function openImportModal() {
    importFile.value = null;
    importFileKey.value += 1;
    showImportModal.value = true;
}

function onImportFileChange(e: Event) {
    const input = e.target as HTMLInputElement;
    importFile.value = input.files?.[0] ?? null;
}

function submitImport() {
    if (!importFile.value) return;
    importProcessing.value = true;
    router.post('/articles/import', { file: importFile.value }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showImportModal.value = false;
            importFile.value = null;
        },
        onFinish: () => {
            importProcessing.value = false;
        },
    });
}
</script>

<template>
    <Head title="Gestion des Articles" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div
                v-if="flash?.success"
                class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
            >
                {{ flash.success }}
            </div>
            <div
                v-if="flash?.warning"
                class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
            >
                {{ flash.warning }}
            </div>
            <div
                v-if="flash?.error"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
            >
                {{ flash.error }}
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-3xl font-bold text-gray-900">Articles</h1>
                <div class="flex flex-wrap items-center gap-2">
                    <a
                        href="/articles/export-template"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50"
                    >
                        <Download class="mr-2 h-4 w-4" />
                        Modèle CSV
                    </a>
                    <Button type="button" variant="outline" @click="openImportModal">
                        <Upload class="mr-2 h-4 w-4" />
                        Import
                    </Button>
                    <Button @click="openCreateModal" class="bg-purple-600 hover:bg-purple-700">
                        <Plus class="mr-2 h-4 w-4" /> Nouveau Article
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-5" @submit.prevent="applyFilters">
                    <div class="space-y-1.5 xl:col-span-2">
                        <Label for="filter-q">Recherche</Label>
                        <div class="relative">
                            <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="filter-q"
                                v-model="searchForm.q"
                                type="search"
                                placeholder="Code ou description…"
                                class="pl-9"
                            />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="filter-responsable">Responsable Dépenses</Label>
                        <select
                            id="filter-responsable"
                            v-model="searchForm.responsable"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">Tous</option>
                            <option v-for="r in RESPONSABLES" :key="r" :value="r">{{ r }}</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="filter-famille">Famille</Label>
                        <select
                            id="filter-famille"
                            v-model="searchForm.famille_id"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">Toutes</option>
                            <option v-for="f in familles" :key="f.id" :value="String(f.id)">{{ f.nom }}</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="filter-type">Type de dépense</Label>
                        <select
                            id="filter-type"
                            v-model="searchForm.type_depense_id"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">Tous</option>
                            <option v-for="td in typeDepenses" :key="td.id" :value="String(td.id)">{{ td.nom_depense }}</option>
                        </select>
                    </div>
                    <div class="flex flex-wrap items-end gap-2 md:col-span-2 xl:col-span-5">
                        <Button type="submit" class="bg-purple-600 hover:bg-purple-700">
                            <Search class="mr-2 h-4 w-4" />
                            Filtrer
                        </Button>
                        <Button
                            v-if="hasActiveFilters"
                            type="button"
                            variant="outline"
                            @click="resetFilters"
                        >
                            <X class="mr-2 h-4 w-4" />
                            Réinitialiser
                        </Button>
                    </div>
                </form>
            </div>

            <DataTable
                :headers="columns"
                :items="props.articles.data"
                :current-page="props.articles.current_page"
                :items-per-page="props.articles.per_page"
                :total-items="props.articles.total"
                :show-select="false"
                :on-page-change="onPageChange"
                :on-items-per-page-change="onItemsPerPageChange"
            >
                <template #item.responsable="{ item }">
                    <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', badgeColor(item.responsable)]">
                        {{ item.responsable }}
                    </span>
                </template>
                <template #item.categorie="{ item }">
                    <span class="text-sm text-gray-600">{{ breadcrumbLabel(item) }}</span>
                </template>
                <template #item.type_depense="{ item }">
                    <span class="text-sm text-gray-600">{{ item.type_depense?.nom_depense ?? '—' }}</span>
                </template>
                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <button @click="openEditModal(item)" class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100">
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button @click="deleteArticle(item.id)" class="inline-flex items-center justify-center rounded-md p-2 text-red-600 hover:bg-red-50">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Modal Créer / Éditer -->
        <Dialog :open="showModal" @update:open="showModal = $event">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>{{ isEditing ? 'Modifier l\'Article' : 'Ajouter un Article' }}</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submitForm" class="space-y-4 py-4">

                    <!-- Code & Description -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label for="code">Code <span class="text-red-500">*</span></Label>
                            <Input id="code" v-model="form.code" required placeholder="Ex: ART001" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="responsable">Responsable Dépenses <span class="text-red-500">*</span></Label>
                            <select id="responsable" v-model="form.responsable"
                                class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm">
                                <option v-for="r in RESPONSABLES" :key="r" :value="r">{{ r }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="description">Description <span class="text-red-500">*</span></Label>
                        <Input id="description" v-model="form.description" required placeholder="Description de l'article" />
                    </div>

                    <!-- Stock Initial & Seuil -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label for="stock_actuel">Stock Initial</Label>
                            <Input id="stock_actuel" type="number" v-model="form.stock_actuel" min="0" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="seuil_alerte">Seuil d'Alerte</Label>
                            <Input id="seuil_alerte" type="number" v-model="form.seuil_alerte" min="0" />
                        </div>
                    </div>

                    <!-- Hiérarchie en cascade -->
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 space-y-3">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Classification</p>

                        <div class="space-y-1.5">
                            <Label>Famille</Label>
                            <select v-model="form.famille_id"
                                class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm">
                                <option :value="null">— Sélectionner une famille —</option>
                                <option v-for="f in familles" :key="f.id" :value="f.id">{{ f.nom }}</option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <Label>Catégorie</Label>
                            <select v-model="form.categorie_id" :disabled="!form.famille_id"
                                class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                <option :value="null">— Sélectionner une catégorie —</option>
                                <option v-for="c in filteredCategories" :key="c.id" :value="c.id">{{ c.nom }}</option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <Label>Sous-Catégorie</Label>
                            <select v-model="form.sous_categorie_id" :disabled="!form.categorie_id"
                                class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                <option :value="null">— Sélectionner une sous-catégorie —</option>
                                <option v-for="s in filteredSousCategories" :key="s.id" :value="s.id">{{ s.nom }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Type de dépense -->
                    <div class="space-y-1.5">
                        <Label>Type de dépense</Label>
                        <select v-model="form.type_depense_id"
                            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm">
                            <option :value="null">— Sélectionner un type de dépense —</option>
                            <option v-for="td in typeDepenses" :key="td.id" :value="td.id">{{ td.nom_depense }}</option>
                        </select>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showModal = false">Annuler</Button>
                        <Button type="submit" class="bg-purple-600 hover:bg-purple-700">Enregistrer</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="showImportModal" @update:open="showImportModal = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Importer des articles</DialogTitle>
                    <DialogDescription>
                        CSV au format du modèle. Si le code existe déjà, l’article est mis à jour ; sinon il est créé.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-3 py-2">
                    <Label for="articles-import-file">Fichier CSV</Label>
                    <Input
                        :key="importFileKey"
                        id="articles-import-file"
                        type="file"
                        accept=".csv,.txt,text/csv"
                        class="cursor-pointer"
                        @change="onImportFileChange"
                    />
                    <p class="text-xs text-muted-foreground">
                        Téléchargez d’abord le modèle CSV pour voir les colonnes attendues.
                    </p>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" :disabled="importProcessing" @click="showImportModal = false">
                        Annuler
                    </Button>
                    <Button
                        type="button"
                        class="bg-purple-600 hover:bg-purple-700"
                        :disabled="importProcessing || !importFile"
                        @click="submitImport"
                    >
                        <Upload class="mr-2 h-4 w-4" />
                        {{ importProcessing ? 'Import…' : 'Importer' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
