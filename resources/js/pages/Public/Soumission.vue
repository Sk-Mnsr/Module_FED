<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AlertCircle, CheckCircle2, UploadCloud, Plus, Minus } from 'lucide-vue-next';

interface AppelOffre {
    id: number;
    reference: string;
    objet: string;
    description: string;
    date_limite_soumission: string;
    dao_path?: string;
    cahier_charges_path?: string;
}

interface Fournisseur {
    id: number;
    nom: string;
    email: string;
}

const props = defineProps<{
    appelOffre: AppelOffre;
    fournisseur: Fournisseur;
    hasSubmitted: boolean;
}>();

const form = useForm({
    articles: [
        { designation: '', quantite: 1, prix_unitaire_ht: 0 }
    ],
    offre_technique: null as File | null,
    offre_financiere: null as File | null,
    ninea: null as File | null,
    rccm: null as File | null,
    commentaires: '',
});

const isSubmitting = ref(false);
const showSuccess = ref(false);

const montantTotalHT = computed(() => {
    return form.articles.reduce((sum, item) => sum + ((item.quantite || 0) * (item.prix_unitaire_ht || 0)), 0);
});

const addArticle = () => {
    form.articles.push({ designation: '', quantite: 1, prix_unitaire_ht: 0 });
};

const removeArticle = (index: number) => {
    form.articles.splice(index, 1);
};

