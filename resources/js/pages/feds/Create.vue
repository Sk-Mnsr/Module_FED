<script setup lang="ts">
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import FormSection from '@/components/FormSection.vue';
import { computed, ref, watch } from 'vue';
import { Plus, Trash2, Minus } from 'lucide-vue-next';

interface FedItemEntityForm {
    budget_line_id: number;
    quantity: number;
    label: string;
}

interface FedItemForm {
    label: string;
    budget_line_id: number | '';
    quantity: number | '';
    description: string;
    entities: FedItemEntityForm[];
}

interface Department {
    id: number;
    name: string;
}

interface BudgetLine {
    id: number;
    code: string;
    label: string;
    montant_estime?: number | null;
    year?: number | null;
    department_name?: string | null;
    is_global: boolean;
    global_line_id?: number | null;
    agence_name?: string | null;
}


interface Props {
    departments: Department[];
    budgetLines: BudgetLine[];
    userDepartment?: string | null;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Fiches de dépense',
        href: '/feds',
    },
    {
        title: 'Nouvelle demande',
        href: '#',
    },
];

const makeItem = (): FedItemForm => ({
    label: '',
    budget_line_id: '',
    quantity: 1,
    description: '',
    entities: [],
});

const form = useForm({
    date: new Date().toISOString().split('T')[0],
    demandeur: '',
    department: props.userDepartment || '',
    fonction: '',
    beneficiaire: [''],
    motive: '',
    priority: 'normal',
    items: [makeItem()],
    attachments: [] as File[],
});

const page = usePage();
const authUser = (page.props.auth as any)?.user;

if (authUser) {
    if (!form.demandeur) {
        form.demandeur = authUser.name || '';
    }
    if (!form.fonction) {
        form.fonction = authUser.fonction || '';
    }
}

const priorityOptions = [
    { value: 'low', label: 'Faible' },
    { value: 'normal', label: 'Normal' },
    { value: 'high', label: 'Haute' },
    { value: 'urgent', label: 'Urgente' },
];

const hasItems = ref(true);
const hasAttachments = ref(false);
const isEditingTotal = ref(false);

const getEntityPercentage = (item: FedItemForm, entity: FedItemEntityForm) => {
    const total = Number(item.quantity) || 0;
    if (total === 0) return 0;
    return Number(((entity.quantity / total) * 100).toFixed(2));
};

const handlePercentageChange = (item: FedItemForm, entity: FedItemEntityForm, percentage: number) => {
    const total = Number(item.quantity) || 0;
    if (total > 0) {
        entity.quantity = Math.round((percentage / 100) * total);
    }
};

const addItem = () => {
    form.items.push(makeItem());
};

const removeItem = (index: number) => {
    form.items.splice(index, 1);
};

const addBeneficiaire = () => {
    form.beneficiaire.push('');
};

const removeBeneficiaire = (index: number) => {
    if (form.beneficiaire.length > 1) {
        form.beneficiaire.splice(index, 1);
    }
};

// Méthodes de calculs de prix retirées

const onFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    form.attachments = input.files ? Array.from(input.files) : [];
};

const itemsCount = computed(() => form.items.length);

const selectedYear = computed(() => {
    if (form.date) {
        const parsed = new Date(form.date);
        if (!Number.isNaN(parsed.getTime())) {
            return parsed.getFullYear();
        }
    }
    return new Date().getFullYear();
});

const handleBudgetLineChange = (index: number) => {
    const item = form.items[index];
    if (!item.budget_line_id) {
        item.entities = [];
        return;
    }

    const subLines = props.budgetLines.filter(line => line.global_line_id === item.budget_line_id);
    item.entities = subLines.map(line => ({
        budget_line_id: line.id,
        quantity: 0,
        label: line.agence_name || line.label,
    }));
    item.quantity = 0;
};

watch(
    () => form.items,
    (newItems) => {
        if (isEditingTotal.value) return;
        
        newItems.forEach((item) => {
            if (item.entities.length > 0) {
                const total = item.entities.reduce((sum, entity) => sum + (Number(entity.quantity) || 0), 0);
                if (item.quantity !== total) {
                    item.quantity = total;
                }
            }
        });
    },
    { deep: true }
);

const availableBudgetLines = computed(() => {
    return props.budgetLines.filter(line => {
        if (!line.department_name || !line.year || !line.is_global) {
            return false;
        }
        return line.department_name === form.department && line.year === selectedYear.value;
    });
});

