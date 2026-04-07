<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import DataTable from '@/components/DataTable.vue';
import { Pencil, Trash2, Plus } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    agences: {
        data: Array<any>;
        current_page: number;
        per_page: number;
        total: number;
    }
}>();

const breadcrumbs = [
    { title: 'Configuration', href: '#' },
    { title: 'Entités', href: '/agences' },
];

const columns = [
    { key: 'code', title: 'Code', sortable: true },
    { key: 'nom', title: 'Nom', sortable: true },
    { key: 'actions', title: 'Actions' }
];

const showModal = ref(false);
const isEditing = ref(false);
const form = ref({
    id: null as number | null,
    code: '',
    nom: '',
});

const openCreateModal = () => {
    isEditing.value = false;
    form.value = { id: null, code: '', nom: '' };
    showModal.value = true;
};

const openEditModal = (agence: any) => {
    isEditing.value = true;
    form.value = { ...agence };
    showModal.value = true;
};

const deleteAgence = (id: number) => {
    if (confirm('Supprimer cette agence ?')) {
        router.delete(`/agences/${id}`, { preserveScroll: true });
    }
};

const submitForm = () => {
    if (isEditing.value) {
        router.put(`/agences/${form.value.id}`, form.value, { 
            onSuccess: () => showModal.value = false 
        });
    } else {
        router.post('/agences', form.value, { 
            onSuccess: () => showModal.value = false 
        });
    }
};
</script>

<template>
    <Head title="Gestion des Entités" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-gray-900">Entités</h1>
                <Button @click="openCreateModal" class="bg-purple-600 hover:bg-purple-700">
                    <Plus class="mr-2 h-4 w-4" /> Nouvelle Entité
                </Button>
            </div>

            <DataTable
                :headers="columns"
                :items="props.agences.data"
                :current-page="props.agences.current_page"
                :items-per-page="props.agences.per_page"
                :total-items="props.agences.total"
                :show-select="false"
            >
                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <button @click="openEditModal(item)" class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900">
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button @click="deleteAgence(item.id)" class="inline-flex items-center justify-center rounded-md p-2 text-red-600 hover:bg-red-50 hover:text-red-700">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <Dialog :open="showModal" @update:open="showModal = $event">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>{{ isEditing ? 'Modifier l\'Agence' : 'Ajouter une Agence' }}</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submitForm" class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="code">Code</Label>
                        <Input id="code" v-model="form.code" required placeholder="Ex: AGC001" />
                    </div>
                    <div class="space-y-2">
                        <Label for="nom">Nom</Label>
                        <Input id="nom" v-model="form.nom" required placeholder="Nom de l'agence" />
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
