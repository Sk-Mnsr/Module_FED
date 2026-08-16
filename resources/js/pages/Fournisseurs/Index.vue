<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import DataTable from '@/components/DataTable.vue';
import { Pencil, Trash2, Plus, Eye } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    fournisseurs: {
        data: Array<any>;
        current_page: number;
        per_page: number;
        total: number;
    };
    banques: Array<{ id: number; nom: string }>;
}>();

const breadcrumbs = [
    { title: 'Référentiels', href: '#' },
    { title: 'Fournisseurs', href: '/fournisseurs' },
];

const columns = [
    { key: 'nom', title: 'Raison Sociale', sortable: true },
    { key: 'type', title: 'Type de Fournisseur' },
    { key: 'categorie', title: 'Catégorie' },
    { key: 'contact_telephone', title: 'Téléphone' },
    { key: 'actions', title: 'Actions' },
];

const showModal = ref(false);
const showViewModal = ref(false);
const isEditing = ref(false);
const selectedFournisseur = ref<any>(null);
const clientErrors = ref<Record<string, string>>({});

const form = useForm({
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
    banque_id: '' as number | string,
});

const page = usePage();
const flashSuccess = computed(() => (page.props.flash as any)?.success as string | undefined);

const accountFields = [
    'compte_transit_paiement',
    'compte_avance_acompte',
    'compte_client_interne',
] as const;

const resetForm = () => {
    form.reset();
    form.clearErrors();
    clientErrors.value = {};
    form.id = null;
    form.banque_id = '';
};

const openCreateModal = () => {
    isEditing.value = false;
    resetForm();
    showModal.value = true;
};