const submitOffer = () => {
    if (form.articles.length === 0) {
        alert("Veuillez ajouter au moins un article dans votre offre financière.");
        return;
    }
    isSubmitting.value = true;
    form.transform((data) => ({
        ...data,
        montant: montantTotalHT.value,
    })).post(window.location.href, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            showSuccess.value = true;
            isSubmitting.value = false;
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};

const handleFileUpload = (event: Event, field: keyof typeof form) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        form[field] = target.files[0] as any;
    } else {
        form[field] = null as any;
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('fr-FR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>

<template>
    <Head title="Soumission d'Offre" />

    <div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-3xl">
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Portail Fournisseur - Soumission
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Vous soumettez au nom de <span class="font-medium text-gray-900">{{ fournisseur.nom }}</span>
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-3xl">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                
                <div class="mb-8 border-b pb-6">
                    <h3 class="text-xl font-semibold text-gray-800">Appel d'Offres : {{ appelOffre.reference }}</h3>
                    <p class="text-gray-600 mt-2">{{ appelOffre.objet }}</p>
                    <div class="mt-4 flex items-center p-4 bg-blue-50 rounded-md">
                        <AlertCircle class="h-5 w-5 text-blue-400 mr-2" />
                        <span class="text-sm text-blue-700">Date limite de soumission : <strong>{{ formatDate(appelOffre.date_limite_soumission) }}</strong></span>
                    </div>
                </div>

                <!-- Documents à télécharger -->
                <div class="mb-8" v-if="appelOffre.dao_path || appelOffre.cahier_charges_path">
                    <h4 class="text-md font-semibold text-gray-800 mb-3 border-b pb-2">Documents de consultation</h4>
                    <div class="flex flex-wrap gap-4">
                        <a 
                            v-if="appelOffre.dao_path" 
                            :href="'/storage/' + appelOffre.dao_path" 
                            target="_blank"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 text-sm font-medium text-blue-800 transition shadow-sm"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Télécharger le DAO
                        </a>
                        
                        <a 
                            v-if="appelOffre.cahier_charges_path" 
                            :href="'/storage/' + appelOffre.cahier_charges_path" 
                            target="_blank"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-50 border border-indigo-200 rounded-md hover:bg-indigo-100 text-sm font-medium text-indigo-800 transition shadow-sm"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Télécharger le Cahier des charges
                        </a>
                    </div>
                </div>

                <div v-if="props.hasSubmitted || showSuccess" class="rounded-md bg-green-50 p-6 text-center">
                    <CheckCircle2 class="mx-auto h-12 w-12 text-green-400" />
                    <h3 class="mt-2 text-lg font-medium text-green-800">Offre soumise avec succès</h3>
                    <p class="mt-2 text-sm text-green-700">
                        Nous avons bien reçu votre proposition technique et financière. Elle sera évaluée confidentiellement par le comité après la date de clôture.
                    </p>
                </div>

                <form v-else class="space-y-6" @submit.prevent="submitOffer">
                    
                    <div class="border rounded-md p-4 bg-gray-50 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-800">Détail financier <span class="text-red-500">*</span></h3>
                            <Button type="button" variant="outline" size="sm" @click="addArticle" class="flex items-center gap-1">
                                <Plus class="h-4 w-4" /> Ajouter une ligne
                            </Button>
                        </div>
                        
                        <div class="space-y-4">
                            <div v-for="(article, index) in form.articles" :key="index" class="flex flex-col sm:flex-row gap-4 items-end bg-white p-3 rounded shadow-sm border border-gray-100 relative">
                                <div class="w-full sm:w-1/2">
                                    <Label :for="'designation-' + index">Désignation</Label>
                                    <Input :id="'designation-' + index" v-model="article.designation" type="text" placeholder="Ex: Ordinateur Dell Latitude" required class="mt-1" />
                                </div>
                                <div class="w-full sm:w-1/4">
                                    <Label :for="'qte-' + index">Quantité</Label>
                                    <Input :id="'qte-' + index" v-model.number="article.quantite" type="number" min="1" required class="mt-1" />
                                </div>
                                <div class="w-full sm:w-1/4">
                                    <Label :for="'pu-' + index">Prix Unitaire HT</Label>
                                    <div class="relative mt-1">
                                        <Input :id="'pu-' + index" v-model.number="article.prix_unitaire_ht" type="number" min="0" required class="pl-3 pr-10" />
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 text-sm">CFA</span>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="form.articles.length > 1" class="absolute -top-2 -right-2 sm:static sm:mb-1">
                                    <Button type="button" variant="destructive" size="icon" class="h-8 w-8 rounded-full" @click="removeArticle(index)">
                                        <Minus class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end items-center border-t pt-4">
                            <span class="text-gray-700 font-medium mr-4">Total HT Estimatif :</span>
                            <span class="text-xl font-bold text-gray-900">{{ montantTotalHT.toLocaleString('fr-FR') }} FCFA</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <!-- Offre Technique -->
                        <div>
                            <Label>Offre Technique (PDF, ZIP max 10MB) <span class="text-red-500">*</span></Label>
                            <label
                                class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md cursor-pointer hover:border-blue-400 transition"
                                :class="{'border-green-500 bg-green-50': form.offre_technique}"
                            >
                                <div class="space-y-1 text-center">
                                    <UploadCloud v-if="!form.offre_technique" class="mx-auto h-12 w-12 text-gray-400" />
                                    <CheckCircle2 v-else class="mx-auto h-12 w-12 text-green-500" />
                                    <div class="flex text-sm text-gray-600 justify-center">
                                        <span class="relative cursor-pointer rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                            {{ form.offre_technique ? form.offre_technique.name : 'Sélectionner un fichier' }}
                                            <input :id="'tech_file'" name="offre_technique" type="file" class="sr-only" required accept=".pdf,.zip" @change="e => handleFileUpload(e, 'offre_technique')">
                                        </span>
                                    </div>
                                </div>
                            </label>
                            <p v-if="form.errors.offre_technique" class="mt-2 text-sm text-red-600">{{ form.errors.offre_technique }}</p>
                        </div>

                        <!-- Offre Financière -->
                        <div>
                            <Label>Offre Financière (PDF, Excel, ZIP) <span class="text-red-500">*</span></Label>
                            <label
                                class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md cursor-pointer hover:border-blue-400 transition"
                                :class="{'border-green-500 bg-green-50': form.offre_financiere}"
                            >
                                <div class="space-y-1 text-center">
                                    <UploadCloud v-if="!form.offre_financiere" class="mx-auto h-12 w-12 text-gray-400" />
                                    <CheckCircle2 v-else class="mx-auto h-12 w-12 text-green-500" />
                                    <div class="flex text-sm text-gray-600 justify-center">
                                        <span class="relative cursor-pointer rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                            {{ form.offre_financiere ? form.offre_financiere.name : 'Sélectionner un fichier' }}
                                            <input :id="'fin_file'" name="offre_financiere" type="file" class="sr-only" required accept=".pdf,.xls,.xlsx,.zip" @change="e => handleFileUpload(e, 'offre_financiere')">
                                        </span>
                                    </div>
                                </div>
                            </label>
                            <p v-if="form.errors.offre_financiere" class="mt-2 text-sm text-red-600">{{ form.errors.offre_financiere }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 mt-4 pt-4 border-t">
                        <!-- Documents Administratifs -->
                        <div>
                            <Label>NINEA / Registre de Commerce</Label>
                            <input
                                type="file"
                                class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                accept=".pdf,.jpg,.png"
                                @change="e => handleFileUpload(e, 'ninea')"
                            >
                            <p v-if="form.errors.ninea" class="mt-1 text-sm text-red-600">{{ form.errors.ninea }}</p>
                        </div>
                        <div>
                            <Label>Quitus Fiscal / Autres</Label>
                            <input
                                type="file"
                                class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                accept=".pdf,.jpg,.png"
                                @change="e => handleFileUpload(e, 'rccm')"
                            >
                            <p v-if="form.errors.rccm" class="mt-1 text-sm text-red-600">{{ form.errors.rccm }}</p>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t">
                        <Label for="commentaires">Informations supplémentaires (Optionnel)</Label>
                        <textarea
                            id="commentaires"
                            v-model="form.commentaires"
                            rows="3"
                            placeholder="Toute remarque, précision ou information utile a ajouter..."
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm p-3"
                        ></textarea>
                        <p v-if="form.errors.commentaires" class="mt-1 text-sm text-red-600">{{ form.errors.commentaires }}</p>
                    </div>

                    <div class="pt-6">
                        <Button type="submit" class="w-full flex justify-center bg-blue-600 hover:bg-blue-700" :disabled="isSubmitting || form.processing">
                            <span v-if="isSubmitting || form.processing">Envoi en cours...</span>
                            <span v-else>Soumettre l'offre définitivement</span>
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
