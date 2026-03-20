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

// Les props reçues depuis le contrôleur
const props = defineProps<{
    banques: {
        data: Array<{ id: number; nom: string; compte_miroir: string | null; compte_externe: string | null }>;
        current_page: number;
        per_page: number;
        total: number;
    }
}>();

const breadcrumbs = [
    { title: 'Configuration', href: '#' },
    { title: 'Banques', href: '/banques' },
];

const columns = [
    { key: 'nom', title: 'Nom de la Banque', sortable: true },
    { key: 'compte_miroir', title: 'Compte Miroir' },
    { key: 'compte_externe', title: 'Compte Externe' },
    { key: 'actions', title: 'Actions' }
];

// État du modal et formulaire
const showModal = ref(false);
const isEditing = ref(false);
const form = ref({
    id: null as number | null,
    nom: '',
    compte_miroir: '',
    compte_externe: ''
});

const openCreateModal = () => {
    isEditing.value = false;
    form.value = { id: null, nom: '', compte_miroir: '', compte_externe: '' };
    showModal.value = true;
};

const openEditModal = (banque: any) => {
    isEditing.value = true;
    form.value = { ...banque };
    showModal.value = true;
};

const deleteBanque = (id: number) => {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette banque ?')) {
        router.delete(`/banques/${id}`, { preserveScroll: true });
    }
};

const submitForm = () => {
    if (isEditing.value) {
        router.put(`/banques/${form.value.id}`, form.value, {
            onSuccess: () => showModal.value = false
        });
    } else {
        router.post('/banques', form.value, {
            onSuccess: () => showModal.value = false
        });
    }
};
</script>

<template>
    <Head title="Gestion des Banques" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-gray-900">Banques</h1>
                <Button @click="openCreateModal" class="bg-purple-600 hover:bg-purple-700">
                    <Plus class="mr-2 h-4 w-4" /> Nouvelle Banque
                </Button>
            </div>

            <DataTable
                :headers="columns"
                :items="props.banques.data"
                :current-page="props.banques.current_page"
                :items-per-page="props.banques.per_page"
                :total-items="props.banques.total"
                :show-select="false"
            >
                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <button
                            @click="openEditModal(item)"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                        >
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button
                            @click="deleteBanque(item.id)"
                            class="inline-flex items-center justify-center rounded-md p-2 text-red-600 hover:bg-red-50 hover:text-red-700"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Modal Ajout/Édition -->
        <Dialog :open="showModal" @update:open="showModal = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ isEditing ? 'Modifier la Banque' : 'Ajouter une Banque' }}</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submitForm" class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="nom">Nom de la Banque</Label>
                        <Input id="nom" v-model="form.nom" required placeholder="Saisir le nom" />
                    </div>
                    <div class="space-y-2">
                        <Label for="compte_miroir">Compte Miroir</Label>
                        <Input id="compte_miroir" v-model="form.compte_miroir" placeholder="N° Compte Miroir" />
                    </div>
                    <div class="space-y-2">
                        <Label for="compte_externe">Compte Externe</Label>
                        <Input id="compte_externe" v-model="form.compte_externe" placeholder="N° Compte Externe" />
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
