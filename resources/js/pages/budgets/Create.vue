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

interface Department { id: number; name: string; code?: string; }
interface SousCategorie { id: number; sous_categorie: string; code: string; }
interface Categorie { id: number; categorie: string; code: string; sous_categories: SousCategorie[]; }
interface Article {
    id: number;
    code: string;
    description: string;
    responsable?: string;
    type_depense?: { id: number; nom_depense: string; compte_gl?: string };
    sous_categorie?: {
        id: number;
        sous_categorie: string;
        categorie_id: number;
        categorie?: { id: number; categorie: string; code: string };
    };
}

interface BudgetLineForm {
    label: string;
    type: string;
    article_id: number | null;
    categorie_depense_id: number | null;
    // Fields for display only
    article_categorie_name?: string;
    article_sous_categorie_name?: string;
    montant_estime: number | undefined;
    date_souhaitee_execution: string | null;
    justification: string;
    compte_gl: string;
    responsable: string;
}

interface Props {
    departments: Department[];
    typologies: Array<{ type: string; libelle: string }>;
    categories: Categorie[];
    articles: Article[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Budgets', href: '/budgets' },
    { title: 'Créer', href: '#' },
];

const makeLine = (): BudgetLineForm => ({
    label: '',
    type: '',
    article_id: null,
    categorie_depense_id: null,
    article_categorie_name: '',
    article_sous_categorie_name: '',
    montant_estime: undefined,
    date_souhaitee_execution: null,
    justification: '',
    compte_gl: '',
    responsable: '',
});

const form = useForm({
    department_id: null as number | null,
    year: new Date().getFullYear(),
    lines: [makeLine()],
});

const getSousCategories = (categorieId: number | null) => {
    if (!categorieId) return [];
    return props.categories.find(c => c.id === categorieId)?.sous_categories ?? [];
};

const onCategorieChange = (line: BudgetLineForm) => { /* No longer needed */ };

const onArticleChange = (line: BudgetLineForm) => {
    const article = props.articles.find(a => a.id === line.article_id);
    if (article) {
        if (article.responsable) line.responsable = article.responsable;
        
        // Article categorization (pre-defined)
        line.article_categorie_name = article.sous_categorie?.categorie?.categorie || '--';
        line.article_sous_categorie_name = article.sous_categorie?.sous_categorie || '--';

        // Mapping Typologie based on type_depense
        if (article.type_depense) {
            const typology = props.typologies.find(t =>
                t.type.toLowerCase() === article.type_depense?.nom_depense.toLowerCase() ||
                t.libelle.toLowerCase() === article.type_depense?.nom_depense.toLowerCase()
            );
            if (typology) line.type = typology.type;
        }
        if (article.type_depense?.compte_gl) {
            line.compte_gl = article.type_depense.compte_gl;
        }
    } else {
        line.article_categorie_name = '';
        line.article_sous_categorie_name = '';
    }
};

const addLine = () => { form.lines.push(makeLine()); };

const removeLine = (index: number) => {
    if (form.lines.length > 1) form.lines.splice(index, 1);
};

const submit = () => { form.post('/budgets', { preserveScroll: true }); };
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
                        <select id="department_id" v-model="form.department_id"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900">
                            <option :value="null">-- Sélectionner --</option>
                            <option v-for="dept in props.departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                        </select>
                        <InputError :message="form.errors.department_id" />
                    </div>
                    <div>
                        <Label for="year" class="text-base font-medium text-gray-700">Année</Label>
                        <Input id="year" v-model="form.year" type="number" min="2000" max="2100" class="mt-1.5 border-gray-300" />
                        <InputError :message="form.errors.year" />
                    </div>
                </FormSection>

                <FormSection title="Lignes budgétaires globales" :columns="1">
                   
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-600">Définir les lignes budgétaires</p>
                        <Button type="button" variant="outline" @click="addLine">
                            <Plus class="mr-2 h-4 w-4" /> Ajouter une ligne
                        </Button>
                    </div>

