<script setup lang="ts">
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import FormSection from '@/components/FormSection.vue';
import { computed, ref, onMounted, watch } from 'vue';
import { Plus, Trash2, Send, Minus } from 'lucide-vue-next';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import SignatureInput from '@/components/SignatureInput.vue';

interface FedItemEntityForm {
    budget_line_id: number;
    quantity: number;
    label?: string;
}

interface FedItemForm {
    label: string;
    budget_line_id?: number | '';
    quantity: number | '';
    description: string;
    entities: FedItemEntityForm[];
}

interface FedAttachment {
    id: number;
    original_name: string;
}

interface Fed {
    id: number;
    code: string;
    date?: string | null;
    demandeur?: string | null;
    department?: string | null;
    budget_line_ids?: number[];
    budget_lines?: Array<{ id: number; code: string; label: string }>;
    fonction?: string | null;
    beneficiaire?: string | null;
    motive?: string | null;
    estimated_total?: number | null;
    priority?: string | null;
    status: string;
    submitted_at?: string | null;
    n1_comment?: string | null;
    n1_action_at?: string | null;
    items: FedItemForm[];
    attachments: FedAttachment[];
}


interface Props {
    fed: Fed;
    canEdit?: boolean;
    authSignature?: string | null;
    departments: Array<{ id: number; name: string }>;
    budgetLines: Array<{
        id: number;
        code: string;
        label: string;
        montant_estime?: number | null;
        year?: number | null;
        department_name?: string | null;
        is_global: boolean;
        global_line_id?: number | null;
        agence_name?: string | null;
    }>;
}

const props = defineProps<Props>();

