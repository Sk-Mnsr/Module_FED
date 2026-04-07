<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Plus, Pencil, Trash2 } from 'lucide-vue-next';
import { ref, reactive } from 'vue';

interface TypeDepense {
    id: number;
    nom_depense: string;
    compte_gl: string | null;
}

interface Pagination<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    typeDepenses: Pagination<TypeDepense>;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Types de dépense', href: '/type-depenses' },
];

// ─── Modal création ────────────────────────────────────────────────────────────
const createOpen = ref(false);
const createForm = reactive({ nom_depense: '', compte_gl: '' });
const isCreating = ref(false);

const openCreate = () => {
    createForm.nom_depense = '';
    createForm.compte_gl = '';
    createOpen.value = true;
};

const submitCreate = () => {
    isCreating.value = true;
    router.post('/type-depenses', { ...createForm }, {
        preserveScroll: true,
        onFinish: () => { isCreating.value = false; createOpen.value = false; },
    });
};

// ─── Modal édition ─────────────────────────────────────────────────────────────
const editOpen = ref(false);
const editTarget = ref<TypeDepense | null>(null);
const editForm = reactive({ nom_depense: '', compte_gl: '' });
const isEditing = ref(false);

const openEdit = (item: TypeDepense) => {
    editTarget.value = item;
    editForm.nom_depense = item.nom_depense;
    editForm.compte_gl = item.compte_gl ?? '';
    editOpen.value = true;
};

const submitEdit = () => {
    if (!editTarget.value) return;
    isEditing.value = true;
    router.put(`/type-depenses/${editTarget.value.id}`, { ...editForm }, {
        preserveScroll: true,
        onFinish: () => { isEditing.value = false; editOpen.value = false; },
    });
};

// ─── Suppression ──────────────────────────────────────────────────────────────
const deleteTarget = ref<TypeDepense | null>(null);
const deleteOpen = ref(false);
const isDeleting = ref(false);

const openDelete = (item: TypeDepense) => { deleteTarget.value = item; deleteOpen.value = true; };

const submitDelete = () => {
    if (!deleteTarget.value) return;
    isDeleting.value = true;
    router.delete(`/type-depenses/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onFinish: () => { isDeleting.value = false; deleteOpen.value = false; },
    });
};
</script>

<template>
    <Head title="Types de dépense" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <!-- En-tête -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Types de dépense</h1>
                    <p class="text-sm text-gray-500">Gestion des types de dépenses et comptes GL associés.</p>
                </div>
                <Button class="bg-purple-600 hover:bg-purple-700" @click="openCreate">
                    <Plus class="mr-2 h-4 w-4" /> Nouveau type
                </Button>
            </div>

            <!-- Tableau -->
            <div class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-900 text-white">
                        <tr class="text-xs uppercase">
                            <th class="px-4 py-3 text-left">Nom de la dépense</th>
                            <th class="px-4 py-3 text-left">Compte GL</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-if="typeDepenses.data.length === 0">
                            <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">
                                Aucun type de dépense. Cliquez sur "Nouveau type" pour commencer.
                            </td>
                        </tr>
                        <tr v-for="item in typeDepenses.data" :key="item.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ item.nom_depense }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ item.compte_gl || '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <button class="mr-2 text-gray-500 hover:text-blue-600" @click="openEdit(item)">
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button class="text-gray-500 hover:text-red-600" @click="openDelete(item)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <p v-if="typeDepenses.total > 0" class="text-sm text-gray-500 text-right">
                {{ typeDepenses.total }} résultat(s) — page {{ typeDepenses.current_page }} / {{ typeDepenses.last_page }}
            </p>
        </div>

        <!-- Modal Créer -->
        <Dialog v-model:open="createOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Nouveau type de dépense</DialogTitle>
                </DialogHeader>
                <div class="space-y-4 py-2">
                    <div>
                        <Label>Nom de la dépense <span class="text-red-500">*</span></Label>
                        <Input v-model="createForm.nom_depense" class="mt-1.5" placeholder="Ex: Fournitures de bureau" />
                    </div>
                    <div>
                        <Label>Compte GL</Label>
                        <Input v-model="createForm.compte_gl" class="mt-1.5" placeholder="Ex: 601000" />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="createOpen = false">Annuler</Button>
                    <Button :disabled="isCreating || !createForm.nom_depense" @click="submitCreate">
                        {{ isCreating ? 'Enregistrement...' : 'Enregistrer' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Modal Éditer -->
        <Dialog v-model:open="editOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Modifier le type de dépense</DialogTitle>
                </DialogHeader>
                <div class="space-y-4 py-2">
                    <div>
                        <Label>Nom de la dépense <span class="text-red-500">*</span></Label>
                        <Input v-model="editForm.nom_depense" class="mt-1.5" />
                    </div>
                    <div>
                        <Label>Compte GL</Label>
                        <Input v-model="editForm.compte_gl" class="mt-1.5" />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="editOpen = false">Annuler</Button>
                    <Button :disabled="isEditing || !editForm.nom_depense" @click="submitEdit">
                        {{ isEditing ? 'Enregistrement...' : 'Enregistrer' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Modal Supprimer -->
        <Dialog v-model:open="deleteOpen">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Confirmer la suppression</DialogTitle>
                </DialogHeader>
                <p class="text-sm text-gray-600 py-2">
                    Supprimer <strong>{{ deleteTarget?.nom_depense }}</strong> ? Cette action est irréversible.
                </p>
                <DialogFooter>
                    <Button variant="outline" @click="deleteOpen = false">Annuler</Button>
                    <Button variant="destructive" :disabled="isDeleting" @click="submitDelete">
                        {{ isDeleting ? 'Suppression...' : 'Supprimer' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
