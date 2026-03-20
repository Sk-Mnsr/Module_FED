<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import DataTable from '@/components/DataTable.vue';
import { Pencil, Trash2, Plus, Download, Upload } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    fiches: {
        data: Array<any>;
        current_page: number;
        per_page: number;
        total: number;
    }
}>();

const breadcrumbs = [
    { title: 'Configuration', href: '#' },
    { title: 'Fiches d\'Intégration', href: '/fiche-integrations' },
];

const columns = [
    { key: 'no_batch', title: 'N° Batch', sortable: true },
    { key: 'no_compte', title: 'N° Compte' },
    { key: 'sens', title: 'Sens' },
    { key: 'montant', title: 'Montant' },
    { key: 'date_de_valeur', title: 'Date Valeur' },
    { key: 'statut', title: 'Statut' },
    { key: 'actions', title: 'Actions' }
];

const showModal = ref(false);
const isEditing = ref(false);
const form = ref({
    id: null as number | null,
    numero: '',
    no_batch: '',
    no_compte: '',
    sens: '',
    montant: 0,
    code_operation: 0,
    date_de_valeur: '',
    code_agence: 500,
    libele_ecriture: '',
    annee_comptable: '',
    mois_comptable: '',
    montantAPayer: 0,
    account: 0,
    relicat: 0,
    restantAPayer: 0,
    statut: 'en_attente',
});

const openCreateModal = () => {
    isEditing.value = false;
    form.value = { 
        id: null, numero: '', no_batch: '', no_compte: '', sens: '', montant: 0, code_operation: 0, 
        date_de_valeur: '', code_agence: 500, libele_ecriture: '', annee_comptable: '', mois_comptable: '', 
        montantAPayer: 0, account: 0, relicat: 0, restantAPayer: 0, statut: 'en_attente'
    };
    showModal.value = true;
};

const openEditModal = (fiche: any) => {
    isEditing.value = true;
    form.value = { ...fiche };
    showModal.value = true;
};

const deleteFiche = (id: number) => {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette fiche d\'intégration ?')) {
        router.delete(`/fiche-integrations/${id}`, { preserveScroll: true });
    }
};

const submitForm = () => {
    if (isEditing.value) {
        router.put(`/fiche-integrations/${form.value.id}`, form.value, {
            onSuccess: () => showModal.value = false
        });
    } else {
        router.post('/fiche-integrations', form.value, {
            onSuccess: () => showModal.value = false
        });
    }
};

const formatAmount = (value?: number | null) => {
    if (value === null || value === undefined) return '-';
    return new Intl.NumberFormat('fr-FR').format(value);
};

const statusBadge = (status: string) => {
    const badges: Record<string, string> = {
        en_attente: 'bg-yellow-100 text-yellow-800',
        valide: 'bg-green-100 text-green-800',
        rejete: 'bg-red-100 text-red-800',
    };
    return badges[status] ?? 'bg-gray-100 text-gray-800';
};

// Export
const exportFiches = () => {
    window.location.href = '/fiche-integrations/export';
};

// Import Modal
const showImportModal = ref(false);
const importForm = useForm({
    file: null as File | null,
});

const submitImport = () => {
    importForm.post('/fiche-integrations/import', {
        preserveScroll: true,
        onSuccess: () => {
            showImportModal.value = false;
            importForm.reset('file');
        },
    });
};
</script>

