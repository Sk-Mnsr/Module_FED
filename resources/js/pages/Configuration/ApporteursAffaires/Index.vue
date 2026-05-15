<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import DataTable from '@/components/DataTable.vue';
import { Label } from '@/components/ui/label';
import { Pencil, Trash2, Plus } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';

type ApporteurRow = {
    id: number;
    code: string | null;
    nom: string;
    actif: boolean;
};

defineProps<{
    apporteurs: {
        data: ApporteurRow[];
        current_page: number;
        per_page: number;
        total: number;
    };
}>();

const breadcrumbs = [
    { title: 'Configuration', href: '#' },
    { title: 'Apporteurs d’affaires', href: '/apporteurs-affaires' },
];

const columns = [
    { key: 'code', title: 'Code', sortable: true },
    { key: 'nom', title: 'Nom', sortable: true },
    { key: 'actif', title: 'Statut' },
    { key: 'actions', title: 'Actions' },
];

const showModal = ref(false);
const isEditing = ref(false);
const form = ref({
    id: null as number | null,
    code: '',
    nom: '',
    actif: true,
});

const openCreateModal = () => {
    isEditing.value = false;
    form.value = {
        id: null,
        code: '',
        nom: '',
        actif: true,
    };
    showModal.value = true;
};

const openEditModal = (row: ApporteurRow) => {
    isEditing.value = true;
    form.value = {
        id: row.id,
        code: row.code ?? '',
        nom: row.nom,
        actif: row.actif,
    };
    showModal.value = true;
};

const deleteRow = (id: number) => {
    if (!confirm('Désactiver cet apporteur d’affaires ?')) {
        return;
    }
    router.delete(`/apporteurs-affaires/${id}`, { preserveScroll: true });
};

const onPageChange = (page: number) => {
    router.get('/apporteurs-affaires', { page }, { preserveState: true, replace: true });
};

const submitForm = () => {
    const code = form.value.code.trim();
    const nom = form.value.nom.trim();
    if (isEditing.value && form.value.id !== null) {
        router.put(`/apporteurs-affaires/${form.value.id}`, {
            code,
            nom,
            actif: form.value.actif,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
            },
        });
    } else {
        router.post('/apporteurs-affaires', { code, nom }, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
            },
        });
    }
};
</script>

<template>
    <Head title="Apporteurs d’affaires — Configuration" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <h1 class="text-3xl font-bold text-gray-900">Apporteurs d’affaires</h1>
                <Button class="bg-violet-600 hover:bg-violet-700" @click="openCreateModal">
                    <Plus class="mr-2 h-4 w-4" />
                    Nouvel apporteur
                </Button>
            </div>
            <p class="text-sm text-gray-600 max-w-3xl">
                Référentiel national des apporteurs d’affaires (code et nom). Les codes sont uniques dans l’application. Ces apporteurs sont disponibles pour toutes les agences.
            </p>

            <DataTable
                :headers="columns"
                :items="apporteurs.data"
                :current-page="apporteurs.current_page"
                :items-per-page="apporteurs.per_page"
                :total-items="apporteurs.total"
                :show-select="false"
                :on-page-change="onPageChange"
            >
                <template #item.actif="{ item }">
                    <span
                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="item.actif ? 'bg-emerald-50 text-emerald-800' : 'bg-gray-100 text-gray-600'"
                    >
                        {{ item.actif ? 'Actif' : 'Inactif' }}
                    </span>
                </template>
                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                            @click="openEditModal(item)"
                        >
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button
                            v-if="item.actif"
                            type="button"
                            class="inline-flex items-center justify-center rounded-md p-2 text-red-600 hover:bg-red-50 hover:text-red-700"
                            @click="deleteRow(item.id)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <Dialog :open="showModal" @update:open="showModal = $event">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{ isEditing ? 'Modifier l’apporteur' : 'Nouvel apporteur d’affaires' }}
                    </DialogTitle>
                </DialogHeader>
                <form class="space-y-4 py-4" @submit.prevent="submitForm">
                    <div class="space-y-2">
                        <Label for="code" class="text-xs font-medium text-gray-600">Code</Label>
                        <Input id="code" v-model="form.code" required maxlength="64" class="uppercase border-gray-300" />
                    </div>
                    <div class="space-y-2">
                        <Label for="nom" class="text-xs font-medium text-gray-600">Nom</Label>
                        <Input id="nom" v-model="form.nom" required maxlength="191" class="border-gray-300" />
                    </div>
                    <div v-if="isEditing" class="flex items-center gap-2">
                        <input id="actif" v-model="form.actif" type="checkbox" class="rounded border-gray-300" />
                        <Label for="actif" class="text-sm font-normal">Actif</Label>
                    </div>
                    <DialogFooter class="gap-2 sm:gap-0">
                        <Button type="button" variant="outline" @click="showModal = false">Annuler</Button>
                        <Button type="submit" class="bg-violet-600 hover:bg-violet-700">Enregistrer</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
