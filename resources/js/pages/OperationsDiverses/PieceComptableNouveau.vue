<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { FileSpreadsheet, Plus, Trash2, Eraser, Save, Hash, CalendarDays } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type JustificatifLigne = {
    description: string;
    file: File | null;
};

defineProps<{
    comptableImportApiConfigured?: boolean;
}>();

const breadcrumbs = [
    { title: 'Opérations diverses', href: '/operations-diverses/piece-comptable' },
    { title: 'Pièce comptable', href: '/operations-diverses/piece-comptable' },
];

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string; warning?: string } | undefined);
const flashSuccess = computed(() => flash.value?.success);
const flashError = computed(() => flash.value?.error);
const flashWarning = computed(() => flash.value?.warning);

const form = useForm<{
    numero_batch: string;
    date_valeur: string;
    nom_classeur: string;
    fichier_integration: File | null;
    justificatifs: JustificatifLigne[];
}>({
    numero_batch: '',
    date_valeur: new Date().toISOString().slice(0, 10),
    nom_classeur: '',
    fichier_integration: null,
    justificatifs: [{ description: 'Email', file: null }],
});

/** Incrémenté pour réinitialiser les champs fichier HTML après « Effacer ». */
const justificatifListKey = ref(0);

function onIntegrationFile(e: Event) {
    const t = e.target as HTMLInputElement;
    form.fichier_integration = t.files?.[0] ?? null;
}

function onJustificatifFile(index: number, e: Event) {
    const t = e.target as HTMLInputElement;
    const f = t.files?.[0] ?? null;
    form.justificatifs[index].file = f;
}

function ajouterJustificatif() {
    form.justificatifs.push({
        description: '',
        file: null,
    });
}

function retirerJustificatif(index: number) {
    if (form.justificatifs.length <= 1) {
        return;
    }
    form.justificatifs.splice(index, 1);
}

function effacerTout() {
    form.reset();
    form.justificatifs = [{ description: 'Email', file: null }];
    justificatifListKey.value += 1;
}

