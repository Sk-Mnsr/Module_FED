<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import DataTable from '@/components/DataTable.vue';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { GitCompare, ImageIcon, Pencil, Plus, Trash2 } from 'lucide-vue-next';

type PartenaireRow = {
    id: number;
    identifiant: string;
    nom: string;
    icone: string | null;
    icone_url: string | null;
};

const props = defineProps<{
    partenaires: {
        data: PartenaireRow[];
        current_page: number;
        per_page: number;
        total: number;
    };
}>();

const breadcrumbs = [
    { title: 'Reconciliation Flexcube', href: '/reconciliation-flexcube' },
    { title: 'Partenaires', href: '/reconciliation-flexcube/partenaires' },
];

const columns = [
    { key: 'icone', title: 'Icône' },
    { key: 'identifiant', title: 'Identifiant', sortable: true },
    { key: 'nom', title: 'Nom', sortable: true },
    { key: 'actions', title: 'Actions' },
];

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);

const showModal = ref(false);
const isEditing = ref(false);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const iconPreview = ref<string | null>(null);
const iconInputKey = ref(0);

const form = ref<{
    id: number | null;
    identifiant: string;
    nom: string;
    icone: File | null;
    icone_url: string | null;
}>({
    id: null,
    identifiant: '',
    nom: '',
    icone: null,
    icone_url: null,
});

function resetForm() {
    form.value = {
        id: null,
        identifiant: '',
        nom: '',
        icone: null,
        icone_url: null,
    };
    iconPreview.value = null;
    errors.value = {};
    iconInputKey.value += 1;
}

function openCreateModal() {
    isEditing.value = false;
    resetForm();
    showModal.value = true;
}

function openEditModal(row: PartenaireRow) {
    isEditing.value = true;
    form.value = {
        id: row.id,
        identifiant: row.identifiant,
        nom: row.nom,
        icone: null,
        icone_url: row.icone_url,
    };
    iconPreview.value = row.icone_url;
    errors.value = {};
    iconInputKey.value += 1;
    showModal.value = true;
}

function onIconChange(e: Event) {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    form.value.icone = file;
    if (file) {
        iconPreview.value = URL.createObjectURL(file);
    } else {
        iconPreview.value = form.value.icone_url;
    }
}

function deletePartenaire(id: number) {
    if (!confirm('Supprimer ce partenaire ?')) {
        return;
    }
    router.delete(`/reconciliation-flexcube/partenaires/${id}`, { preserveScroll: true });
}

function onPageChange(pageNumber: number) {
    router.get('/reconciliation-flexcube/partenaires', { page: pageNumber }, {
        preserveState: true,
        replace: true,
    });
}

function submitForm() {
    processing.value = true;
    errors.value = {};

    const payload: Record<string, unknown> = {
        identifiant: form.value.identifiant.trim(),
        nom: form.value.nom.trim(),
    };
    if (form.value.icone) {
        payload.icone = form.value.icone;
    }

    const options = {
        forceFormData: true as const,
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            resetForm();
        },
        onError: (errs: Record<string, string>) => {
            errors.value = errs;
        },
        onFinish: () => {
            processing.value = false;
        },
    };

    if (isEditing.value && form.value.id !== null) {
        router.post(`/reconciliation-flexcube/partenaires/${form.value.id}`, {
            ...payload,
            _method: 'put',
        }, options);
    } else {
        router.post('/reconciliation-flexcube/partenaires', payload, options);
    }
}
</script>

<template>
    <Head title="Partenaires — Reconciliation Flexcube" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div
                v-if="flash?.success"
                class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
            >
                {{ flash.success }}
            </div>

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-cyan-50 p-2 text-cyan-700">
                        <GitCompare class="size-6" />
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold text-foreground">Partenaires</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Référentiel des partenaires Flexcube (identifiant, nom et icône).
                        </p>
                    </div>
                </div>
                <Button class="bg-cyan-700 text-white hover:bg-cyan-800" @click="openCreateModal">
                    <Plus class="mr-2 h-4 w-4" />
                    Nouveau partenaire
                </Button>
            </div>

            <DataTable
                :headers="columns"
                :items="props.partenaires.data"
                :current-page="props.partenaires.current_page"
                :items-per-page="props.partenaires.per_page"
                :total-items="props.partenaires.total"
                :show-select="false"
                :on-page-change="onPageChange"
            >
                <template #item.icone="{ item }">
                    <div class="flex items-center">
                        <img
                            v-if="item.icone_url"
                            :src="item.icone_url"
                            :alt="item.nom"
                            class="size-9 rounded-md border border-border object-contain bg-white p-0.5"
                        />
                        <div
                            v-else
                            class="flex size-9 items-center justify-center rounded-md border border-dashed border-border bg-muted/40 text-muted-foreground"
                        >
                            <ImageIcon class="size-4" />
                        </div>
                    </div>
                </template>
                <template #item.identifiant="{ item }">
                    <span class="font-mono text-sm">{{ item.identifiant }}</span>
                </template>
                <template #item.actions="{ item }">
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-md p-2 text-muted-foreground hover:bg-muted hover:text-foreground"
                            title="Modifier"
                            @click="openEditModal(item)"
                        >
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-md p-2 text-red-600 hover:bg-red-50 hover:text-red-700"
                            title="Supprimer"
                            @click="deletePartenaire(item.id)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <Dialog :open="showModal" @update:open="showModal = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{ isEditing ? 'Modifier le partenaire' : 'Nouveau partenaire' }}
                    </DialogTitle>
                </DialogHeader>

                <form class="space-y-4 py-2" @submit.prevent="submitForm">
                    <div class="space-y-2">
                        <Label for="identifiant">Identifiant <span class="text-red-600">*</span></Label>
                        <Input
                            id="identifiant"
                            v-model="form.identifiant"
                            required
                            autocomplete="off"
                            placeholder="Ex. WAVE, OM, FREE"
                        />
                        <InputError :message="errors.identifiant" />
                    </div>

                    <div class="space-y-2">
                        <Label for="nom">Nom <span class="text-red-600">*</span></Label>
                        <Input
                            id="nom"
                            v-model="form.nom"
                            required
                            autocomplete="off"
                            placeholder="Nom du partenaire"
                        />
                        <InputError :message="errors.nom" />
                    </div>

                    <div class="space-y-2">
                        <Label for="icone">Icône</Label>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-muted/30"
                            >
                                <img
                                    v-if="iconPreview"
                                    :src="iconPreview"
                                    alt="Aperçu"
                                    class="size-full object-contain p-1"
                                />
                                <ImageIcon v-else class="size-5 text-muted-foreground" />
                            </div>
                            <Input
                                :key="iconInputKey"
                                id="icone"
                                type="file"
                                accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
                                class="cursor-pointer file:mr-3 file:rounded-md file:border-0 file:bg-cyan-100 file:px-3 file:py-1 file:text-sm file:font-medium file:text-cyan-800"
                                @change="onIconChange"
                            />
                        </div>
                        <p v-if="isEditing && form.icone_url && !form.icone" class="text-xs text-muted-foreground">
                            Laissez vide pour conserver l’icône actuelle.
                        </p>
                        <InputError :message="errors.icone" />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" :disabled="processing" @click="showModal = false">
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            class="bg-cyan-700 text-white hover:bg-cyan-800"
                            :disabled="processing"
                        >
                            {{ processing ? 'Enregistrement…' : 'Enregistrer' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
