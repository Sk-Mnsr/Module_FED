<script setup lang="ts">
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import FormSection from '@/components/FormSection.vue';

const props = defineProps<{
    appelOffre: any;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Appels d\'Offres',
        href: '/appel-offres',
    },
    {
        title: props.appelOffre.reference,
        href: `/appel-offres/${props.appelOffre.id}`,
    },
    {
        title: 'Dépôt d\'offre',
        href: '#',
    },
];

const form = useForm({
    nom_fournisseur: '',
    email_fournisseur: '',
    contact_nom: '',
    contact_telephone: '',
    articles: [
        { designation: '', quantite: 1, prix_unitaire_ht: 0 }
    ],
    commentaires: '',
    offre_technique: null as File | null,
    offre_financiere: null as File | null,
    rccm_file: null as File | null,
    ninea_file: null as File | null,
    fiche_technique_file: null as File | null,
    references_file: null as File | null,
});

const onFileChange = (field: keyof typeof form, event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        (form as any)[field] = target.files[0];
    }
};

import { computed } from 'vue';
import { Plus, Minus } from 'lucide-vue-next';

// ... computed and helper methods
const montantTotalHT = computed(() => {
    return form.articles.reduce((sum, item) => sum + ((item.quantite || 0) * (item.prix_unitaire_ht || 0)), 0);
});

const addArticle = () => {
    form.articles.push({ designation: '', quantite: 1, prix_unitaire_ht: 0 });
};

const removeArticle = (index: number) => {
    form.articles.splice(index, 1);
};

