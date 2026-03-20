<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import FormSection from '@/components/FormSection.vue';
import { Plus, Trash2 } from 'lucide-vue-next';

interface Department {
    id: number;
    name: string;
    code?: string;
}

interface SousCategorie {
    id: number;
    sous_categorie: string;
    code: string;
}

interface Categorie {
    id: number;
    categorie: string;
    code: string;
    sous_categories: SousCategorie[];
}

interface BudgetLineForm {
    label: string;
    type: string;
    categorie_depense_id: number | null;
    sous_categorie: string;
    rubrique: string;
    sous_rubrique: string;
    montant_estime: number | null;
    date_souhaitee_execution: string | null;
    justification: string;
    compte_gl: string;
}

interface Props {
    departments: Department[];
    typologies: Array<{ type: string; libelle: string }>;
    categories: Categorie[];
    rubriqueSuggestions: string[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Budgets', href: '/budgets' },
    { title: 'Créer', href: '#' },
];

const makeLine = (): BudgetLineForm => ({
    label: '',
    type: '',
    categorie_depense_id: null,
    sous_categorie: '',
    rubrique: '',
    sous_rubrique: '',
    montant_estime: null,
    date_souhaitee_execution: null,
    justification: '',
    compte_gl: '',
});

const form = useForm({
    department_id: null as number | null,
    year: new Date().getFullYear(),
    lines: [makeLine()],
});

const getSousCategories = (categorieId: number | null) => {
    if (!categorieId) return [];
    const cat = props.categories.find(c => c.id === categorieId);
    return cat?.sous_categories ?? [];
};

const onCategorieChange = (line: BudgetLineForm) => {
    line.sous_categorie = '';
};

const addLine = () => {
    form.lines.push(makeLine());
};

const removeLine = (index: number) => {
    if (form.lines.length > 1) {
        form.lines.splice(index, 1);
    }
};

const submit = () => {
    form.post('/budgets', { preserveScroll: true });
};
</script>

<template>
    <Head title="Créer un budget" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <h1 class="text-3xl font-bold text-gray-900">Créer un budget</h1>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <FormSection :columns="2">
                    <div>
                        <Label for="department_id" class="text-base font-medium text-gray-700">Département</Label>
                        <select
                            id="department_id"
                            v-model="form.department_id"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900"
                        >
                            <option :value="null">-- Sélectionner --</option>
                            <option v-for="department in props.departments" :key="department.id" :value="department.id">
                                {{ department.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.department_id" />
                    </div>

                    <div>
                        <Label for="year" class="text-base font-medium text-gray-700">Année</Label>
                        <Input id="year" v-model="form.year" type="number" min="2000" max="2100" class="mt-1.5 border-gray-300" />
                        <InputError :message="form.errors.year" />
                    </div>
                </FormSection>

                <FormSection title="Lignes budgétaires" :columns="1">
                    <p class="text-sm text-gray-500">
                        Le code ligne budgétaire est généré automatiquement (ex: IT – OPEX – ELC – 001).
                    </p>
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-600">Définir les lignes budgétaires</p>
                        <Button type="button" variant="outline" @click="addLine">
                            <Plus class="mr-2 h-4 w-4" /> Ajouter une ligne
                        </Button>
                    </div>

                    <div v-for="(line, index) in form.lines" :key="index" class="rounded-md border border-gray-200 p-4">
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="md:col-span-2">
                                <Label :for="`label-${index}`" class="text-base font-medium text-gray-700">Libellé de la dépense</Label>
                                <Input :id="`label-${index}`" v-model="line.label" type="text" class="mt-1.5 border-gray-300" />
                                <InputError :message="form.errors[`lines.${index}.label` as keyof typeof form.errors]" />
                            </div>
                            <div>
                                <Label :for="`type-${index}`" class="text-base font-medium text-gray-700">Type</Label>
                                <select
                                    :id="`type-${index}`"
                                    v-model="line.type"
                                    class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900"
                                >
                                    <option value="">-- Sélectionner --</option>
                                    <option v-for="typology in props.typologies" :key="typology.type" :value="typology.type">
                                        {{ typology.type }} - {{ typology.libelle }}
                                    </option>
                                </select>
                                <InputError :message="form.errors[`lines.${index}.type` as keyof typeof form.errors]" />
                            </div>
                            <div>
                                <Label :for="`categorie-${index}`" class="text-base font-medium text-gray-700">Catégorie dépense</Label>
                                <select
                                    :id="`categorie-${index}`"
                                    v-model="line.categorie_depense_id"
                                    class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900"
                                    @change="onCategorieChange(line)"
                                >
                                    <option :value="null">-- Sélectionner --</option>
                                    <option v-for="cat in props.categories" :key="cat.id" :value="cat.id">
                                        {{ cat.categorie }} ({{ cat.code }})
                                    </option>
                                </select>
                                <InputError :message="form.errors[`lines.${index}.categorie_depense_id` as keyof typeof form.errors]" />
                            </div>
                            <div>
                                <Label :for="`sous-categorie-${index}`" class="text-base font-medium text-gray-700">Sous catégorie</Label>
                                <Input
                                    :id="`sous-categorie-${index}`"
                                    v-model="line.sous_categorie"
                                    type="text"
                                    class="mt-1.5 border-gray-300"
                                    :list="`sous-categorie-list-${index}`"
                                    placeholder="Saisir ou sélectionner une sous-catégorie..."
                                    :disabled="!line.categorie_depense_id"
                                />
                                <datalist :id="`sous-categorie-list-${index}`">
                                    <option v-for="sc in getSousCategories(line.categorie_depense_id)" :key="sc.id" :value="sc.sous_categorie" />
                                </datalist>
                                <InputError :message="form.errors[`lines.${index}.sous_categorie` as keyof typeof form.errors]" />
                            </div>
                            <div>
                                <Label :for="`rubrique-${index}`" class="text-base font-medium text-gray-700">Rubrique dépenses</Label>
                                <Input
                                    :id="`rubrique-${index}`"
                                    v-model="line.rubrique"
                                    type="text"
                                    class="mt-1.5 border-gray-300"
                                    :list="`rubrique-list-${index}`"
                                    placeholder="Saisir une rubrique..."
                                />
                                <datalist :id="`rubrique-list-${index}`">
                                    <option v-for="s in props.rubriqueSuggestions" :key="s" :value="s" />
                                </datalist>
                                <InputError :message="form.errors[`lines.${index}.rubrique` as keyof typeof form.errors]" />
                            </div>
                            <div>
                                <Label :for="`sous-rubrique-${index}`" class="text-base font-medium text-gray-700">Sous rubrique</Label>
                                <Input :id="`sous-rubrique-${index}`" v-model="line.sous_rubrique" type="text" class="mt-1.5 border-gray-300" />
                                <InputError :message="form.errors[`lines.${index}.sous_rubrique` as keyof typeof form.errors]" />
                            </div>
                            <div>
                                <Label :for="`montant-estime-${index}`" class="text-base font-medium text-gray-700">Montant estimé</Label>
                                <Input
                                    :id="`montant-estime-${index}`"
                                    v-model="line.montant_estime"
                                    type="number"
                                    step="0.01"
                                    class="mt-1.5 border-gray-300"
                                />
                                <InputError :message="form.errors[`lines.${index}.montant_estime` as keyof typeof form.errors]" />
                            </div>
                            <div>
                                <Label :for="`date-souhaitee-${index}`" class="text-base font-medium text-gray-700">
                                    Date souhaitée d'exécution
                                </Label>
                                <textarea
                                    :id="`date-souhaitee-${index}`"
                                    v-model="line.date_souhaitee_execution"
                                    rows="2"
                                    class="mt-1.5 w-full rounded-md border border-gray-300 px-3 py-2 text-base focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                                    placeholder="Ex: Q1 2026, Dès que possible..."
                                />
                                <InputError :message="form.errors[`lines.${index}.date_souhaitee_execution` as keyof typeof form.errors]" />
                            </div>
                            <div class="md:col-span-3">
                                <Label :for="`justification-${index}`" class="text-base font-medium text-gray-700">Justifications</Label>
                                <textarea
                                    :id="`justification-${index}`"
                                    v-model="line.justification"
                                    rows="2"
                                    class="mt-1.5 w-full rounded-md border border-gray-300 px-3 py-2 text-base focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                                />
                                <InputError :message="form.errors[`lines.${index}.justification` as keyof typeof form.errors]" />
                            </div>
                            <div class="md:col-span-3">
                                <Label :for="`compte-gl-${index}`" class="text-base font-medium text-gray-700">Compte GL</Label>
                                <Input
                                    :id="`compte-gl-${index}`"
                                    v-model="line.compte_gl"
                                    type="text"
                                    class="mt-1.5 border-gray-300"
                                    placeholder="Ex: 401000"
                                />
                                <InputError :message="form.errors[`lines.${index}.compte_gl` as keyof typeof form.errors]" />
                            </div>
                        </div>
                        <div v-if="form.lines.length > 1" class="mt-3 flex justify-end">
                            <Button type="button" variant="ghost" class="text-red-600 hover:text-red-700" @click="removeLine(index)">
                                <Trash2 class="mr-2 h-4 w-4" /> Supprimer
                            </Button>
                        </div>
                    </div>
                </FormSection>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="router.visit('/budgets')">Annuler</Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Création...' : 'Créer' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
