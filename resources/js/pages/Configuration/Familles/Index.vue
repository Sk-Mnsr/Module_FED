<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Plus, Pencil, Trash2, ChevronDown, ChevronRight, Folder, FolderOpen, Tag, Upload } from 'lucide-vue-next';
import {
    Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';

// ─── Types ───────────────────────────────────────────────────────────────────

interface SousCategorie { id: number; nom: string; categorie_id: number; }
interface Categorie { id: number; nom: string; famille_id: number; sous_categories: SousCategorie[]; }
interface Famille { id: number; nom: string; categories: Categorie[]; }

const props = defineProps<{ familles: Famille[] }>();

const breadcrumbs = [
    { title: 'Configuration', href: '#' },
    { title: 'Familles', href: '/familles' },
];

// ─── Accordéon ───────────────────────────────────────────────────────────────
const openFamilles = ref<Set<number>>(new Set());
const openCategories = ref<Set<number>>(new Set());
const toggleFamille = (id: number) => openFamilles.value.has(id) ? openFamilles.value.delete(id) : openFamilles.value.add(id);
const toggleCategorie = (id: number) => openCategories.value.has(id) ? openCategories.value.delete(id) : openCategories.value.add(id);

// ─── Modal Famille ────────────────────────────────────────────────────────────
const familleModal = ref(false);
const familleEditing = ref<Famille | null>(null);
const familleForm = ref({ nom: '' });

const openFamilleCreate = () => { familleEditing.value = null; familleForm.value = { nom: '' }; familleModal.value = true; };
const openFamilleEdit = (f: Famille) => { familleEditing.value = f; familleForm.value = { nom: f.nom }; familleModal.value = true; };
const submitFamille = () => {
    if (familleEditing.value) {
        router.put(`/familles/${familleEditing.value.id}`, familleForm.value, { preserveScroll: true, onSuccess: () => familleModal.value = false });
    } else {
        router.post('/familles', familleForm.value, { preserveScroll: true, onSuccess: () => familleModal.value = false });
    }
};
const deleteFamille = (f: Famille) => {
    if (confirm(`Supprimer la famille "${f.nom}" et toutes ses catégories/sous-catégories ?`))
        router.delete(`/familles/${f.id}`, { preserveScroll: true });
};

// ─── Modal Catégorie ──────────────────────────────────────────────────────────
const categorieModal = ref(false);
const categorieEditing = ref<Categorie | null>(null);
const categorieForm = ref({ nom: '', famille_id: 0 });

const openCategorieCreate = (famille: Famille) => {
    categorieEditing.value = null;
    categorieForm.value = { nom: '', famille_id: famille.id };
    categorieModal.value = true;
};
const openCategorieEdit = (c: Categorie) => {
    categorieEditing.value = c;
    categorieForm.value = { nom: c.nom, famille_id: c.famille_id };
    categorieModal.value = true;
};
const submitCategorie = () => {
    if (categorieEditing.value) {
        router.put(`/categories/${categorieEditing.value.id}`, categorieForm.value, { preserveScroll: true, onSuccess: () => categorieModal.value = false });
    } else {
        router.post('/categories', categorieForm.value, { preserveScroll: true, onSuccess: () => categorieModal.value = false });
    }
};
const deleteCategorie = (c: Categorie) => {
    if (confirm(`Supprimer la catégorie "${c.nom}" et toutes ses sous-catégories ?`))
        router.delete(`/categories/${c.id}`, { preserveScroll: true });
};

// ─── Modal Sous-Catégorie ─────────────────────────────────────────────────────
const sousCatModal = ref(false);
const sousCatEditing = ref<SousCategorie | null>(null);
const sousCatForm = ref({ nom: '', categorie_id: 0 });

const openSousCatCreate = (cat: Categorie) => {
    sousCatEditing.value = null;
    sousCatForm.value = { nom: '', categorie_id: cat.id };
    sousCatModal.value = true;
};
const openSousCatEdit = (s: SousCategorie) => {
    sousCatEditing.value = s;
    sousCatForm.value = { nom: s.nom, categorie_id: s.categorie_id };
    sousCatModal.value = true;
};
const submitSousCat = () => {
    if (sousCatEditing.value) {
        router.put(`/sous-categories/${sousCatEditing.value.id}`, sousCatForm.value, { preserveScroll: true, onSuccess: () => sousCatModal.value = false });
    } else {
        router.post('/sous-categories', sousCatForm.value, { preserveScroll: true, onSuccess: () => sousCatModal.value = false });
    }
};
const deleteSousCat = (s: SousCategorie) => {
    if (confirm(`Supprimer la sous-catégorie "${s.nom}" ?`))
        router.delete(`/sous-categories/${s.id}`, { preserveScroll: true });
};

// ─── Modal Importation ────────────────────────────────────────────────────────
const importModal = ref(false);
const fileToImport = ref<File | null>(null);
const isImporting = ref(false);

const handleFileUpload = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        fileToImport.value = target.files[0];
    }
};

