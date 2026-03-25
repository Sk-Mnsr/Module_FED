<script setup lang="ts">
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import FormSection from '@/components/FormSection.vue';
import { Plus, Trash2, Minus } from 'lucide-vue-next';

const props = defineProps<{
    fournisseurs: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Appels d\'Offres',
        href: '/appel-offres',
    },
    {
        title: 'Nouveau',
        href: '#',
    },
];

interface CritereForm {
    nom: string;
    ponderation: number;
    type: string;
    note_maximale: number;
}

const makeCritere = (): CritereForm => ({
    nom: '',
    ponderation: 1,
    type: 'technique',
    note_maximale: 100,
});

const form = useForm({
    objet: '',
    description: '',
    date_lancement: '',
    date_limite_soumission: '',
    type_publication: 'interne',
    criteres: [
        { nom: 'Conformité technique', ponderation: 30, type: 'technique', note_maximale: 10 },
        { nom: 'Expérience / Références', ponderation: 20, type: 'experience', note_maximale: 10 },
        { nom: 'Délai de livraison', ponderation: 20, type: 'delais', note_maximale: 10 },
        { nom: 'Offre financière', ponderation: 20, type: 'financier', note_maximale: 10 },
        { nom: 'Documents administratifs', ponderation: 10, type: 'docs', note_maximale: 10 }
    ],
    fournisseurs: [] as number[],
    dao_file: null as File | null,
    cahier_charges_file: null as File | null,
});

const onDaoChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length) form.dao_file = target.files[0];
};

const onCcChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length) form.cahier_charges_file = target.files[0];
};

const typeOptions = [
    { value: 'technique', label: 'Technique' },
    { value: 'financier', label: 'Financier' },
    { value: 'delais', label: 'Délais' },
    { value: 'experience', label: 'Expérience' },
    { value: 'docs', label: 'Documents administratifs' },
    
];

const publicationOptions = [
    { value: 'interne', label: 'Interne (Utilisateurs du système)' },
    { value: 'externe', label: 'Externe (Lien public)' },
];

const addCritere = () => {
    form.criteres.push(makeCritere());
};

const removeCritere = (index: number) => {
    form.criteres.splice(index, 1);
};