function submit() {
    // Après enregistrement, le serveur redirige vers la page de résumé (aperçu CSV + validation).
    form.post('/operations-diverses/piece-comptable', {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Pièce comptable — OD" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div v-if="flashSuccess" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                {{ flashSuccess }}
            </div>
            <div v-if="flashWarning" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                {{ flashWarning }}
            </div>
            <div v-if="flashError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                {{ flashError }}
            </div>

            <div class="flex items-start gap-3">
                <div class="rounded-lg bg-muted p-2 text-muted-foreground">
                    <FileSpreadsheet class="size-6" />
                </div>
                <div>
                    <h1 class="text-xl font-semibold text-foreground">Pièce comptable</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Initiez une intégration OD : renseignez le batch, la date valeur, le classeur, le fichier d’export et les pièces justificatives.
                    </p>
                </div>
            </div>

            <form id="form-integration-od" class="space-y-8" @submit.prevent="submit">
                <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div class="border-b border-border bg-gradient-to-r from-violet-50/90 to-transparent px-5 py-3 dark:from-violet-950/40 dark:to-transparent">
                        <h2 class="text-sm font-semibold text-foreground">Données d’intégration</h2>
                        
                    </div>

                    <div class="space-y-5 p-5">
                        <!-- Champs mis en valeur : Numéro batch & Date valeur -->
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl border border-violet-200/80 bg-violet-50/50 p-4 shadow-xs dark:border-violet-900/60 dark:bg-violet-950/20">
                                <div class="flex items-center gap-2">
                                    <div class="rounded-lg bg-violet-600 p-1.5 text-white">
                                        <Hash class="size-4" />
                                    </div>
                                    <Label for="numero_batch" class="text-xs font-semibold uppercase tracking-wide text-violet-800 dark:text-violet-300">
                                        Numéro batch <span class="text-red-600">*</span>
                                    </Label>
                                </div>
                                <Input
                                    id="numero_batch"
                                    v-model="form.numero_batch"
                                    type="text"
                                    autocomplete="off"
                                    placeholder="xxxx"
                                    class="mt-3 h-11 border-violet-200 bg-white text-base font-semibold tracking-wide focus-visible:ring-violet-400/40 dark:border-violet-900 dark:bg-card"
                                />
                                <InputError :message="form.errors.numero_batch" />
                            </div>

                            <div class="rounded-xl border border-violet-200/80 bg-violet-50/50 p-4 shadow-xs dark:border-violet-900/60 dark:bg-violet-950/20">
                                <div class="flex items-center gap-2">
                                    <div class="rounded-lg bg-violet-600 p-1.5 text-white">
                                        <CalendarDays class="size-4" />
                                    </div>
                                    <Label for="date_valeur" class="text-xs font-semibold uppercase tracking-wide text-violet-800 dark:text-violet-300">
                                        Date valeur <span class="text-red-600">*</span>
                                    </Label>
                                </div>
                                <Input
                                    id="date_valeur"
                                    v-model="form.date_valeur"
                                    type="date"
                                    class="mt-3 h-11 border-violet-200 bg-white text-base font-semibold focus-visible:ring-violet-400/40 dark:border-violet-900 dark:bg-card"
                                />
                                <InputError :message="form.errors.date_valeur" />
                            </div>
                        </div>

                        <!-- Champs secondaires -->
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="nom_classeur">Nom du classeur <span class="text-red-600">*</span></Label>
                                <Input
                                    id="nom_classeur"
                                    v-model="form.nom_classeur"
                                    type="text"
                                    placeholder="Libellé du classeur"
                                />
                                <InputError :message="form.errors.nom_classeur" />
                            </div>
                            <div class="space-y-2">
                                <Label for="fichier_integration">Fichier à importer pour l’intégration <span class="text-red-600">*</span></Label>
                                <Input
                                    id="fichier_integration"
                                    type="file"
                                    accept=".csv,.txt,text/csv"
                                    class="cursor-pointer file:mr-3 file:rounded-md file:border-0 file:bg-violet-100 file:px-3 file:py-1 file:text-sm file:font-medium file:text-violet-800 hover:file:bg-violet-200 dark:file:bg-violet-950 dark:file:text-violet-200"
                                    @change="onIntegrationFile"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Fichier CSV .
                                </p>
                                <p
                                    v-if="comptableImportApiConfigured === false"
                                    class="rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
                                >
                                   Non intégré : le fichier sera enregistré mais non transmis.
                                </p>
                                <InputError :message="form.errors.fichier_integration" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bloc type « Information sur les filtres » -->
                <div class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
                    <div class="border-b border-border bg-muted/70 px-4 py-3 dark:bg-muted/40">
                        <h2 class="text-sm font-semibold text-foreground">Pièces justificatives</h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Renseignez le libellé affiché et joignez le document. Utilisez
                            <span class="font-medium text-foreground">+ Ajouter</span> pour plusieurs pièces.
                        </p>
                    </div>

                    <div class="space-y-4 p-4">
                        <div
                            v-for="(ligne, index) in form.justificatifs"
                            :key="`${justificatifListKey}-${index}`"
                            class="relative rounded-lg border border-border bg-background p-4 pr-14 shadow-xs dark:bg-card/50"
                        >
                            <Button
                                v-if="form.justificatifs.length > 1"
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="absolute right-2 top-2 size-8 text-muted-foreground hover:text-destructive"
                                :aria-label="'Supprimer la pièce ' + (index + 1)"
                                @click="retirerJustificatif(index)"
                            >
                                <Trash2 class="size-4" />
                            </Button>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label :for="'pj-desc-' + index">Texte à afficher <span class="text-red-600">*</span></Label>
                                    <Input
                                        :id="'pj-desc-' + index"
                                        v-model="ligne.description"
                                        type="text"
                                        placeholder="Texte à afficher"
                                    />
                                    <InputError :message="form.errors[`justificatifs.${index}.description`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="'pj-file-' + index">Joindre la pièce <span class="text-red-600">*</span></Label>
                                    <Input
                                        :id="'pj-file-' + index"
                                        type="file"
                                        class="cursor-pointer file:mr-3 file:rounded-md file:border-0 file:bg-violet-100 file:px-3 file:py-1 file:text-sm file:font-medium file:text-violet-800 hover:file:bg-violet-200 dark:file:bg-violet-950 dark:file:text-violet-200"
                                        @change="onJustificatifFile(index, $event)"
                                    />
                                    <InputError
                                        :message="(form.errors as Record<string, string>)[`justificatifs.${index}.file`]"
                                    />
                                </div>
                            </div>
                        </div>

                        <Button
                            type="button"
                            class="bg-violet-600 text-white hover:bg-violet-700 dark:bg-violet-600 dark:hover:bg-violet-500"
                            @click="ajouterJustificatif"
                        >
                            <Plus class="size-4" />
                            Ajouter
                        </Button>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border bg-muted/40 px-4 py-3 dark:bg-muted/25">
                        <Button
                            type="button"
                            variant="outline"
                            class="border-violet-200 bg-background text-violet-800 hover:bg-violet-50 dark:border-violet-900 dark:text-violet-200 dark:hover:bg-violet-950/50"
                            @click="effacerTout"
                        >
                            <Eraser class="size-4" />
                            Effacer
                        </Button>
                        <Button
                            type="submit"
                            class="bg-violet-700 text-white hover:bg-violet-800 dark:bg-violet-600 dark:hover:bg-violet-500"
                            :disabled="form.processing"
                        >
                            <Save class="size-4" />
                            {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
