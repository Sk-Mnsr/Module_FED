<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import DataTable from '@/components/DataTable.vue';
import { Pencil, Trash2, Plus } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';

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
}>();

const breadcrumbs = [
    { title: 'Configuration', href: '#' },
    { title: 'Articles', href: '/articles' },
];

const columns = [
    { key: 'code', title: 'Code', sortable: true },
    { key: 'description', title: 'Description', sortable: true },
    { key: 'responsable', title: 'Responsable' },
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
</script>

<template>
    <Head title="Gestion des Articles" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-gray-900">Articles</h1>
                <Button @click="openCreateModal" class="bg-purple-600 hover:bg-purple-700">
                    <Plus class="mr-2 h-4 w-4" /> Nouveau Article
                </Button>
            </div>

            <DataTable
                :headers="columns"
                :items="props.articles.data"
                :current-page="props.articles.current_page"
                :items-per-page="props.articles.per_page"
                :total-items="props.articles.total"
                :show-select="false"
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
                            <Label for="responsable">Responsable <span class="text-red-500">*</span></Label>
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
    </AppLayout>
</template>