                    <div v-for="(line, index) in form.lines" :key="index" class="rounded-md border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="mb-2 flex items-center gap-2">
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-purple-600 text-xs font-bold text-white">{{ index + 1 }}</span>
                            <span class="text-sm font-medium text-gray-700">Ligne globale</span>
                        </div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <!-- Libellé -->
                            <div class="md:col-span-2">
                                <Label :for="`label-${index}`" class="text-base font-medium text-gray-700">Libellé de la dépense</Label>
                                <Input :id="`label-${index}`" v-model="line.label" type="text" class="mt-1.5 border-gray-300" />
                                <InputError :message="form.errors[`lines.${index}.label` as keyof typeof form.errors]" />
                            </div>
                            <!-- Responsable -->
                            <div>
                                <Label :for="`responsable-${index}`" class="text-base font-medium text-gray-700">Responsable</Label>
                                <select :id="`responsable-${index}`" v-model="line.responsable"
                                    class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900">
                                    <option value="">-- Sélectionner --</option>
                                    <option value="IT">IT</option>
                                    <option value="Facilities">Facilities</option>
                                    <option value="RH">RH</option>
                                </select>
                            </div>
                            <!-- Type -->
                            <div>
                                <Label :for="`type-${index}`" class="text-base font-medium text-gray-700">Type (OPEX/CAPEX)</Label>
                                <select :id="`type-${index}`" v-model="line.type"
                                    class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900">
                                    <option value="">-- Sélectionner --</option>
                                    <option v-for="typ in props.typologies" :key="typ.type" :value="typ.type">
                                        {{ typ.type }} - {{ typ.libelle }}
                                    </option>
                                </select>
                                <InputError :message="form.errors[`lines.${index}.type` as keyof typeof form.errors]" />
                            </div>
                             <!-- Catégorie -->
                            <div>
                                <Label :for="`categorie-${index}`" class="text-base font-medium text-gray-700">Catégorie dépense</Label>
                                <select :id="`categorie-${index}`" v-model="line.categorie_depense_id"
                                    class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900"
                                    @change="onCategorieChange(line)">
                                    <option :value="null">-- Sélectionner --</option>
                                    <option v-for="cat in props.categories" :key="cat.id" :value="cat.id">
                                        {{ cat.categorie }} ({{ cat.code }})
                                    </option>
                                </select>
                                <InputError :message="form.errors[`lines.${index}.categorie_depense_id` as keyof typeof form.errors]" />
                            </div>
                            <!-- Article -->
                            <div>
                                <Label :for="`article-${index}`" class="text-base font-medium text-gray-700">Article (code ligne)</Label>
                                <select :id="`article-${index}`" v-model="line.article_id"
                                    class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900"
                                    @change="onArticleChange(line)">
                                    <option :value="null">-- Sélectionner --</option>
                                    <option v-for="art in props.articles" :key="art.id" :value="art.id">
                                        {{ art.code }} — {{ art.description }}
                                    </option>
                                </select>
                            </div>
                           
                              <!-- Compte GL -->
                            <div>
                                <Label :for="`compte-gl-${index}`" class="text-base font-medium text-gray-700">Compte GL</Label>
                                <Input :id="`compte-gl-${index}`" v-model="line.compte_gl" type="text"
                                    class="mt-1.5 border-gray-300" placeholder="Ex: 1000000000001" />
                            </div>
                            <!-- Montant -->
                            <div>
                                <Label :for="`montant-estime-${index}`" class="text-base font-medium text-gray-700">Montant estimé</Label>
                                <Input :id="`montant-estime-${index}`" v-model="line.montant_estime" type="number" step="0.01" class="mt-1.5 border-gray-300" />
                                <InputError :message="form.errors[`lines.${index}.montant_estime` as keyof typeof form.errors]" />
                            </div>
                            <!-- Date -->
                            <div>
                                <Label :for="`date-souhaitee-${index}`" class="text-base font-medium text-gray-700">Date souhaitée d'exécution</Label>
                                <textarea :id="`date-souhaitee-${index}`" v-model="line.date_souhaitee_execution" rows="2"
                                    class="mt-1.5 w-full rounded-md border border-gray-300 px-3 py-2 text-base"
                                    placeholder="Ex: Q1 2026, Dès que possible..." />
                            </div>
                            <!-- Justification -->
                            <div class="md:col-span-3">
                                <Label :for="`justification-${index}`" class="text-base font-medium text-gray-700">Justifications</Label>
                                <textarea :id="`justification-${index}`" v-model="line.justification" rows="2"
                                    class="mt-1.5 w-full rounded-md border border-gray-300 px-3 py-2 text-base" />
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