const submit = () => {
    if (form.criteres.length === 0) {
        alert('Veuillez ajouter au moins un critère d\'évaluation.');
        return;
    }

    form.post('/appel-offres', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Nouvel Appel d'Offres" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <h1 class="text-3xl font-bold text-gray-900">Nouvel Appel d'Offres</h1>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <!-- Informations Générales -->
                <FormSection title="Informations générales" :columns="2">
                    <div class="md:col-span-2">
                        <Label for="objet" class="text-base font-medium text-gray-700">Objet de l'appel d'offres</Label>
                        <Input id="objet" v-model="form.objet" type="text" class="mt-1.5 border-gray-300" required />
                        <InputError :message="form.errors.objet" />
                    </div>
                    <div class="md:col-span-2">
                        <Label for="description" class="text-base font-medium text-gray-700">Description détaillée</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            class="mt-1.5 w-full rounded-md border border-gray-300 px-3 py-2 text-base focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                            required
                        ></textarea>
                        <InputError :message="form.errors.description" />
                    </div>
                    <div>
                        <Label for="date_lancement" class="text-base font-medium text-gray-700">Date de lancement</Label>
                        <Input id="date_lancement" v-model="form.date_lancement" type="date" class="mt-1.5 border-gray-300" />
                        <InputError :message="form.errors.date_lancement" />
                    </div>
                    <div>
                        <Label for="date_limite_soumission" class="text-base font-medium text-gray-700">Date limite de soumission</Label>
                        <Input id="date_limite_soumission" v-model="form.date_limite_soumission" type="datetime-local" class="mt-1.5 border-gray-300" required />
                        <InputError :message="form.errors.date_limite_soumission" />
                    </div>
                    <div class="md:col-span-2">
                        <Label for="type_publication" class="text-base font-medium text-gray-700">Type de publication</Label>
                        <select
                            id="type_publication"
                            v-model="form.type_publication"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900"
                            required
                        >
                            <option v-for="option in publicationOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.type_publication" />
                    </div>
                </FormSection>

                <!-- Critères d'évaluation -->
                <FormSection title="Critères d'évaluation" :columns="1">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-sm text-gray-600">Définissez les critères de notation pour le comité d'évaluation.</p>
                        <Button type="button" variant="outline" size="sm" @click="addCritere" class="p-2 h-8 w-8">
                            <Plus class="h-4 w-4" />
                        </Button>
                    </div>

                    <div v-for="(critere, index) in form.criteres" :key="index" class="rounded-md border border-gray-200 p-4 mb-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <Label :for="`nom-${index}`" class="text-base font-medium text-gray-700">Nom du critère</Label>
                                <Input :id="`nom-${index}`" v-model="critere.nom" type="text" class="mt-1.5 border-gray-300" required />
                                <InputError :message="form.errors[`criteres.${index}.nom` as keyof typeof form.errors]" />
                            </div>
                            <div>
                                <Label :for="`type-${index}`" class="text-base font-medium text-gray-700">Type de critère</Label>
                                <select
                                    :id="`type-${index}`"
                                    v-model="critere.type"
                                    class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900"
                                    required
                                >
                                    <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                                <InputError :message="form.errors[`criteres.${index}.type` as keyof typeof form.errors]" />
                            </div>
                            <div>
                                <Label :for="`max-${index}`" class="text-base font-medium text-gray-700">Note maximale (ex: 20)</Label>
                                <!-- @ts-ignore -->
                                <Input :id="`max-${index}`" v-model.number="critere.note_maximale" type="number" min="1" step="0.5" class="mt-1.5 border-gray-300" required />
                                <InputError :message="form.errors[`criteres.${index}.note_maximale` as keyof typeof form.errors]" />
                            </div>
                            <div>
                                <Label :for="`pond-${index}`" class="text-base font-medium text-gray-700">Coefficient / Pondération</Label>
                                <!-- @ts-ignore -->
                                <Input :id="`pond-${index}`" v-model.number="critere.ponderation" type="number" min="1" step="1" class="mt-1.5 border-gray-300" required />
                                <InputError :message="form.errors[`criteres.${index}.ponderation` as keyof typeof form.errors]" />
                            </div>
                        </div>
                        <div v-if="form.criteres.length > 1" class="mt-3 flex justify-end">
                            <Button type="button" variant="ghost" class="text-red-600 hover:text-red-700 hover:bg-red-50 p-2 h-9 w-9" @click="removeCritere(index)" title="Supprimer ce critère">
                                <Minus class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </FormSection>

                <!-- Documents joints -->
                <FormSection title="Documents de l'Appel d'Offres" :columns="2">
                    <div>
                        <Label for="dao_file" class="text-base font-medium text-gray-700">Dossier d'Appel d'Offres (DAO)</Label>
                        <Input id="dao_file" type="file" @change="onDaoChange" class="mt-1.5 border-gray-300" />
                        <InputError :message="form.errors.dao_file" />
                    </div>
                    <div>
                        <Label for="cahier_charges_file" class="text-base font-medium text-gray-700">Cahiers des charges</Label>
                        <Input id="cahier_charges_file" type="file" @change="onCcChange" class="mt-1.5 border-gray-300" />
                        <InputError :message="form.errors.cahier_charges_file" />
                    </div>
                </FormSection>

                <!-- Fournisseurs -->
                <FormSection title="Fournisseurs concernés" :columns="1">
                    <p class="text-sm text-gray-600 mb-4">Sélectionnez les fournisseurs à qui cet appel d'offres s'adresse.</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 max-h-60 overflow-y-auto border border-gray-200 p-4 rounded-md bg-white">
                        <label v-for="fournisseur in fournisseurs" :key="fournisseur.id" class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                            <input type="checkbox" :value="fournisseur.id" v-model="form.fournisseurs" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">{{ fournisseur.nom }} <span class="text-gray-400 text-xs">({{ fournisseur.contact_email }})</span></span>
                        </label>
                    </div>
                    <InputError :message="form.errors.fournisseurs" />
                </FormSection>

                <div class="flex justify-end gap-2 mt-4">
                    <Link href="/appel-offres">
                        <Button type="button" variant="outline">Annuler</Button>
                    </Link>
                    <Button type="submit" :disabled="form.processing" class="bg-purple-600 hover:bg-purple-700 text-white">
                        {{ form.processing ? 'Création...' : 'Créer l\'Appel d\'Offres' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
