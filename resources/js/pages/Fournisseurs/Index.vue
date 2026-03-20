<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import DataTable from '@/components/DataTable.vue';
import { Pencil, Trash2, Plus, Eye } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

// Les props
const props = defineProps<{
    fournisseurs: {
        data: Array<any>;
        current_page: number;
        per_page: number;
        total: number;
    },
    banques: Array<{ id: number; nom: string; }>
}>();

const breadcrumbs = [
    { title: 'Configuration', href: '#' },
    { title: 'Fournisseurs', href: '/fournisseurs' },
];

const columns = [
    { key: 'nom', title: 'Raison Sociale', sortable: true },
    { key: 'type', title: 'Type de Fournisseur' },
    { key: 'categorie', title: 'Catégorie' },
    { key: 'contact_telephone', title: 'Téléphone' },
    { key: 'actions', title: 'Actions' }
];

const showModal = ref(false);
const showViewModal = ref(false);
const isEditing = ref(false);
const selectedFournisseur = ref<any>(null);
const form = ref({
    id: null as number | null,
    nom: '',
    type: '',
    categorie: '',
    description: '',
    contact_nom: '',
    contact_telephone: '',
    contact_email: '',
    site_web: '',
    adresse_physique: '',
    compte_transit_paiement: '',
    compte_avance_acompte: '',
    compte_client_interne: '',
    banque_id: '' as number | string
});

const openCreateModal = () => {
    isEditing.value = false;
    form.value = { 
        id: null, nom: '', type: '', categorie: '', description: '', 
        contact_nom: '', contact_telephone: '', contact_email: '', site_web: '', adresse_physique: '',
        compte_transit_paiement: '', compte_avance_acompte: '', compte_client_interne: '', banque_id: '' 
    };
    showModal.value = true;
};

const openEditModal = (f: any) => {
    isEditing.value = true;
    form.value = { ...f, banque_id: f.banque_id || '' };
    showModal.value = true;
};

const openViewModal = (f: any) => {
    selectedFournisseur.value = f;
    showViewModal.value = true;
};

const deleteFournisseur = (id: number) => {
    if (confirm('Supprimer ce fournisseur ?')) {
        router.delete(`/fournisseurs/${id}`, { preserveScroll: true });
    }
};

const submitForm = () => {
    if (isEditing.value) {
        router.put(`/fournisseurs/${form.value.id}`, form.value, { onSuccess: () => showModal.value = false });
    } else {
        router.post('/fournisseurs', form.value, { onSuccess: () => showModal.value = false });
    }
};
</script>