<template>
    <Head title="Gestion des Fiches d'Intégration" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-3xl font-bold text-gray-900">Fiches d'Intégration</h1>
                <div class="flex items-center gap-2">
                    <Button variant="outline" @click="exportFiches" class="text-green-700 hover:text-green-800 hover:bg-green-50">
                        <Download class="mr-2 h-4 w-4" /> Exporter (Excel)
                    </Button>
                    <Button variant="outline" @click="showImportModal = true" class="text-blue-700 hover:text-blue-800 hover:bg-blue-50">
                        <Upload class="mr-2 h-4 w-4" /> Importer
                    </Button>
                    <Button @click="openCreateModal" class="bg-purple-600 hover:bg-purple-700 text-white">
                        <Plus class="mr-2 h-4 w-4" /> Nouvelle Fiche
                    </Button>
                </div>
            </div>

            <DataTable
                v-if="props.fiches"
                :headers="columns"
                :items="props.fiches.data"
                :current-page="props.fiches.current_page"
                :items-per-page="props.fiches.per_page"
                :total-items="props.fiches.total"
                :show-select="false"
            >
                <template #item.montant="{ item }">
                    {{ formatAmount(item.montant) }}
                </template>
                <template #item.statut="{ item }">
                    <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', statusBadge(item.statut)]">
                        {{ item.statut }}
                    </span>
                </template>
                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <button
                            @click="openEditModal(item)"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                        >
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button
                            @click="deleteFiche(item.id)"
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

        <Dialog :open="showModal" @update:open="showModal = $event">
            <DialogContent class="max-w-2xl max-h-[80vh] overflow-y-auto w-full">
                <DialogHeader>
                    <DialogTitle>{{ isEditing ? 'Modifier la Fiche d\'Intégration' : 'Ajouter une Fiche d\'Intégration' }}</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submitForm" class="space-y-4 py-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="numero">Numéro</Label>
                            <Input id="numero" v-model="form.numero" required />
                        </div>
                        <div class="space-y-2">
                            <Label for="no_batch">N° Batch</Label>
                            <Input id="no_batch" v-model="form.no_batch" required />
                        </div>
                        <div class="space-y-2">
                            <Label for="no_compte">N° Compte</Label>
                            <Input id="no_compte" v-model="form.no_compte" required />
                        </div>
                         <div class="space-y-2">
                            <Label for="sens">Sens</Label>
                            <select id="sens" v-model="form.sens" class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900" required>
                                <option value="D">D (Débit)</option>
                                <option value="C">C (Crédit)</option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <Label for="montant">Montant</Label>
                            <Input type="number" id="montant" v-model="form.montant" required />
                        </div>
                        <div class="space-y-2">
                            <Label for="code_operation">Code Opération</Label>
                            <Input type="number" id="code_operation" v-model="form.code_operation" required />
                        </div>
                        <div class="space-y-2">
                            <Label for="date_de_valeur">Date de Valeur</Label>
                            <Input type="date" id="date_de_valeur" v-model="form.date_de_valeur" required />
                        </div>
                         <div class="space-y-2">
                            <Label for="code_agence">Code Agence</Label>
                            <Input type="number" id="code_agence" v-model="form.code_agence" required />
                        </div>
                        <div class="space-y-2">
                            <Label for="annee_comptable">Année Comptable</Label>
                            <Input id="annee_comptable" v-model="form.annee_comptable" required />
                        </div>
                        <div class="space-y-2">
                            <Label for="mois_comptable">Mois Comptable</Label>
                            <Input id="mois_comptable" v-model="form.mois_comptable" required />
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                            <Label for="libele_ecriture">Libellé écriture</Label>
                            <Input id="libele_ecriture" v-model="form.libele_ecriture" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                         <div class="space-y-2">
                            <Label for="montantAPayer">Montant à Payer</Label>
                            <Input type="number" id="montantAPayer" v-model="form.montantAPayer" required />
                        </div>
                         <div class="space-y-2">
                            <Label for="account">Account (Montant Compte)</Label>
                            <Input type="number" id="account" v-model="form.account" required />
                        </div>
                         <div class="space-y-2">
                            <Label for="relicat">Relicat</Label>
                            <Input type="number" id="relicat" v-model="form.relicat" required />
                        </div>
                         <div class="space-y-2">
                            <Label for="restantAPayer">Restant à Payer</Label>
                            <Input type="number" id="restantAPayer" v-model="form.restantAPayer" required />
                        </div>
                         <div class="space-y-2">
                            <Label for="statut">Statut</Label>
                            <select id="statut" v-model="form.statut" class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900" required>
                                <option value="en_attente">En attente</option>
                                <option value="valide">Validé</option>
                                <option value="rejete">Rejeté</option>
                            </select>
                        </div>
                    </div>
                    
                    <DialogFooter class="pt-4">
                        <Button type="button" variant="outline" @click="showModal = false">Annuler</Button>
                        <Button type="submit" class="bg-purple-600 hover:bg-purple-700">Enregistrer</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
        <!-- Modal Import -->
        <Dialog :open="showImportModal" @update:open="showImportModal = $event">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Importer des Fiches d'Intégration</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submitImport" class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="file">Fichier Excel (.xlsx, .xls, .csv)</Label>
                        <Input 
                            id="file" 
                            type="file" 
                            accept=".xlsx,.xls,.csv"
                            @input="importForm.file = $event.target.files[0]" 
                            required 
                        />
                        <p v-if="importForm.errors.file" class="text-sm text-red-600">
                            {{ importForm.errors.file }}
                        </p>
                    </div>
                    
                    <DialogFooter class="pt-4">
                        <Button type="button" variant="outline" @click="showImportModal = false" :disabled="importForm.processing">Annuler</Button>
                        <Button type="submit" class="bg-blue-600 hover:bg-blue-700" :disabled="importForm.processing">
                            <Upload v-if="!importForm.processing" class="mr-2 h-4 w-4" />
                            {{ importForm.processing ? 'Importation en cours...' : 'Importer' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
