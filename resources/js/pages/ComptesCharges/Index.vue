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
    comptesCharges: {
        data: Array<{ id: number; nom: string; code_agence: string | null; code_gl: string | null }>;
        current_page: number;
        per_page: number;
        total: number;
    }
}>();

const breadcrumbs = [
    { title: 'Configuration', href: '#' },
    { title: 'Comptes de Charges', href: '/comptes-charges' },
];

const columns = [
    { key: 'nom', title: 'Nom du Compte', sortable: true },
    { key: 'code_agence', title: 'Code Agence' },
    { key: 'code_gl', title: 'Code GL' },
    { key: 'actions', title: 'Actions' }
];

// État du modal et formulaire
const showModal = ref(false);
const isEditing = ref(false);
const form = ref({
    id: null as number | null,
    nom: '',
    code_agence: '',
    code_gl: ''
});

const openCreateModal = () => {
    isEditing.value = false;
    form.value = { id: null, nom: '', code_agence: '', code_gl: '' };
    showModal.value = true;
};

const openEditModal = (compte: any) => {
    isEditing.value = true;
    form.value = { ...compte };
    showModal.value = true;
};

const deleteCompte = (id: number) => {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce compte de charge ?')) {
        router.delete(`/comptes-charges/${id}`, { preserveScroll: true });
    }
};

const submitForm = () => {
    if (isEditing.value) {
        router.put(`/comptes-charges/${form.value.id}`, form.value, {
            onSuccess: () => showModal.value = false
        });
    } else {
        router.post('/comptes-charges', form.value, {
            onSuccess: () => showModal.value = false
        });
    }
};
</script>

<template>
    <Head title="Gestion des Comptes de Charges" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-gray-900">Comptes de Charges</h1>
                <Button @click="openCreateModal" class="bg-purple-600 hover:bg-purple-700">
                    <Plus class="mr-2 h-4 w-4" /> Nouveau Compte
                </Button>
            </div>

            <!-- Ajout d'une vérification de présence de données -->
            <DataTable
                v-if="props.comptesCharges"
                :headers="columns"
                :items="props.comptesCharges.data"
                :current-page="props.comptesCharges.current_page"
                :items-per-page="props.comptesCharges.per_page"
                :total-items="props.comptesCharges.total"
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
                            @click="deleteCompte(item.id)"
                            class="inline-flex items-center justify-center rounded-md p-2 text-red-600 hover:bg-red-50 hover:text-red-700"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </template>
            </DataTable>
            <div v-else class="text-center py-6 text-gray-500">
                Aucune donnée reçue.
            </div>
        </div>

        <!-- Modal Ajout/Édition -->
        <Dialog :open="showModal" @update:open="showModal = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ isEditing ? 'Modifier le Compte de Charge' : 'Ajouter un Compte de Charge' }}</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submitForm" class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="nom">Nom du Compte</Label>
                        <Input id="nom" v-model="form.nom" required placeholder="Saisir le nom" />
                    </div>
                    <div class="space-y-2">
                        <Label for="code_agence">Code Agence</Label>
                        <Input id="code_agence" v-model="form.code_agence" placeholder="Saisir le Code Agence" />
                    </div>
                    <div class="space-y-2">
                        <Label for="code_gl">Code GL</Label>
                        <Input id="code_gl" v-model="form.code_gl" placeholder="Saisir le Code GL" />
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
