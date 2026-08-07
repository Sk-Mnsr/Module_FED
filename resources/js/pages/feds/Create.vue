<script setup lang="ts">
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { computed, ref, watch } from 'vue';
import {
    Building2,
    ClipboardList,
    FilePlus2,
    FileText,
    Minus,
    Paperclip,
    Plus,
    Send,
    User,
} from 'lucide-vue-next';

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
    { title: 'Fiches de dépense', href: '/feds' },
    { title: 'Nouvelle demande', href: '#' },
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

const onFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    form.attachments = input.files ? Array.from(input.files) : [];
};

const itemsCount = computed(() => form.items.length);
const filledBeneficiaires = computed(() => form.beneficiaire.filter((b) => b.trim()).length);
const priorityLabel = computed(
    () => priorityOptions.find((o) => o.value === form.priority)?.label ?? '—',
);

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

    const subLines = props.budgetLines.filter((line) => line.global_line_id === item.budget_line_id);
    item.entities = subLines.map((line) => ({
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
    { deep: true },
);

const availableBudgetLines = computed(() => {
    return props.budgetLines.filter((line) => {
        if (!line.department_name || !line.year || !line.is_global) {
            return false;
        }
        return line.department_name === form.department && line.year === selectedYear.value;
    });
});

const fieldClass =
    'mt-1.5 h-10 border-slate-300 bg-white text-slate-900 shadow-sm placeholder:text-slate-400 focus-visible:border-primary focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground dark:placeholder:text-slate-500';

const selectClass =
    'mt-1.5 flex h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';

const textareaClass =
    'mt-1.5 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground dark:placeholder:text-slate-500';

const fieldReadonlyClass =
    'mt-1.5 h-10 border-slate-300 bg-slate-50 text-slate-700 shadow-sm dark:border-slate-600 dark:bg-muted/40 dark:text-foreground';

const submit = () => {
    if (!form.department || !form.motive) {
        alert('Veuillez renseigner tous les champs obligatoires (Département, Motif).');
        return;
    }

    if (form.items.length === 0) {
        alert('Veuillez ajouter au moins un article.');
        return;
    }

    const invalidItems = form.items.some((item) => {
        if (!item.label || !item.budget_line_id || !item.quantity) return true;
        if (item.entities.length > 0 && !item.entities.some((e) => e.quantity > 0)) return true;
        return false;
    });
    if (invalidItems) {
        alert(
            "Veuillez renseigner l'intitulé, la ligne budgétaire et les quantités (par entité si applicable) pour tous les articles.",
        );
        return;
    }

    form
        .transform((data) => ({
            ...data,
            items: hasItems.value ? data.items : [],
            attachments: hasAttachments.value ? data.attachments : [],
        }))
        .post('/feds', {
            preserveScroll: true,
            forceFormData: true,
        });
};
</script>

<template>
    <Head title="Nouvelle FED" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-0 flex-1 flex-col gap-4 p-4 lg:p-6">
            <div
                class="flex flex-wrap items-start justify-between gap-3 rounded-2xl border border-border/80 bg-card px-5 py-5 shadow-sm sm:px-6"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm"
                    >
                        <FilePlus2 class="size-5" />
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-primary">
                            Fiches de dépense
                        </p>
                        <h1 class="text-xl font-semibold tracking-tight text-foreground lg:text-2xl">
                            Nouvelle fiche d'engagement
                        </h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Renseignez la demande, les articles, puis soumettez pour validation N+1.
                        </p>
                    </div>
                </div>
            </div>

            <form
                class="grid min-h-0 flex-1 gap-4 xl:grid-cols-[minmax(0,1.55fr)_minmax(300px,0.7fr)]"
                @submit.prevent="submit"
            >
                <!-- Colonne principale -->
                <div class="flex min-h-0 flex-col gap-4 overflow-y-auto">
                    <!-- Informations générales -->
                    <section class="rounded-2xl border border-border/80 bg-card p-4 shadow-sm lg:p-5">
                        <div class="mb-4 flex items-center gap-3 border-b border-border/80 pb-3">
                            <div
                                class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <ClipboardList class="size-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-semibold text-foreground">Informations générales</h2>
                                <p class="text-sm text-muted-foreground">Identité et contexte de la demande</p>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <Label for="date">Date</Label>
                                <Input id="date" v-model="form.date" type="date" :class="fieldClass" />
                                <InputError :message="form.errors.date" />
                            </div>
                            <div>
                                <Label for="demandeur">Demandeur</Label>
                                <Input
                                    id="demandeur"
                                    v-model="form.demandeur"
                                    type="text"
                                    readonly
                                    :class="fieldReadonlyClass"
                                />
                                <InputError :message="form.errors.demandeur" />
                            </div>
                            <div>
                                <Label for="department">
                                    Département
                                    <span class="text-red-600">*</span>
                                </Label>
                                <select id="department" v-model="form.department" :class="selectClass">
                                    <option value="">-- Sélectionner --</option>
                                    <option
                                        v-for="department in props.departments"
                                        :key="department.id"
                                        :value="department.name"
                                    >
                                        {{ department.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.department" />
                            </div>
                            <div>
                                <Label for="fonction">Fonction</Label>
                                <Input
                                    id="fonction"
                                    v-model="form.fonction"
                                    type="text"
                                    readonly
                                    :class="fieldReadonlyClass"
                                />
                                <InputError :message="form.errors.fonction" />
                            </div>
                            <div>
                                <div class="flex items-center justify-between gap-2">
                                    <Label>Bénéficiaire(s)</Label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="h-8 w-8 border-slate-300 p-0"
                                        title="Ajouter un bénéficiaire"
                                        @click="addBeneficiaire"
                                    >
                                        <Plus class="size-4" />
                                    </Button>
                                </div>
                                <div class="mt-1.5 space-y-2">
                                    <div
                                        v-for="(_, index) in form.beneficiaire"
                                        :key="index"
                                        class="flex items-center gap-2"
                                    >
                                        <Input
                                            :id="`beneficiaire-${index}`"
                                            v-model="form.beneficiaire[index]"
                                            type="text"
                                            placeholder="Agence, service…"
                                            :class="fieldClass.replace('mt-1.5 ', '')"
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            class="h-10 w-9 p-0 text-red-600 hover:bg-red-50 hover:text-red-700"
                                            :disabled="form.beneficiaire.length === 1"
                                            title="Supprimer"
                                            @click="removeBeneficiaire(index)"
                                        >
                                            <Minus class="size-4" />
                                        </Button>
                                    </div>
                                </div>
                                <InputError :message="form.errors.beneficiaire" />
                            </div>
                            <div>
                                <Label for="priority">Priorité</Label>
                                <select id="priority" v-model="form.priority" :class="selectClass">
                                    <option
                                        v-for="option in priorityOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.priority" />
                            </div>
                            <div class="md:col-span-2">
                                <Label for="motive">
                                    Motif
                                    <span class="text-red-600">*</span>
                                </Label>
                                <textarea
                                    id="motive"
                                    v-model="form.motive"
                                    rows="3"
                                    :class="textareaClass"
                                    placeholder="Objet de la dépense…"
                                />
                                <InputError :message="form.errors.motive" />
                            </div>
                        </div>
                    </section>

                    <!-- Articles -->
                    <section v-if="hasItems" class="rounded-2xl border border-border/80 bg-card p-4 shadow-sm lg:p-5">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-border pb-3">
                            <div class="flex items-center gap-3">
                                <div class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <FilePlus2 class="size-5" />
                                </div>
                                <div>
                                    <h2 class="text-base font-semibold text-foreground">Articles / Services</h2>
                                    <p class="text-sm text-muted-foreground">
                                        {{ itemsCount }} ligne{{ itemsCount > 1 ? 's' : '' }} de dépense
                                    </p>
                                </div>
                            </div>
                            <Button type="button" variant="outline" size="sm" class="border-primary/25 text-primary hover:bg-primary/5" @click="addItem">
                                <Plus class="mr-1.5 size-4" />
                                Ajouter une ligne
                            </Button>
                        </div>

                        <div class="space-y-4">
                            <div
                                v-for="(item, index) in form.items"
                                :key="index"
                                class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-muted/20"
                            >
                                <div class="mb-3 flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-foreground">Ligne {{ index + 1 }}</p>
                                    <Button
                                        v-if="form.items.length > 1"
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 text-red-600 hover:bg-red-50 hover:text-red-700"
                                        @click="removeItem(index)"
                                    >
                                        <Minus class="mr-1 size-4" />
                                        Retirer
                                    </Button>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <Label :for="`label-${index}`">Intitulé</Label>
                                        <Input
                                            :id="`label-${index}`"
                                            v-model="item.label"
                                            type="text"
                                            :class="fieldClass"
                                            placeholder="Ex. Laptop"
                                        />
                                        <InputError
                                            :message="form.errors[`items.${index}.label` as keyof typeof form.errors]"
                                        />
                                    </div>
                                    <div>
                                        <Label :for="`quantity-${index}`">Quantité</Label>
                                        <!-- @ts-ignore -->
                                        <Input
                                            :id="`quantity-${index}`"
                                            v-model.number="item.quantity"
                                            type="number"
                                            step="1"
                                            :class="[
                                                fieldClass,
                                                item.entities.length > 0 ? 'bg-sky-50 dark:bg-sky-950/20' : '',
                                            ]"
                                            @focus="isEditingTotal = true"
                                            @blur="isEditingTotal = false"
                                        />
                                        <InputError
                                            :message="
                                                form.errors[`items.${index}.quantity` as keyof typeof form.errors]
                                            "
                                        />
                                    </div>

                                    <div class="md:col-span-2">
                                        <Label :for="`budget-line-${index}`">Ligne budgétaire</Label>
                                        <select
                                            :id="`budget-line-${index}`"
                                            v-model="item.budget_line_id"
                                            :class="selectClass"
                                            @change="handleBudgetLineChange(index)"
                                        >
                                            <option value="">-- Sélectionner --</option>
                                            <template v-if="form.department && availableBudgetLines.length > 0">
                                                <option
                                                    v-for="line in availableBudgetLines"
                                                    :key="line.id"
                                                    :value="line.id"
                                                >
                                                    {{ line.code }} ({{ line.label }})
                                                </option>
                                            </template>
                                            <option v-else-if="form.department" disabled>
                                                Aucune ligne disponible
                                            </option>
                                            <option v-else disabled>Sélectionnez un département</option>
                                        </select>
                                        <InputError
                                            :message="
                                                form.errors[
                                                    `items.${index}.budget_line_id` as keyof typeof form.errors
                                                ]
                                            "
                                        />
                                    </div>

                                    <div
                                        v-if="item.entities.length > 0"
                                        class="space-y-3 rounded-lg border border-border bg-background p-3 md:col-span-2"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <Label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                                Quantités par entité ({{ item.entities.length }})
                                            </Label>
                                            <span class="text-xs text-muted-foreground italic">Détail par entité</span>
                                        </div>
                                        <div class="max-h-[280px] overflow-y-auto rounded-md border border-border">
                                            <table class="w-full text-sm">
                                                <thead
                                                    class="sticky top-0 bg-muted text-xs font-medium uppercase text-muted-foreground"
                                                >
                                                    <tr>
                                                        <th class="px-3 py-2 text-left">Entité</th>
                                                        <th class="w-28 px-3 py-2 text-right">Pourc. (%)</th>
                                                        <th class="w-28 px-3 py-2 text-right">Quantité</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-border bg-card">
                                                    <tr
                                                        v-for="(entity, eIndex) in item.entities"
                                                        :key="eIndex"
                                                        class="hover:bg-muted/40"
                                                    >
                                                        <td class="px-3 py-2.5 font-medium text-foreground">
                                                            {{ entity.label }}
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            <Input
                                                                :id="`entity-perc-${index}-${eIndex}`"
                                                                :value="getEntityPercentage(item, entity)"
                                                                type="number"
                                                                readonly
                                                                :class="[fieldClass.replace('mt-1.5 ', ''), 'h-9 bg-slate-50 text-right font-medium']"
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
                                                                :class="[fieldClass.replace('mt-1.5 ', ''), 'h-9 text-right font-medium']"
                                                                placeholder="0"
                                                            />
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div class="md:col-span-2">
                                        <Label :for="`desc-${index}`">Description</Label>
                                        <textarea
                                            :id="`desc-${index}`"
                                            v-model="item.description"
                                            rows="2"
                                            :class="textareaClass"
                                            placeholder="Précisions techniques, références…"
                                        />
                                        <InputError
                                            :message="
                                                form.errors[`items.${index}.description` as keyof typeof form.errors]
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Pièces jointes -->
                    <section class="rounded-2xl border border-border/80 bg-card p-4 shadow-sm lg:p-5">
                        <div class="flex items-start gap-3">
                            <div class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Paperclip class="size-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <h2 class="text-base font-semibold text-foreground">Pièces jointes</h2>
                                        <p class="text-sm text-muted-foreground">Optionnel — devis, specs, etc.</p>
                                    </div>
                                    <label
                                        class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-foreground"
                                    >
                                        <input
                                            id="toggle-attachments"
                                            v-model="hasAttachments"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-input text-red-600 focus:ring-red-600"
                                        />
                                        Joindre des fichiers
                                    </label>
                                </div>
                                <div v-if="hasAttachments" class="mt-4">
                                    <Label for="attachments">Fichiers</Label>
                                    <Input
                                        id="attachments"
                                        type="file"
                                        multiple
                                        :class="[
                                            fieldClass,
                                            'cursor-pointer file:mr-3 file:rounded-md file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary',
                                        ]"
                                        @change="onFileChange"
                                    />
                                    <p
                                        v-if="form.attachments.length"
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        {{ form.attachments.length }} fichier(s) sélectionné(s)
                                    </p>
                                    <InputError :message="form.errors.attachments" />
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Panneau latéral -->
                <aside class="xl:sticky xl:top-4 xl:self-start">
                    <div class="flex flex-col gap-4 rounded-2xl border border-border/80 bg-card p-4 shadow-sm lg:p-5">
                        <div class="flex items-start gap-3 border-b border-border/80 pb-4">
                            <div class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <FileText class="size-5" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Récapitulatif
                                </p>
                                <h2 class="text-base font-semibold text-foreground">Avant soumission</h2>
                            </div>
                        </div>

                        <dl class="space-y-3 text-sm">
                            <div class="flex items-start gap-2">
                                <User class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                <div class="min-w-0">
                                    <dt class="text-muted-foreground">Demandeur</dt>
                                    <dd class="font-medium text-foreground">
                                        {{ form.demandeur || '—' }}
                                        <span v-if="form.fonction" class="font-normal text-muted-foreground">
                                            · {{ form.fonction }}
                                        </span>
                                    </dd>
                                </div>
                            </div>
                            <div class="flex items-start gap-2">
                                <Building2 class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                <div class="min-w-0">
                                    <dt class="text-muted-foreground">Département</dt>
                                    <dd class="font-medium text-foreground">{{ form.department || '—' }}</dd>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3 border-t border-border pt-3">
                                <div>
                                    <dt class="text-muted-foreground">Priorité</dt>
                                    <dd class="font-medium text-foreground">{{ priorityLabel }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">Articles</dt>
                                    <dd class="font-medium text-foreground">{{ itemsCount }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">Bénéficiaires</dt>
                                    <dd class="font-medium text-foreground">{{ filledBeneficiaires || '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">Pièces jointes</dt>
                                    <dd class="font-medium text-foreground">
                                        {{ hasAttachments ? form.attachments.length || 0 : 0 }}
                                    </dd>
                                </div>
                            </div>
                            <div v-if="form.motive" class="border-t border-border pt-3">
                                <dt class="text-muted-foreground">Motif</dt>
                                <dd class="mt-1 line-clamp-4 whitespace-pre-line text-foreground">
                                    {{ form.motive }}
                                </dd>
                            </div>
                        </dl>

                        <div class="flex flex-col gap-2 border-t border-border pt-4">
                            <Button
                                type="submit"
                                class="w-full bg-primary text-primary-foreground hover:bg-primary/90"
                                :disabled="form.processing"
                            >
                                <Send class="mr-2 size-4" />
                                {{ form.processing ? 'Création…' : 'Soumettre N+1' }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                class="w-full border-slate-300"
                                @click="router.visit('/feds')"
                            >
                                Annuler
                            </Button>
                        </div>
                    </div>
                </aside>
            </form>
        </div>
    </AppLayout>
</template>