const submit = () => {
    if (form.articles.length === 0) {
        alert("Veuillez ajouter au moins un article dans votre offre financière.");
        return;
    }
    form.transform((data) => ({
        ...data,
        montant: montantTotalHT.value,
        details_financiers: data.articles,
    })).post(`/appel-offres/${props.appelOffre.id}/offres`, {
        preserveScroll: true,
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Déposer une offre" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-4xl mx-auto">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-2">
                <h2 class="text-blue-800 font-semibold mb-1">Dépôt d'offre pour : {{ appelOffre.reference }}</h2>
                <p class="text-blue-600 text-sm">Organisé par {{ appelOffre.creator?.name || 'Notre Entreprise' }}</p>
                <p class="text-red-600 text-sm font-medium mt-2">
                    Date limite : {{ new Date(appelOffre.date_limite_soumission).toLocaleString('fr-FR') }}
                </p>
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <!-- Informations Fournisseur -->
                <FormSection title="Informations de l'Entreprise" :columns="2">
                    <div>
                        <Label for="nom_fournisseur" class="text-base font-medium text-gray-700">Nom de l'entreprise</Label>
                        <Input id="nom_fournisseur" v-model="form.nom_fournisseur" type="text" class="mt-1.5 border-gray-300" required />
                        <InputError :message="form.errors.nom_fournisseur" />
                    </div>
                    <div>
                        <Label for="email_fournisseur" class="text-base font-medium text-gray-700">Adresse Email Officielle</Label>
                        <Input id="email_fournisseur" v-model="form.email_fournisseur" type="email" class="mt-1.5 border-gray-300" required />
                        <InputError :message="form.errors.email_fournisseur" />
                    </div>
                    <div>
                        <Label for="contact_nom" class="text-base font-medium text-gray-700">Nom du contact</Label>
                        <Input id="contact_nom" v-model="form.contact_nom" type="text" class="mt-1.5 border-gray-300" />
                        <InputError :message="form.errors.contact_nom" />
                    </div>
                    <div>
                        <Label for="contact_telephone" class="text-base font-medium text-gray-700">Téléphone du contact</Label>
                        <Input id="contact_telephone" v-model="form.contact_telephone" type="text" class="mt-1.5 border-gray-300" />
                        <InputError :message="form.errors.contact_telephone" />
                    </div>
                </FormSection>

                <!-- Détail financier dynamique -->
                <div class="border border-blue-100 rounded-lg p-6 bg-white shadow-sm mt-4">
                    <div class="flex items-center justify-between mb-4 border-b pb-4 border-gray-100">
                        <h3 class="text-xl font-medium text-gray-800">Détail financier (Hors Taxes)</h3>
                        <Button type="button" variant="outline" size="sm" @click="addArticle" class="flex items-center gap-1">
                            <Plus class="h-4 w-4" /> Ajouter ligne
                        </Button>
                    </div>
                    
                    <div class="space-y-4">
                        <div v-for="(article, index) in form.articles" :key="index" class="flex flex-col sm:flex-row gap-4 items-end bg-gray-50 p-4 rounded-md border border-gray-200 relative mb-4">
                            <div class="w-full sm:w-1/2">
                                <Label :for="'designation-' + index" class="text-sm font-medium">Désignation</Label>
                                <Input :id="'designation-' + index" v-model="article.designation" type="text" placeholder="Ex: Ordinateur" required class="mt-1" />
                            </div>
                            <div class="w-full sm:w-1/4">
                                <Label :for="'qte-' + index" class="text-sm font-medium">Quantité</Label>
                                <Input :id="'qte-' + index" v-model.number="article.quantite" type="number" min="1" required class="mt-1" />
                            </div>
                            <div class="w-full sm:w-1/4">
                                <Label :for="'pu-' + index" class="text-sm font-medium">Prix Unitaire HT</Label>
                                <div class="relative mt-1">
                                    <Input :id="'pu-' + index" v-model.number="article.prix_unitaire_ht" type="number" min="0" required class="pl-3 pr-12" />
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 text-sm">CFA</span>
                                    </div>
                                </div>
                            </div>
                            <div v-if="form.articles.length > 1" class="absolute -top-3 -right-3 z-10 shrink-0">
                                <Button type="button" variant="destructive" size="icon" class="h-7 w-7 rounded-full shadow" @click="removeArticle(index)">
                                    <Minus class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end items-center bg-blue-50 p-4 rounded-md border border-blue-100">
                        <span class="text-gray-700 font-medium mr-4">Total HT Estimatif :</span>
                        <span class="text-xl font-bold tracking-wide text-gray-900">{{ montantTotalHT.toLocaleString('fr-FR') }} FCFA</span>
                    </div>
                </div>

                <!-- Documents administratifs -->
                <FormSection title="Documents Administratifs" :columns="2">
                    <div>
                        <Label for="rccm_file" class="text-base font-medium text-gray-700">RCCM</Label>
                        <Input id="rccm_file" type="file" class="mt-1.5 border-gray-300" @change="onFileChange('rccm_file', $event)" />
                        <InputError :message="form.errors.rccm_file" />
                    </div>
                    <div>
                        <Label for="ninea_file" class="text-base font-medium text-gray-700">NINEA</Label>
                        <Input id="ninea_file" type="file" class="mt-1.5 border-gray-300" @change="onFileChange('ninea_file', $event)" />
                        <InputError :message="form.errors.ninea_file" />
                    </div>
                    <div>
                        <Label for="fiche_technique_file" class="text-base font-medium text-gray-700">Fiche technique de l'entreprise</Label>
                        <Input id="fiche_technique_file" type="file" class="mt-1.5 border-gray-300" @change="onFileChange('fiche_technique_file', $event)" />
                        <InputError :message="form.errors.fiche_technique_file" />
                    </div>
                    <div>
                        <Label for="references_file" class="text-base font-medium text-gray-700">Références Clients</Label>
                        <Input id="references_file" type="file" class="mt-1.5 border-gray-300" @change="onFileChange('references_file', $event)" />
                        <InputError :message="form.errors.references_file" />
                    </div>
                </FormSection>

                <!-- Pièces de l'offre -->
                <FormSection title="Pièces Constitutives de l'Offre" :columns="2">
                    <p class="text-sm text-gray-600 mb-4 col-span-2">
                        Ces offres resteront strictement confidentielles jusqu'à la date confirmée d'ouverture des plis.
                    </p>
                    
                    <div>
                        <Label for="offre_technique" class="text-base font-medium text-gray-700">Offre Technique <span class="text-red-500">*</span></Label>
                        <Input id="offre_technique" type="file" class="mt-1.5 border-gray-300" @change="onFileChange('offre_technique', $event)" required />
                        <InputError :message="form.errors.offre_technique" />
                    </div>
                    <div>
                        <Label for="offre_financiere" class="text-base font-medium text-gray-700">Offre Financière <span class="text-red-500">*</span></Label>
                        <Input id="offre_financiere" type="file" class="mt-1.5 border-gray-300" @change="onFileChange('offre_financiere', $event)" required />
                        <InputError :message="form.errors.offre_financiere" />
                    </div>
                </FormSection>

                <!-- Informations Supplémentaires -->
                <div class="border border-gray-200 rounded-lg p-6 bg-white shadow-sm mt-4">
                    <Label for="commentaires" class="text-base font-medium text-gray-700 mb-2 block">Informations supplémentaires (Optionnel)</Label>
                    <textarea
                        id="commentaires"
                        v-model="form.commentaires"
                        rows="4"
                        placeholder="Toute remarque ou précision utile pour le comité concernant l'offre technique ou financière..."
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm p-3 block"
                    ></textarea>
                </div>

                <div class="flex justify-end gap-2 mt-4">
                    <Link :href="`/appel-offres/${appelOffre.id}`">
                        <Button type="button" variant="outline">Annuler</Button>
                    </Link>
                    <Button type="submit" :disabled="form.processing" class="bg-green-600 hover:bg-green-700 text-white">
                        <span v-if="form.processing">Traitement sécurisé en cours...</span>
                        <span v-else>Soumettre mon offre</span>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