<template>
    <Head title="Gestion des Fournisseurs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-gray-900">Fournisseurs</h1>
                <Button @click="openCreateModal" class="bg-purple-600 hover:bg-purple-700">
                    <Plus class="mr-2 h-4 w-4" /> Nouveau Fournisseur
                </Button>
            </div>

            <DataTable
                :headers="columns"
                :items="props.fournisseurs.data"
                :current-page="props.fournisseurs.current_page"
                :items-per-page="props.fournisseurs.per_page"
                :total-items="props.fournisseurs.total"
                :show-select="false"
            >
                <template #item.banque.nom="{ item }">
                    {{ item.banque ? item.banque.nom : '-' }}
                </template>
                <template #item.categorie="{ item }">
                    {{ item.categorie || '-' }}
                </template>
                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <button @click="openViewModal(item)" class="inline-flex items-center justify-center rounded-md p-2 text-blue-600 hover:bg-blue-50 hover:text-blue-700">
                            <Eye class="h-4 w-4" />
                        </button>
                        <button @click="openEditModal(item)" class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900">
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button @click="deleteFournisseur(item.id)" class="inline-flex items-center justify-center rounded-md p-2 text-red-600 hover:bg-red-50 hover:text-red-700">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <Dialog :open="showModal" @update:open="showModal = $event">
            <DialogContent class="max-w-4xl max-h-[90vh] overflow-y-auto w-full">
                <DialogHeader>
                    <DialogTitle>{{ isEditing ? 'Modifier le Fournisseur' : 'Ajouter un Fournisseur' }}</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submitForm" class="space-y-6 py-4">
                    
                    <!-- Section: Informations Générales -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Informations Générales</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="nom">Nom du Fournisseur *</Label>
                                <Input id="nom" v-model="form.nom" required placeholder="Ex: Mnsr Corp" />
                            </div>
                            <div class="space-y-2">
                                <Label for="type">Type de Fournisseur</Label>
                                <Input id="type" v-model="form.type" placeholder="Ex: Prestataire de service" />
                            </div>
                            <div class="space-y-2">
                                <Label for="categorie">Catégorie</Label>
                                <Input id="categorie" v-model="form.categorie" placeholder="Ex: Informatique" />
                            </div>
                            <div class="space-y-2">
                                <Label for="description">Description / Notes</Label>
                                <Input id="description" v-model="form.description" placeholder="Courte description" />
                            </div>
                        </div>
                    </div>

                    <!-- Section: Contacts & Localisation -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Contacts & Localisation</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div class="space-y-2">
                                <Label for="contact_nom">Nom du Contact Principal</Label>
                                <Input id="contact_nom" v-model="form.contact_nom" placeholder="Amadou MAR" />
                            </div>
                            <div class="space-y-2">
                                <Label for="contact_telephone">Numéro de Téléphone</Label>
                                <Input id="contact_telephone" v-model="form.contact_telephone" placeholder="+221 3..." />
                            </div>
                            <div class="space-y-2">
                                <Label for="contact_email">Adresse E-mail</Label>
                                <Input type="email" id="contact_email" v-model="form.contact_email" placeholder="cofina@cofina.com" />
                            </div>
                            <div class="space-y-2 lg:col-span-2">
                                <Label for="adresse_physique">Adresse Physique</Label>
                                <Input id="adresse_physique" v-model="form.adresse_physique" placeholder="Rue, Ville, Pays" />
                            </div>
                            <div class="space-y-2">
                                <Label for="site_web">Site Web</Label>
                                <Input id="site_web" v-model="form.site_web" placeholder="www.cofinasenegal.com" />
                            </div>
                        </div>
                    </div>

                    <!-- Section: Informations Comptables -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Informations Comptables </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="compte_transit_paiement">Compte Transit Paiement Facture</Label>
                                <Input id="compte_transit_paiement" v-model="form.compte_transit_paiement" maxlength="12" placeholder="Ex: 100000000001" />
                                <p class="text-xs text-gray-500">12 chiffres requis</p>
                            </div>
                            <div class="space-y-2">
                                <Label for="compte_avance_acompte">Compte Avance et Acompte</Label>
                                <Input id="compte_avance_acompte" v-model="form.compte_avance_acompte" maxlength="12" placeholder="Ex: 100000000002" />
                                <p class="text-xs text-gray-500">12 chiffres requis</p>
                            </div>
                            <div class="space-y-2">
                                <Label for="compte_client_interne">Compte Client Interne</Label>
                                <Input id="compte_client_interne" v-model="form.compte_client_interne" maxlength="12" placeholder="Ex: 100000000003" />
                                <p class="text-xs text-gray-500">12 chiffres requis</p>
                            </div>
                            <div class="space-y-2">
                                <Label for="banque_id">Banque </Label>
                                <select id="banque_id" v-model="form.banque_id" class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-gray-400">
                                    <option value="">-- Sélectionner une banque --</option>
                                    <option v-for="b in props.banques" :key="b.id" :value="b.id">
                                        {{ b.nom }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showModal = false">Annuler</Button>
                        <Button type="submit" class="bg-purple-600 hover:bg-purple-700">Enregistrer</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal Vue Détails -->
        <Dialog :open="showViewModal" @update:open="showViewModal = $event">
            <DialogContent class="max-w-3xl max-h-[90vh] overflow-y-auto w-full">
                <DialogHeader>
                    <DialogTitle>Détails du Fournisseur</DialogTitle>
                </DialogHeader>
                <div v-if="selectedFournisseur" class="space-y-6 py-4">
                    <!-- Informations Générales -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Informations Générales</h3>
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Raison Sociale</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ selectedFournisseur.nom }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Type de Fournisseur</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ selectedFournisseur.type || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Catégorie</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ selectedFournisseur.categorie || '-' }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-sm font-medium text-gray-500">Description / Notes</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ selectedFournisseur.description || '-' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Contacts & Localisation -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Contacts & Localisation</h3>
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Nom du Contact Principal</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ selectedFournisseur.contact_nom || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Téléphone</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ selectedFournisseur.contact_telephone || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Adresse E-mail</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ selectedFournisseur.contact_email || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Site Web</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ selectedFournisseur.site_web || '-' }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-sm font-medium text-gray-500">Adresse Physique</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ selectedFournisseur.adresse_physique || '-' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Informations Comptables -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Informations Comptables</h3>
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Compte Transit</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ selectedFournisseur.compte_transit_paiement || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Compte Avance / Acompte</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ selectedFournisseur.compte_avance_acompte || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Compte Client Interne</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ selectedFournisseur.compte_client_interne || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Banque</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    <span v-if="selectedFournisseur.banque">
                                        {{ selectedFournisseur.banque.compte_miroir || 'N/A' }} 
                                        <span class="text-gray-500">({{ selectedFournisseur.banque.nom }})</span>
                                    </span>
                                    <span v-else>-</span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