const submitImport = () => {
    if (!fileToImport.value) return;
    isImporting.value = true;
    router.post('/familles/import', { file: fileToImport.value }, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            isImporting.value = false;
            importModal.value = false;
            fileToImport.value = null;
        }
    });
};
</script>

<template>
    <Head title="Familles d'articles" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">

            <!-- En-tête -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Familles d'articles</h1>
                    <p class="text-sm text-gray-500">Hiérarchie : Famille → Catégorie → Sous-Catégorie</p>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" class="border-purple-200 text-purple-700 hover:bg-purple-50" @click="importModal = true">
                        <Upload class="mr-2 h-4 w-4" /> Importer
                    </Button>
                    <Button class="bg-purple-600 hover:bg-purple-700" @click="openFamilleCreate">
                        <Plus class="mr-2 h-4 w-4" /> Nouvelle famille
                    </Button>
                </div>
            </div>

            <!-- Liste des familles -->
            <div class="space-y-3">
                <div v-if="familles.length === 0" class="rounded-lg border border-dashed border-gray-300 py-12 text-center text-sm text-gray-400">
                    Aucune famille. Cliquez sur "Nouvelle famille" pour commencer.
                </div>

                <div v-for="famille in familles" :key="famille.id"
                    class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">

                    <!-- Famille header -->
                    <div class="flex items-center gap-3 px-4 py-3 bg-gray-50 border-b border-gray-200">
                        <button @click="toggleFamille(famille.id)" class="text-gray-500 hover:text-gray-800">
                            <ChevronDown v-if="openFamilles.has(famille.id)" class="h-4 w-4" />
                            <ChevronRight v-else class="h-4 w-4" />
                        </button>
                        <FolderOpen v-if="openFamilles.has(famille.id)" class="h-5 w-5 text-purple-500" />
                        <Folder v-else class="h-5 w-5 text-purple-400" />
                        <span class="font-semibold text-gray-900 flex-1">{{ famille.nom }}</span>
                        <span class="text-xs text-gray-400 mr-2">{{ famille.categories.length }} catégorie(s)</span>
                        <button @click="openCategorieCreate(famille)"
                            class="text-xs text-purple-600 hover:text-purple-800 font-medium mr-3 flex items-center gap-1">
                            <Plus class="h-3 w-3" /> Catégorie
                        </button>
                        <button @click="openFamilleEdit(famille)" class="text-gray-400 hover:text-blue-600 mr-2">
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button @click="deleteFamille(famille)" class="text-gray-400 hover:text-red-600">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Catégories -->
                    <div v-if="openFamilles.has(famille.id)" class="divide-y divide-gray-100">
                        <div v-if="famille.categories.length === 0" class="px-8 py-3 text-sm text-gray-400 italic">
                            Aucune catégorie. Cliquez sur "+ Catégorie" pour en ajouter.
                        </div>

                        <div v-for="cat in famille.categories" :key="cat.id">
                            <!-- Catégorie row -->
                            <div class="flex items-center gap-3 px-8 py-2.5 bg-white hover:bg-gray-50">
                                <button @click="toggleCategorie(cat.id)" class="text-gray-400 hover:text-gray-700">
                                    <ChevronDown v-if="openCategories.has(cat.id)" class="h-3.5 w-3.5" />
                                    <ChevronRight v-else class="h-3.5 w-3.5" />
                                </button>
                                <span class="text-sm font-medium text-gray-800 flex-1">{{ cat.nom }}</span>
                                <span class="text-xs text-gray-400 mr-2">{{ cat.sous_categories.length }} sous-cat.</span>
                                <button @click="openSousCatCreate(cat)"
                                    class="text-xs text-indigo-600 hover:text-indigo-800 font-medium mr-3 flex items-center gap-1">
                                    <Plus class="h-3 w-3" /> Sous-cat.
                                </button>
                                <button @click="openCategorieEdit(cat)" class="text-gray-400 hover:text-blue-600 mr-2">
                                    <Pencil class="h-3.5 w-3.5" />
                                </button>
                                <button @click="deleteCategorie(cat)" class="text-gray-400 hover:text-red-600">
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>

                            <!-- Sous-catégories -->
                            <div v-if="openCategories.has(cat.id)" class="bg-gray-50 border-t border-gray-100">
                                <div v-if="cat.sous_categories.length === 0" class="px-16 py-2 text-xs text-gray-400 italic">
                                    Aucune sous-catégorie.
                                </div>
                                <div v-for="sc in cat.sous_categories" :key="sc.id"
                                    class="flex items-center gap-3 px-16 py-2 border-b border-gray-100 last:border-0 hover:bg-gray-100">
                                    <Tag class="h-3 w-3 text-indigo-400" />
                                    <span class="text-sm text-gray-700 flex-1">{{ sc.nom }}</span>
                                    <button @click="openSousCatEdit(sc)" class="text-gray-400 hover:text-blue-600 mr-2">
                                        <Pencil class="h-3.5 w-3.5" />
                                    </button>
                                    <button @click="deleteSousCat(sc)" class="text-gray-400 hover:text-red-600">
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Famille -->
        <Dialog v-model:open="familleModal">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>{{ familleEditing ? 'Modifier la famille' : 'Nouvelle famille' }}</DialogTitle>
                </DialogHeader>
                <div class="space-y-3 py-2">
                    <Label>Nom <span class="text-red-500">*</span></Label>
                    <Input v-model="familleForm.nom" placeholder="Ex: Informatique" />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="familleModal = false">Annuler</Button>
                    <Button :disabled="!familleForm.nom" class="bg-purple-600 hover:bg-purple-700" @click="submitFamille">Enregistrer</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Modal Catégorie -->
        <Dialog v-model:open="categorieModal">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>{{ categorieEditing ? 'Modifier la catégorie' : 'Nouvelle catégorie' }}</DialogTitle>
                </DialogHeader>
                <div class="space-y-3 py-2">
                    <Label>Nom <span class="text-red-500">*</span></Label>
                    <Input v-model="categorieForm.nom" placeholder="Ex: Hardware" />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="categorieModal = false">Annuler</Button>
                    <Button :disabled="!categorieForm.nom" class="bg-purple-600 hover:bg-purple-700" @click="submitCategorie">Enregistrer</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Modal Sous-Catégorie -->
        <Dialog v-model:open="sousCatModal">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>{{ sousCatEditing ? 'Modifier la sous-catégorie' : 'Nouvelle sous-catégorie' }}</DialogTitle>
                </DialogHeader>
                <div class="space-y-3 py-2">
                    <Label>Nom <span class="text-red-500">*</span></Label>
                    <Input v-model="sousCatForm.nom" placeholder="Ex: Laptops" />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="sousCatModal = false">Annuler</Button>
                    <Button :disabled="!sousCatForm.nom" class="bg-purple-600 hover:bg-purple-700" @click="submitSousCat">Enregistrer</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Modal Importation -->
        <Dialog v-model:open="importModal">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Importer une hiérarchie depuis Excel</DialogTitle>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="bg-blue-50 text-blue-800 text-sm p-3 rounded-lg flex flex-col gap-2 border border-blue-200">
                        <p>L'importation parcourt votre fichier ligne par ligne. Elle crée la famille, puis la catégorie, et enfin la sous-catégorie si elles n'existent pas déjà.</p>
                        <p class="font-medium">
                            <a href="/familles/export-template" class="underline text-blue-700 hover:text-blue-900">
                                Télécharger le template Excel (Exemple)
                            </a>
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label>Fichier (.xlsx ou .csv) <span class="text-red-500">*</span></Label>
                        <Input type="file" accept=".xlsx, .csv, .xls" @change="handleFileUpload" class="cursor-pointer" />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="importModal = false">Annuler</Button>
                    <Button :disabled="!fileToImport || isImporting" class="bg-purple-600 hover:bg-purple-700" @click="submitImport">
                        {{ isImporting ? 'Importation en cours...' : 'Lancer l\'importation' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>