const openEditModal = (f: any) => {
    isEditing.value = true;
    form.clearErrors();
    clientErrors.value = {};
    form.id = f.id;
    form.nom = f.nom ?? '';
    form.type = f.type ?? '';
    form.categorie = f.categorie ?? '';
    form.description = f.description ?? '';
    form.contact_nom = f.contact_nom ?? '';
    form.contact_telephone = f.contact_telephone ?? '';
    form.contact_email = f.contact_email ?? '';
    form.site_web = f.site_web ?? '';
    form.adresse_physique = f.adresse_physique ?? '';
    form.compte_transit_paiement = f.compte_transit_paiement ?? '';
    form.compte_avance_acompte = f.compte_avance_acompte ?? '';
    form.compte_client_interne = f.compte_client_interne ?? '';
    form.banque_id = f.banque_id || '';
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

const onlyDigits = (value: string) => value.replace(/\D/g, '').slice(0, 12);

const onAccountInput = (field: (typeof accountFields)[number], event: Event) => {
    const input = event.target as HTMLInputElement;
    form[field] = onlyDigits(input.value);
    delete clientErrors.value[field];
};

const validateAccounts = () => {
    clientErrors.value = {};
    for (const field of accountFields) {
        const value = String(form[field] ?? '').trim();
        if (value !== '' && !/^\d{12}$/.test(value)) {
            clientErrors.value[field] = 'Le compte doit contenir exactement 12 chiffres.';
        }
    }
    return Object.keys(clientErrors.value).length === 0;
};

const fieldError = (field: string) =>
    clientErrors.value[field] || (form.errors as Record<string, string>)[field] || '';

const submitForm = () => {
    if (!validateAccounts()) return;

    const payload = {
        nom: form.nom,
        type: form.type || null,
        categorie: form.categorie || null,
        description: form.description || null,
        contact_nom: form.contact_nom || null,
        contact_telephone: form.contact_telephone || null,
        contact_email: form.contact_email || null,
        site_web: form.site_web || null,
        adresse_physique: form.adresse_physique || null,
        compte_transit_paiement: form.compte_transit_paiement || null,
        compte_avance_acompte: form.compte_avance_acompte || null,
        compte_client_interne: form.compte_client_interne || null,
        banque_id: form.banque_id === '' ? null : form.banque_id,
    };

    if (isEditing.value && form.id) {
        form.transform(() => payload).put(`/fournisseurs/${form.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                resetForm();
            },
        });
    } else {
        form.transform(() => payload).post('/fournisseurs', {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                resetForm();
            },
        });
    }
};

watch(showModal, (open) => {
    if (!open) {
        form.clearErrors();
        clientErrors.value = {};
    }
});
</script>

<template>
    <Head title="Gestion des Fournisseurs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div
                v-if="flashSuccess"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            >
                {{ flashSuccess }}
            </div>

            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-gray-900">Fournisseurs</h1>
                <Button class="bg-purple-600 hover:bg-purple-700" @click="openCreateModal">
                    <Plus class="mr-2 h-4 w-4" />
                    Nouveau Fournisseur
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
                <template #item.categorie="{ item }">
                    {{ item.categorie || '-' }}
                </template>
                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-md p-2 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                            @click="openViewModal(item)"
                        >
                            <Eye class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                            @click="openEditModal(item)"
                        >
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-md p-2 text-red-600 hover:bg-red-50 hover:text-red-700"
                            @click="deleteFournisseur(item.id)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <Dialog :open="showModal" @update:open="showModal = $event">
            <DialogContent class="max-h-[90vh] w-full max-w-4xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {{ isEditing ? 'Modifier le Fournisseur' : 'Ajouter un Fournisseur' }}
                    </DialogTitle>
                </DialogHeader>
                <form class="space-y-6 py-4" @submit.prevent="submitForm">
                    <div
                        v-if="Object.keys(form.errors).length || Object.keys(clientErrors).length"
                        class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                    >
                        Corrigez les champs en erreur avant d’enregistrer.
                    </div>

                    <div>
                        <h3 class="mb-4 border-b pb-2 text-lg font-medium text-gray-900">
                            Informations Générales
                        </h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="nom">Nom du Fournisseur *</Label>
                                <Input id="nom" v-model="form.nom" required placeholder="Ex: Mnsr Corp" />
                                <InputError :message="form.errors.nom" />
                            </div>
                            <div class="space-y-2">
                                <Label for="type">Type de Fournisseur</Label>
                                <Input id="type" v-model="form.type" placeholder="Ex: Prestataire de service" />
                                <InputError :message="form.errors.type" />
                            </div>
                            <div class="space-y-2">
                                <Label for="categorie">Catégorie</Label>
                                <Input id="categorie" v-model="form.categorie" placeholder="Ex: Informatique" />
                                <InputError :message="form.errors.categorie" />
                            </div>
                            <div class="space-y-2">
                                <Label for="description">Description / Notes</Label>
                                <Input
                                    id="description"
                                    v-model="form.description"
                                    placeholder="Courte description"
                                />
                                <InputError :message="form.errors.description" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-4 border-b pb-2 text-lg font-medium text-gray-900">
                            Contacts & Localisation
                        </h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <div class="space-y-2">
                                <Label for="contact_nom">Nom du Contact Principal</Label>
                                <Input id="contact_nom" v-model="form.contact_nom" placeholder="Amadou MAR" />
                                <InputError :message="form.errors.contact_nom" />
                            </div>
                            <div class="space-y-2">
                                <Label for="contact_telephone">Numéro de Téléphone</Label>
                                <Input
                                    id="contact_telephone"
                                    v-model="form.contact_telephone"
                                    placeholder="+221 3..."
                                />
                                <InputError :message="form.errors.contact_telephone" />
                            </div>
                            <div class="space-y-2">
                                <Label for="contact_email">Adresse E-mail</Label>
                                <Input
                                    id="contact_email"
                                    v-model="form.contact_email"
                                    type="email"
                                    placeholder="cofina@cofina.com"
                                />
                                <InputError :message="form.errors.contact_email" />
                            </div>
                            <div class="space-y-2 lg:col-span-2">
                                <Label for="adresse_physique">Adresse Physique</Label>
                                <Input
                                    id="adresse_physique"
                                    v-model="form.adresse_physique"
                                    placeholder="Rue, Ville, Pays"
                                />
                                <InputError :message="form.errors.adresse_physique" />
                            </div>
                            <div class="space-y-2">
                                <Label for="site_web">Site Web</Label>
                                <Input
                                    id="site_web"
                                    v-model="form.site_web"
                                    placeholder="www.cofinasenegal.com"
                                />
                                <InputError :message="form.errors.site_web" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-4 border-b pb-2 text-lg font-medium text-gray-900">
                            Informations Comptables
                        </h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="compte_transit_paiement">Compte Transit Paiement Facture</Label>
                                <Input
                                    id="compte_transit_paiement"
                                    :model-value="form.compte_transit_paiement"
                                    inputmode="numeric"
                                    maxlength="12"
                                    placeholder="Ex: 100000000001"
                                    :class="fieldError('compte_transit_paiement') ? 'border-red-500' : ''"
                                    @input="onAccountInput('compte_transit_paiement', $event)"
                                />
                                <p class="text-xs text-muted-foreground">Exactement 12 chiffres (ou vide)</p>
                                <InputError :message="fieldError('compte_transit_paiement')" />
                            </div>
                            <div class="space-y-2">
                                <Label for="compte_avance_acompte">Compte Avance et Acompte</Label>
                                <Input
                                    id="compte_avance_acompte"
                                    :model-value="form.compte_avance_acompte"
                                    inputmode="numeric"
                                    maxlength="12"
                                    placeholder="Ex: 100000000002"
                                    :class="fieldError('compte_avance_acompte') ? 'border-red-500' : ''"
                                    @input="onAccountInput('compte_avance_acompte', $event)"
                                />
                                <p class="text-xs text-muted-foreground">Exactement 12 chiffres (ou vide)</p>
                                <InputError :message="fieldError('compte_avance_acompte')" />
                            </div>
                            <div class="space-y-2">
                                <Label for="compte_client_interne">Compte Client Interne</Label>
                                <Input
                                    id="compte_client_interne"
                                    :model-value="form.compte_client_interne"
                                    inputmode="numeric"
                                    maxlength="12"
                                    placeholder="Ex: 100000000003"
                                    :class="fieldError('compte_client_interne') ? 'border-red-500' : ''"
                                    @input="onAccountInput('compte_client_interne', $event)"
                                />
                                <p class="text-xs text-muted-foreground">Exactement 12 chiffres (ou vide)</p>
                                <InputError :message="fieldError('compte_client_interne')" />
                            </div>
                            <div class="space-y-2">
                                <Label for="banque_id">Banque</Label>
                                <select
                                    id="banque_id"
                                    v-model="form.banque_id"
                                    class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-gray-400"
                                >
                                    <option value="">-- Sélectionner une banque --</option>
                                    <option v-for="b in props.banques" :key="b.id" :value="b.id">
                                        {{ b.nom }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.banque_id" />
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showModal = false">Annuler</Button>
                        <Button
                            type="submit"
                            class="bg-purple-600 hover:bg-purple-700"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="showViewModal" @update:open="showViewModal = $event">
            <DialogContent class="max-h-[90vh] w-full max-w-3xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Détails du Fournisseur</DialogTitle>
                </DialogHeader>
                <div v-if="selectedFournisseur" class="space-y-6 py-4">
                    <div>
                        <h3 class="mb-4 border-b pb-2 text-lg font-medium text-gray-900">
                            Informations Générales
                        </h3>
                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ selectedFournisseur.categorie || '-' }}
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-sm font-medium text-gray-500">Description / Notes</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ selectedFournisseur.description || '-' }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div>
                        <h3 class="mb-4 border-b pb-2 text-lg font-medium text-gray-900">
                            Contacts & Localisation
                        </h3>
                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Nom du Contact Principal</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ selectedFournisseur.contact_nom || '-' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Téléphone</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ selectedFournisseur.contact_telephone || '-' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Adresse E-mail</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ selectedFournisseur.contact_email || '-' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Site Web</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ selectedFournisseur.site_web || '-' }}
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-sm font-medium text-gray-500">Adresse Physique</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ selectedFournisseur.adresse_physique || '-' }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div>
                        <h3 class="mb-4 border-b pb-2 text-lg font-medium text-gray-900">
                            Informations Comptables
                        </h3>
                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Compte Transit</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ selectedFournisseur.compte_transit_paiement || '-' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Compte Avance / Acompte</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ selectedFournisseur.compte_avance_acompte || '-' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Compte Client Interne</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ selectedFournisseur.compte_client_interne || '-' }}
                                </dd>
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