const submit = () => {
    // Client-side validation for mandatory fields
    if (!form.department || !form.motive) {
        alert('Veuillez renseigner tous les champs obligatoires (Département, Motif).');
        return;
    }

    if (form.items.length === 0) {
        alert('Veuillez ajouter au moins un article.');
        return;
    }
    
    const invalidItems = form.items.some(item => {
        if (!item.label || !item.budget_line_id || !item.quantity) return true;
        // Si c'est une ligne globale avec des entités, il faut qu'au moins une entité ait une quantité > 0
        if (item.entities.length > 0 && !item.entities.some(e => e.quantity > 0)) return true;
        return false;
    });
    if (invalidItems) {
        alert('Veuillez renseigner l\'intitulé, la ligne budgétaire et les quantités (par entité si applicable) pour tous les articles.');
        return;
    }

    form.transform((data) => ({
        ...data,
        items: hasItems.value ? data.items : [],
        attachments: hasAttachments.value ? data.attachments : []
    })).post('/feds', {
        preserveScroll: true,
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Nouvelle FED" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <h1 class="text-3xl font-bold text-gray-900">Nouvelle fiche d'engagement</h1>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <FormSection title="Informations générales" :columns="2">
                    <div>
                        <Label for="date" class="text-base font-medium text-gray-700">Date</Label>
                        <Input id="date" v-model="form.date" type="date" class="mt-1.5 border-gray-300" />
                        <InputError :message="form.errors.date" />
                    </div>
                    <div>
                        <Label for="demandeur" class="text-base font-medium text-gray-700">Demandeur</Label>
                        <Input
                            id="demandeur"
                            v-model="form.demandeur"
                            type="text"
                            readonly
                            class="mt-1.5 border-gray-300 bg-gray-50 text-gray-700"
                        />
                        <InputError :message="form.errors.demandeur" />
                    </div>
                    <div>
                        <Label for="department" class="text-base font-medium text-gray-700">Département</Label>
                        <select
                            id="department"
                            v-model="form.department"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900"
                        >
                            <option value="">-- Sélectionner --</option>
                            <option v-for="department in props.departments" :key="department.id" :value="department.name">
                                {{ department.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.department" />
                    </div>
                                        <div>
                        <Label for="fonction" class="text-base font-medium text-gray-700">Fonction</Label>
                        <Input
                            id="fonction"
                            v-model="form.fonction"
                            type="text"
                            readonly
                            class="mt-1.5 border-gray-300 bg-gray-50 text-gray-700"
                        />
                        <InputError :message="form.errors.fonction" />
                    </div>


                    <div>
                        <div class="flex items-center justify-between">
                            <Label class="text-base font-medium text-gray-700">Bénéficiaire(s)</Label>
                            <Button type="button" variant="outline" size="sm" @click="addBeneficiaire" class="p-2 h-8 w-8">
                                <Plus class="h-4 w-4" />
                            </Button>
                        </div>
                        <div class="mt-2 space-y-2">
                            <div v-for="(item, index) in form.beneficiaire" :key="index" class="flex items-center gap-2">
                                <Input
                                    :id="`beneficiaire-${index}`"
                                    v-model="form.beneficiaire[index]"
                                    type="text"
                                    class="border-gray-300"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    class="text-red-600 hover:text-red-700 hover:bg-red-50 p-2 h-9 w-9"
                                    @click="removeBeneficiaire(index)"
                                    :disabled="form.beneficiaire.length === 1"
                                    title="Supprimer"
                                >
                                    <Minus class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                        <InputError :message="form.errors.beneficiaire" />
                    </div>
                    <div>
                        <Label for="priority" class="text-base font-medium text-gray-700">Priorité</Label>
                        <select
                            id="priority"
                            v-model="form.priority"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900"
                        >
                            <option v-for="option in priorityOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.priority" />
                    </div>
                    <div class="md:col-span-2">
                        <Label for="motive" class="text-base font-medium text-gray-700">Motif</Label>
                        <textarea
                            id="motive"
                            v-model="form.motive"
                            rows="3"
                            class="mt-1.5 w-full rounded-md border border-gray-300 px-3 py-2 text-base focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        />
                        <InputError :message="form.errors.motive" />
                    </div>
                  
                </FormSection>

                <!-- Section Articles / Services (Obligatoire) -->

                <FormSection v-if="hasItems" title="Articles / Services" :columns="1">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-600">Ajoutez les lignes de dépense ({{ itemsCount }})</p>
                        <Button type="button" variant="outline" size="sm" @click="addItem" class="p-2 h-8 w-8">
                            <Plus class="h-4 w-4" />
                        </Button>
                    </div>

                    <div v-for="(item, index) in form.items" :key="index" class="rounded-md border border-gray-200 p-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <Label :for="`label-${index}`" class="text-base font-medium text-gray-700">Intitulé</Label>
                                <Input :id="`label-${index}`" v-model="item.label" type="text" class="mt-1.5 border-gray-300" />
                                <InputError :message="form.errors[`items.${index}.label` as keyof typeof form.errors]" />
                            </div>
                            <div>
                                <Label :for="`quantity-${index}`" class="text-base font-medium text-gray-700">Quantité</Label>
                                <!-- @ts-ignore -->
                                <Input
                                    :id="`quantity-${index}`"
                                    v-model.number="item.quantity"
                                    type="number"
                                    step="1"
                                    @focus="isEditingTotal = true"
                                    @blur="isEditingTotal = false"
                                    :class="['mt-1.5 border-gray-300', item.entities.length > 0 ? 'bg-blue-50/30' : '']"
                                />
                                <InputError :message="form.errors[`items.${index}.quantity` as keyof typeof form.errors]" />
                            </div>



                            <div class="md:col-span-2">
                                <Label :for="`budget-line-${index}`" class="text-base font-medium text-gray-700">Ligne budgétaire</Label>
                                <select
                                    :id="`budget-line-${index}`"
                                    v-model="item.budget_line_id"
                                    @change="handleBudgetLineChange(index)"
                                    class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900"
                                >
                                    <option value="">-- Sélectionner --</option>
                                    <template v-if="form.department && availableBudgetLines.length > 0">
                                        <option v-for="line in availableBudgetLines" :key="line.id" :value="line.id">
                                            {{ line.code }} ({{ line.label }})
                                        </option>
                                    </template>
                                    <option v-else-if="form.department" disabled>Aucune ligne disponible</option>
                                    <option v-else disabled>Sélectionnez un département</option>
                                </select>
                                <InputError :message="form.errors[`items.${index}.budget_line_id` as keyof typeof form.errors]" />
                            </div>
                                                        <div v-if="item.entities.length > 0" class="md:col-span-2 space-y-3 rounded-md border border-gray-100 bg-gray-50/50 p-4">
                                <div class="flex items-center justify-between">
                                    <Label class="text-sm font-semibold text-gray-600 uppercase tracking-wider">Quantités par entité ({{ item.entities.length }})</Label>
                                    <span class="text-xs text-gray-400 italic">Détail par entité</span>
                                </div>
                                <div class="max-h-[300px] overflow-y-auto rounded border border-gray-200 bg-white">
                                    <table class="w-full text-sm">
                                        <thead class="sticky top-0 bg-gray-100 text-xs font-medium text-gray-500 uppercase">
                                            <tr>
                                                <th class="px-3 py-2 text-left">Entité</th>
                                                <th class="px-3 py-2 text-right w-32">Pourc. (%)</th>
                                                <th class="px-3 py-2 text-right w-32">Quantité</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr v-for="(entity, eIndex) in item.entities" :key="eIndex" class="hover:bg-gray-50/50">
                                                <td class="px-3 py-3 font-medium text-gray-700">
                                                    {{ entity.label }}
                                                </td>
                                                <td class="px-3 py-2">
                                                    <Input
                                                        :id="`entity-perc-${index}-${eIndex}`"
                                                        :value="getEntityPercentage(item, entity)"
                                                        type="number"
                                                        readonly
                                                        class="h-9 border-gray-300 text-right font-medium bg-gray-50"
                                                        placeholder="0"
                                                    />
                                                </td>
                                                <td class="px-3 py-2">
                                                    <Input
                                                        :id="`entity-${index}-${eIndex}`"
                                                        v-model.number="entity.quantity"
                                                        type="number"
                                                        step="1"
                                                        min="0"
                                                        class="h-9 border-gray-300 text-right font-medium"
                                                        placeholder="0"
                                                    />
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <Label :for="`desc-${index}`" class="text-base font-medium text-gray-700">Description</Label>
                                <textarea
                                    :id="`desc-${index}`"
                                    v-model="item.description"
                                    rows="2"
                                    class="mt-1.5 w-full rounded-md border border-gray-300 px-3 py-2 text-base focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                                />
                                <InputError :message="form.errors[`items.${index}.description` as keyof typeof form.errors]" />
                            </div>
                        </div>
                        <div v-if="form.items.length > 1" class="mt-3 flex justify-end">
                            <Button type="button" variant="ghost" class="text-red-600 hover:text-red-700 hover:bg-red-50 p-2 h-9 w-9" @click="removeItem(index)" title="Supprimer">
                                <Minus class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </FormSection>

                <!-- Section Optionnelle : Pièces jointes -->
                <div class="flex items-center space-x-2 rounded-md border border-gray-200 p-4 bg-gray-50">
                    <input type="checkbox" id="toggle-attachments" v-model="hasAttachments" class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-600" />
                    <Label for="toggle-attachments" class="text-base font-medium text-gray-700 cursor-pointer">Ajouter des pièces jointes (optionnel)</Label>
                </div>

                <FormSection v-if="hasAttachments" title="Pièces jointes" :columns="1">
                    <div>
                        <Label for="attachments" class="text-base font-medium text-gray-700">Ajouter des fichiers</Label>
                        <Input id="attachments" type="file" multiple class="mt-1.5 border-gray-300" @change="onFileChange" />
                        <InputError :message="form.errors.attachments" />
                    </div>
                </FormSection>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="router.visit('/feds')">Annuler</Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Création...' : 'Soumettre N+1' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