const canEdit = computed(() => props.canEdit !== false);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Fiches de dépense',
        href: '/feds',
    },
    {
        title: props.fed.code,
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

const splitBeneficiaires = (value?: string | null) => {
    if (!value) {
        return [''];
    }
    const parts = value.split(';').map(part => part.trim()).filter(Boolean);
    return parts.length > 0 ? parts : [''];
};

const prepareItems = () => {
    if (!props.fed.items || props.fed.items.length === 0) {
        return [makeItem()];
    }

    return props.fed.items.map(item => {
        // S'assurer que chaque entité a son libellé
        const entities = (item.entities || []).map(e => {
            const line = props.budgetLines.find(bl => bl.id === e.budget_line_id);
            return {
                ...e,
                label: line?.agence_name || line?.label || 'Inconnue'
            };
        });

        return {
            ...item,
            entities
        };
    });
};

const form = useForm({
    date: props.fed.date ? String(props.fed.date).substring(0, 10) : '',
    demandeur: props.fed.demandeur || '',
    department: props.fed.department || '',
    fonction: props.fed.fonction || '',
    beneficiaire: splitBeneficiaires(props.fed.beneficiaire),
    motive: props.fed.motive || '',
    priority: props.fed.priority || 'normal',
    items: prepareItems(),
    attachments: [] as File[],
    removed_attachment_ids: [] as number[],
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

const hasItems = ref(props.fed.items && props.fed.items.length > 0);
const hasAttachments = ref(props.fed.attachments && props.fed.attachments.length > 0);
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

const markAttachmentForRemoval = (id: number) => {
    if (!form.removed_attachment_ids.includes(id)) {
        form.removed_attachment_ids.push(id);
    }
};

const isRemoved = (id: number) => {
    return form.removed_attachment_ids.includes(id);
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
    (newItems: FedItemForm[]) => {
        if (isEditingTotal.value) return;

        newItems.forEach((item: FedItemForm) => {
            if (item.entities && item.entities.length > 0) {
                const total = item.entities.reduce((sum: number, entity: FedItemEntityForm) => sum + (Number(entity.quantity) || 0), 0);
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

const submitUpdate = () => {
    form
        .transform(data => ({
            ...data,
            _method: 'put',
            items: hasItems.value ? data.items : [],
            attachments: hasAttachments.value ? data.attachments : []
        }))
        .post(`/feds/${props.fed.id}`, {
            preserveScroll: true,
            forceFormData: true,
        });
};

const showSignatureModal = ref(false);
const signatureInputRef = ref<InstanceType<typeof SignatureInput> | null>(null);

const openSubmitModal = () => {
    showSignatureModal.value = true;
};

onMounted(() => {
    if (typeof window !== 'undefined' && new URL(window.location.href).searchParams.get('submit') === '1') {
        showSignatureModal.value = true;
        window.history.replaceState({}, '', window.location.pathname);
    }
});

const submitRequest = () => {
    const signature = signatureInputRef.value?.getSignature();
    if (!signature) {
        alert('Veuillez signer avant de soumettre.');
        return;
    }
    showSignatureModal.value = false;
    form
        .transform(data => ({
            ...data,
            requester_signature: signature,
            items: hasItems.value ? data.items : [],
            attachments: hasAttachments.value ? data.attachments : []
        }))
        .post(`/feds/${props.fed.id}/submit`, {
            preserveScroll: true,
            forceFormData: true,
        });
};
</script>

<template>
    <Head :title="`FED ${props.fed.code}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-gray-900">FED {{ props.fed.code }}</h1>
                <span class="rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">
                    Statut : {{ props.fed.status }}
                </span>
            </div>

            <div v-if="props.fed.n1_comment" class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-700 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-gray-900">Commentaire N+1</span>
                    <span v-if="props.fed.n1_action_at" class="text-xs text-gray-500">
                        {{ new Date(props.fed.n1_action_at).toLocaleString('fr-FR') }}
                    </span>
                </div>
                <p class="mt-2 whitespace-pre-line">{{ props.fed.n1_comment }}</p>
            </div>

            <div
                v-if="!canEdit"
                class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"
            >
                Cette demande a été validée et n'est plus modifiable.
            </div>
            <div
                v-else-if="props.fed.submitted_at"
                class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800"
            >
                En attente de validation. Vous pouvez encore modifier la demande avant qu'elle ne soit validée par le N+1.
            </div>

            <form @submit.prevent="canEdit && submitUpdate()" class="flex flex-col gap-6">
                <fieldset :disabled="!canEdit" class="contents">
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
                            <Button v-if="canEdit" type="button" variant="outline" size="sm" @click="addBeneficiaire" class="p-2 h-8 w-8">
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
                                    v-if="canEdit"
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

                <!-- Section Optionnelle : Articles / Services -->
                <div class="flex items-center space-x-2 rounded-md border border-gray-200 p-4 bg-gray-50">
                    <input :disabled="!canEdit" type="checkbox" id="toggle-items" v-model="hasItems" class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-600" />
                    <Label for="toggle-items" class="text-base font-medium text-gray-700 cursor-pointer">Ajouter des articles ou services (optionnel)</Label>
                </div>

                <FormSection v-if="hasItems" title="Articles / Services" :columns="1">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-600">Lignes de dépense ({{ itemsCount }})</p>
                        <Button v-if="canEdit" type="button" variant="outline" size="sm" @click="addItem" class="p-2 h-8 w-8">
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
                                    :class="['mt-1.5 border-gray-300', (item.entities && item.entities.length > 0) ? 'bg-blue-50/30' : '']"
                                />
                                <InputError :message="form.errors[`items.${index}.quantity` as keyof typeof form.errors]" />
                            </div>

                            <div v-if="item.entities && item.entities.length > 0" class="md:col-span-2 space-y-3 rounded-md border border-gray-100 bg-gray-50/50 p-4">
                                <div class="flex items-center justify-between">
                                    <Label class="text-sm font-semibold text-gray-600 uppercase tracking-wider">Quantités par entité ({{ item.entities.length }})</Label>
                                    <span class="text-xs text-gray-400 italic">Détail par agence/entité</span>
                                </div>
                                <div class="max-h-[300px] overflow-y-auto rounded border border-gray-200 bg-white">
                                    <table class="w-full text-sm">
                                        <thead class="sticky top-0 bg-gray-100 text-xs font-medium text-gray-500 uppercase">
                                            <tr>
                                                <th class="px-3 py-2 text-left">Entité / Agence</th>
                                                <th class="px-3 py-2 text-right w-32">Perc. (%)</th>
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
                        <div v-if="canEdit && form.items.length > 1" class="mt-3 flex justify-end">
                            <Button type="button" variant="ghost" class="text-red-600 hover:text-red-700 hover:bg-red-50 p-2 h-9 w-9" @click="removeItem(index)" title="Supprimer">
                                <Minus class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </FormSection>

                <!-- Section Optionnelle : Pièces jointes -->
                <div class="flex items-center space-x-2 rounded-md border border-gray-200 p-4 bg-gray-50">
                    <input :disabled="!canEdit" type="checkbox" id="toggle-attachments" v-model="hasAttachments" class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-600" />
                    <Label for="toggle-attachments" class="text-base font-medium text-gray-700 cursor-pointer">Ajouter des pièces jointes (optionnel)</Label>
                </div>

                <FormSection v-if="hasAttachments" title="Pièces jointes" :columns="1">
                    <div v-if="canEdit">
                        <Label for="attachments" class="text-base font-medium text-gray-700">Ajouter des fichiers</Label>
                        <Input id="attachments" type="file" multiple class="mt-1.5 border-gray-300" @change="onFileChange" />
                        <InputError :message="form.errors.attachments" />
                    </div>

                    <div v-if="props.fed.attachments.length" class="space-y-2">
                        <p class="text-sm font-medium text-gray-700">Pièces jointes existantes</p>
                        <div
                            v-for="attachment in props.fed.attachments"
                            :key="attachment.id"
                            class="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2"
                        >
                            <span :class="isRemoved(attachment.id) ? 'line-through text-gray-400' : 'text-gray-700'">
                                {{ attachment.original_name }}
                            </span>
                            <Button
                                v-if="canEdit"
                                type="button"
                                variant="ghost"
                                class="text-red-600 hover:text-red-700"
                                @click="markAttachmentForRemoval(attachment.id)"
                                :disabled="isRemoved(attachment.id)"
                            >
                                Retirer
                            </Button>
                        </div>
                    </div>
                </FormSection>

                <div class="flex flex-wrap justify-end gap-2">
                    <Link :href="`/feds/${props.fed.id}`">
                        <Button type="button" variant="outline">Voir le document</Button>
                    </Link>
                    <Button type="button" variant="outline" @click="router.visit('/feds')">Annuler</Button>
                    <template v-if="canEdit">
                        <Button
                            v-if="!props.fed.submitted_at"
                            type="button"
                            class="bg-blue-600 hover:bg-blue-700"
                            @click="openSubmitModal"
                            :disabled="form.processing"
                        >
                            <Send class="mr-2 h-4 w-4" /> Soumettre N+1
                        </Button>
                        <Button
                            v-else
                            type="button"
                            variant="outline"
                            @click="submitUpdate"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Enregistrement...' : 'Enregistrer' }}
                        </Button>
                    </template>
                </div>
                </fieldset>
            </form>

            <Dialog v-model:open="showSignatureModal">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Signature avant soumission N+1</DialogTitle>
                        <DialogDescription>
                            Signez pour confirmer la soumission de votre demande. Utilisez votre signature enregistrée ou dessinez/téléversez une nouvelle.
                        </DialogDescription>
                    </DialogHeader>
                    <SignatureInput ref="signatureInputRef" :saved-signature="props.authSignature" />
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showSignatureModal = false">
                            Annuler
                        </Button>
                        <Button
                            type="button"
                            class="bg-blue-600 hover:bg-blue-700"
                            @click="submitRequest"
                            :disabled="form.processing"
                        >
                            Confirmer et soumettre N+1
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

