<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import {
    ArrowLeft,
    Check,
    Download,
    Eye,
    FileSpreadsheet,
    FileText,
    Plus,
    Trash2,
    Eraser,
    Save,
    Upload,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { odFileTooLarge } from '@/lib/odUpload';

type JustificatifLigne = {
    description: string;
    file: File | null;
};

type EditPiece = {
    id: number | string;
    description: string | null;
    original_name: string;
    url: string;
    preview_url?: string | null;
    is_piece_comptable?: boolean;
};

type EditClasseur = {
    id: number;
    numero_batch: string;
    date_valeur: string | null;
    nom_classeur: string;
    fichier: string | null;
    resume_url: string;
    pieces: EditPiece[];
};

const props = defineProps<{
    odIntegrationConfigured?: boolean;
    templateCsvUrl?: string;
    maxUploadMo?: number;
    editing?: boolean;
    classeur?: EditClasseur;
}>();

const breadcrumbs = [
    { title: 'Opérations diverses', href: '/operations-diverses/piece-comptable' },
    { title: 'Intégration', href: '/operations-diverses/integrations' },
    { title: 'Automatique', href: '/operations-diverses/piece-comptable' },
];

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string; warning?: string } | undefined);
const flashSuccess = computed(() => flash.value?.success);
const flashWarning = computed(() => flash.value?.warning);

const maxUploadMo = computed(() => props.maxUploadMo ?? 25);

/** Champs plus contrastés (bordure / fond / placeholder). */
const fieldClass =
    'h-10 border-slate-300 bg-white text-slate-900 shadow-sm placeholder:text-slate-400 focus-visible:border-primary focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground dark:placeholder:text-slate-500';

const form = useForm<{
    numero_batch: string;
    date_valeur: string;
    nom_classeur: string;
    fichier_integration: File | null;
    justificatifs: JustificatifLigne[];
}>({
    numero_batch: props.classeur?.numero_batch ?? '',
    date_valeur: props.classeur?.date_valeur ?? new Date().toISOString().slice(0, 10),
    nom_classeur: props.classeur?.nom_classeur ?? '',
    fichier_integration: null,
    justificatifs: props.editing ? [] : [{ description: 'Email', file: null }],
});

/** Incrémenté pour réinitialiser les champs fichier HTML après « Effacer ». */
const justificatifListKey = ref(0);
const csvInputRef = ref<HTMLInputElement | null>(null);
const csvDragOver = ref(false);

const stepIdentifiantsOk = computed(
    () => form.date_valeur !== '' && form.nom_classeur.trim() !== '',
);

const stepCsvOk = computed(
    () =>
        form.fichier_integration !== null ||
        (Boolean(props.editing) && Boolean(props.classeur?.fichier)),
);

const stepPiecesOk = computed(() => {
    if (props.editing) {
        return (props.classeur?.pieces?.length ?? 0) > 0 || form.justificatifs.some((j) => j.file);
    }
    return form.justificatifs.some((j) => j.description.trim() && j.file);
});

const setupStep = computed(() => {
    if (!stepIdentifiantsOk.value) return 1;
    if (!stepCsvOk.value) return 2;
    return 3;
});

function assignIntegrationFile(f: File | null, input?: HTMLInputElement | null) {
    const tooLarge = odFileTooLarge(f, maxUploadMo.value);
    if (tooLarge) {
        form.setError('fichier_integration', tooLarge);
        form.fichier_integration = null;
        if (input) input.value = '';
        return;
    }
    form.clearErrors('fichier_integration');
    form.fichier_integration = f;
}

function onIntegrationFile(e: Event) {
    const t = e.target as HTMLInputElement;
    assignIntegrationFile(t.files?.[0] ?? null, t);
}

function onCsvDrop(e: DragEvent) {
    csvDragOver.value = false;
    const f = e.dataTransfer?.files?.[0] ?? null;
    if (!f) return;
    const name = f.name.toLowerCase();
    if (!name.endsWith('.csv') && !name.endsWith('.txt') && f.type !== 'text/csv') {
        form.setError('fichier_integration', 'Seuls les fichiers CSV / TXT sont acceptés.');
        return;
    }
    assignIntegrationFile(f);
}

function clearCsvFile() {
    form.fichier_integration = null;
    form.clearErrors('fichier_integration');
    if (csvInputRef.value) csvInputRef.value.value = '';
}

function onJustificatifFile(index: number, e: Event) {
    const t = e.target as HTMLInputElement;
    const f = t.files?.[0] ?? null;
    const tooLarge = odFileTooLarge(f, maxUploadMo.value);
    if (tooLarge) {
        form.setError(`justificatifs.${index}.file`, tooLarge);
        form.justificatifs[index].file = null;
        t.value = '';
        return;
    }
    form.clearErrors(`justificatifs.${index}.file`);
    form.justificatifs[index].file = f;
}

function ajouterJustificatif() {
    form.justificatifs.push({
        description: '',
        file: null,
    });
}

function retirerJustificatif(index: number) {
    if (!props.editing && form.justificatifs.length <= 1) {
        return;
    }
    form.justificatifs.splice(index, 1);
}

function effacerTout() {
    form.reset();
    form.justificatifs = [{ description: 'Email', file: null }];
    justificatifListKey.value += 1;
    if (csvInputRef.value) csvInputRef.value.value = '';
}

function submit() {
    if (props.editing && props.classeur) {
        // PUT + multipart FormData : PHP ne parse pas le corps → champs « required » vides.
        // On spoofe via POST + _method=PUT (compatible fichiers CSV / pièces).
        form
            .transform((data) => ({
                ...data,
                _method: 'put',
                justificatifs: data.justificatifs.filter((j) => j.file !== null),
            }))
            .post(`/operations-diverses/piece-comptable/${props.classeur.id}`, {
                forceFormData: true,
                preserveScroll: true,
            });
        return;
    }

    form.post('/operations-diverses/piece-comptable', {
        forceFormData: true,
        preserveScroll: true,
    });
}

function formatBytes(size: number): string {
    if (size < 1024) return `${size} o`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} Ko`;
    return `${(size / (1024 * 1024)).toFixed(1)} Mo`;
}
</script>

<template>
    <Head :title="editing ? 'Modifier l’intégration — OD' : 'Intégration automatique — OD'" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 sm:p-6">
            <div
                v-if="flashSuccess"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"
            >
                {{ flashSuccess }}
            </div>
            <div
                v-if="flashWarning"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
            >
                {{ flashWarning }}
            </div>

            <Link
                v-if="editing && classeur?.resume_url"
                :href="classeur.resume_url"
                class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground transition hover:text-foreground"
            >
                <ArrowLeft class="size-4" /> Retour au résumé
            </Link>

            <form id="form-integration-od" class="space-y-6" @submit.prevent="submit">
                <section
                    class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm"
                >
                    <!-- En-tête + étapes -->
                    <div
                        class="border-b border-border/80 bg-gradient-to-r from-primary/5 via-card to-transparent px-5 py-5 sm:px-6 dark:from-primary/10"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary text-white shadow-sm"
                                >
                                    <FileSpreadsheet class="size-5" />
                                </div>
                                <div>
                                    <h1 class="text-xl font-semibold tracking-tight text-foreground">
                                        {{
                                            editing
                                                ? 'Modifier l’intégration automatique'
                                                : 'Intégration automatique'
                                        }}
                                    </h1>
                                    <p class="mt-1 max-w-xl text-sm text-muted-foreground">
                                        <template v-if="editing">
                                            Corrigez les données. Les pièces déjà jointes sont
                                            conservées.
                                        </template>
                                        <template v-else>
                                            Identifiants, fichier CSV, puis pièces justificatives —
                                            en une seule soumission.
                                        </template>
                                    </p>
                                </div>
                            </div>

                            <ol
                                v-if="!editing"
                                class="flex flex-wrap items-center gap-1.5 text-xs font-medium"
                                aria-label="Progression"
                            >
                                <li
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 transition"
                                    :class="
                                        setupStep >= 1
                                            ? 'bg-primary/10 text-primary dark:bg-primary/20 dark:text-red-200'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    <span
                                        class="flex size-5 items-center justify-center rounded-full text-[11px] font-bold"
                                        :class="
                                            stepIdentifiantsOk
                                                ? 'bg-primary text-white'
                                                : 'bg-white text-primary dark:bg-primary/30 dark:text-red-100'
                                        "
                                    >
                                        <Check v-if="stepIdentifiantsOk" class="size-3" />
                                        <template v-else>1</template>
                                    </span>
                                    Identifiants
                                </li>
                                <li class="hidden text-muted-foreground/50 sm:inline" aria-hidden="true">
                                    →
                                </li>
                                <li
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 transition"
                                    :class="
                                        setupStep >= 2
                                            ? 'bg-primary/10 text-primary dark:bg-primary/20 dark:text-red-200'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    <span
                                        class="flex size-5 items-center justify-center rounded-full text-[11px] font-bold"
                                        :class="
                                            stepCsvOk
                                                ? 'bg-primary text-white'
                                                : 'bg-white text-primary dark:bg-primary/30 dark:text-red-100'
                                        "
                                    >
                                        <Check v-if="stepCsvOk" class="size-3" />
                                        <template v-else>2</template>
                                    </span>
                                    CSV
                                </li>
                                <li class="hidden text-muted-foreground/50 sm:inline" aria-hidden="true">
                                    →
                                </li>
                                <li
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 transition"
                                    :class="
                                        setupStep >= 3
                                            ? 'bg-primary/10 text-primary dark:bg-primary/20 dark:text-red-200'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    <span
                                        class="flex size-5 items-center justify-center rounded-full text-[11px] font-bold"
                                        :class="
                                            stepPiecesOk
                                                ? 'bg-primary text-white'
                                                : 'bg-white text-primary dark:bg-primary/30 dark:text-red-100'
                                        "
                                    >
                                        <Check v-if="stepPiecesOk" class="size-3" />
                                        <template v-else>3</template>
                                    </span>
                                    Pièces
                                </li>
                            </ol>
                        </div>
                    </div>

                    <!-- Identifiants + CSV -->
                    <div class="grid lg:grid-cols-5">
                        <div class="space-y-5 border-b border-border/80 p-5 sm:p-6 lg:col-span-2 lg:border-b-0 lg:border-r">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-primary dark:text-red-300">
                                    Étape 1
                                </p>
                                <h2 class="mt-0.5 text-sm font-semibold text-foreground">
                                    Identifiants
                                </h2>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    Date valeur et libellé du classeur. Le n° de batch est attribué à l’intégration.
                                </p>
                            </div>

                            <div class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="numero_batch">Numéro batch</Label>
                                    <Input
                                        id="numero_batch"
                                        v-model="form.numero_batch"
                                        type="text"
                                        autocomplete="off"
                                        placeholder="Laissé vide : attribué automatiquement"
                                        :class="[fieldClass, 'font-medium tracking-wide']"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Optionnel. Sera remplacé par le numéro généré à l’intégration.
                                    </p>
                                    <InputError :message="form.errors.numero_batch" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="date_valeur">
                                        Date valeur <span class="text-red-600">*</span>
                                    </Label>
                                    <Input
                                        id="date_valeur"
                                        v-model="form.date_valeur"
                                        type="date"
                                        :class="fieldClass"
                                    />
                                    <InputError :message="form.errors.date_valeur" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="nom_classeur">
                                        Nom du classeur <span class="text-red-600">*</span>
                                    </Label>
                                    <Input
                                        id="nom_classeur"
                                        v-model="form.nom_classeur"
                                        type="text"
                                        placeholder="Libellé du classeur"
                                        :class="fieldClass"
                                    />
                                    <InputError :message="form.errors.nom_classeur" />
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4 p-5 sm:p-6 lg:col-span-3">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-primary dark:text-red-300">
                                        Étape 2
                                    </p>
                                    <h2 class="mt-0.5 text-sm font-semibold text-foreground">
                                        Fichier CSV
                                        <span v-if="!editing" class="text-red-600">*</span>
                                    </h2>
                                    <p class="mt-0.5 text-xs text-muted-foreground">
                                        Glissez le fichier ou cliquez pour le sélectionner (max.
                                        {{ maxUploadMo }} Mo).
                                    </p>
                                </div>
                                <a
                                    v-if="templateCsvUrl"
                                    :href="templateCsvUrl"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-primary/25 bg-primary/5 px-3 py-1.5 text-xs font-medium text-primary transition hover:bg-primary/10 dark:border-primary/30 dark:bg-primary/15 dark:text-red-200 dark:hover:bg-primary/20"
                                >
                                    <Download class="size-3.5" />
                                    Modèle CSV
                                </a>
                            </div>

                            <input
                                id="fichier_integration"
                                ref="csvInputRef"
                                type="file"
                                accept=".csv,.txt,text/csv"
                                class="sr-only"
                                @change="onIntegrationFile"
                            />

                            <div
                                role="button"
                                tabindex="0"
                                class="group relative flex min-h-[180px] cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-4 py-8 text-center transition outline-none focus-visible:ring-2 focus-visible:ring-primary/50"
                                :class="
                                    csvDragOver
                                        ? 'border-primary bg-primary/5 dark:bg-primary/15'
                                        : form.fichier_integration
                                          ? 'border-primary/40 bg-primary/5 dark:border-primary/40 dark:bg-primary/10'
                                          : 'border-slate-300 bg-slate-50/80 hover:border-primary/50 hover:bg-primary/5 dark:border-slate-600 dark:hover:border-primary/40'
                                "
                                @click="csvInputRef?.click()"
                                @keydown.enter.prevent="csvInputRef?.click()"
                                @keydown.space.prevent="csvInputRef?.click()"
                                @dragenter.prevent="csvDragOver = true"
                                @dragover.prevent="csvDragOver = true"
                                @dragleave.prevent="csvDragOver = false"
                                @drop.prevent="onCsvDrop"
                            >
                                <template v-if="form.fichier_integration">
                                    <div
                                        class="flex size-12 items-center justify-center rounded-xl bg-primary text-white shadow-sm"
                                    >
                                        <FileSpreadsheet class="size-6" />
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-foreground">
                                        {{ form.fichier_integration.name }}
                                    </p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ formatBytes(form.fichier_integration.size) }} — cliquez
                                        pour remplacer
                                    </p>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="mt-3 text-muted-foreground hover:text-destructive"
                                        @click.stop="clearCsvFile"
                                    >
                                        <Trash2 class="size-3.5" />
                                        Retirer
                                    </Button>
                                </template>
                                <template v-else>
                                    <div
                                        class="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground transition group-hover:bg-primary/10 group-hover:text-primary dark:group-hover:bg-primary/20 dark:group-hover:text-red-300"
                                    >
                                        <Upload class="size-6" />
                                    </div>
                                    <p class="mt-3 text-sm font-medium text-foreground">
                                        Glissez un fichier CSV ici
                                    </p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        ou cliquez pour parcourir · .csv, .txt
                                    </p>
                                    <p
                                        v-if="editing && classeur?.fichier"
                                        class="mt-3 rounded-lg bg-background/80 px-3 py-1.5 text-xs text-muted-foreground ring-1 ring-border"
                                    >
                                        Fichier actuel :
                                        <span class="font-medium text-foreground">{{
                                            classeur.fichier
                                        }}</span>
                                        — laissez vide pour conserver.
                                    </p>
                                </template>
                            </div>

                            <p
                                v-if="odIntegrationConfigured === false"
                                class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
                            >
                                Le service d’intégration n’est pas disponible. Contactez le support.
                            </p>
                            <InputError :message="form.errors.fichier_integration" />
                        </div>
                    </div>
                </section>

                <!-- Pièces justificatives -->
                <section
                    class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3 border-b border-border/80 px-5 py-4 sm:px-6"
                    >
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-primary dark:text-red-300">
                                Étape 3
                            </p>
                            <h2 class="mt-0.5 text-sm font-semibold text-foreground">
                                Pièces justificatives
                            </h2>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                <template v-if="editing">
                                    Documents déjà joints — ajoutez-en d’autres si besoin.
                                </template>
                                <template v-else>
                                    Au moins une pièce avec libellé et fichier.
                                </template>
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="border-primary/25 text-primary hover:bg-primary/5 dark:border-primary/30 dark:text-red-200 dark:hover:bg-primary/15"
                            @click="ajouterJustificatif"
                        >
                            <Plus class="size-4" />
                            {{ editing ? 'Ajouter une pièce' : 'Ajouter' }}
                        </Button>
                    </div>

                    <ul
                        v-if="editing && classeur?.pieces?.length"
                        class="divide-y divide-border border-b border-border"
                    >
                        <li
                            v-for="p in classeur.pieces"
                            :key="p.id"
                            class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 sm:px-6"
                        >
                            <div class="flex min-w-0 items-center gap-2.5 text-sm">
                                <div
                                    class="flex size-8 shrink-0 items-center justify-center rounded-lg"
                                    :class="
                                        p.is_piece_comptable
                                            ? 'bg-primary/10 text-primary dark:bg-primary/20 dark:text-red-300'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    <FileText class="size-4" />
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-foreground">
                                        {{ p.description || p.original_name }}
                                    </p>
                                    <p class="truncate text-xs text-muted-foreground">
                                        {{ p.original_name }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <a
                                    v-if="p.preview_url"
                                    :href="p.preview_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline dark:text-red-300"
                                    title="Visualiser"
                                >
                                    <Eye class="size-3.5" /> Voir
                                </a>
                                <a
                                    :href="p.url"
                                    class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground"
                                >
                                    <Download class="size-3.5" /> Télécharger
                                </a>
                            </div>
                        </li>
                    </ul>

                    <div class="space-y-3 p-5 sm:p-6">
                        <div
                            v-for="(ligne, index) in form.justificatifs"
                            :key="`${justificatifListKey}-${index}`"
                            class="relative rounded-xl border border-border bg-muted/20 p-4 transition hover:border-primary/25/80 dark:hover:border-primary/30"
                        >
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <span class="text-xs font-medium text-muted-foreground">
                                    Pièce {{ index + 1 }}
                                </span>
                                <Button
                                    v-if="editing || form.justificatifs.length > 1"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 text-muted-foreground hover:text-destructive"
                                    :aria-label="'Supprimer la pièce ' + (index + 1)"
                                    @click="retirerJustificatif(index)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label :for="'pj-desc-' + index">
                                        Texte à afficher <span class="text-red-600">*</span>
                                    </Label>
                                    <Input
                                        :id="'pj-desc-' + index"
                                        v-model="ligne.description"
                                        type="text"
                                        placeholder="Ex. Email, Facture…"
                                        :class="fieldClass"
                                    />
                                    <InputError
                                        :message="form.errors[`justificatifs.${index}.description`]"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="'pj-file-' + index">
                                        Joindre la pièce <span class="text-red-600">*</span>
                                    </Label>
                                    <Input
                                        :id="'pj-file-' + index"
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.csv,.txt,.xlsx,.xls,.doc,.docx,.eml"
                                        :class="[
                                            fieldClass,
                                            'cursor-pointer file:mr-3 file:rounded-md file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary hover:file:bg-primary/15 dark:file:bg-primary/20 dark:file:text-red-200',
                                        ]"
                                        @change="onJustificatifFile(index, $event)"
                                    />
                                    <p
                                        v-if="ligne.file"
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ ligne.file.name }} ({{ formatBytes(ligne.file.size) }})
                                    </p>
                                    <InputError
                                        :message="
                                            (form.errors as Record<string, string>)[
                                                `justificatifs.${index}.file`
                                            ]
                                        "
                                    />
                                </div>
                            </div>
                        </div>

                        <p
                            v-if="editing && form.justificatifs.length === 0"
                            class="rounded-lg border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground"
                        >
                            Aucune nouvelle pièce. Cliquez sur « Ajouter une pièce » pour en joindre.
                        </p>
                    </div>

                    <!-- Actions -->
                    <div
                        class="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 border-t border-border/80 bg-card/95 px-5 py-4 backdrop-blur sm:px-6"
                    >
                        <p class="text-xs text-muted-foreground">
                            <template v-if="editing">
                                Seules les nouvelles pièces ajoutées seront jointes.
                            </template>
                            <template v-else>
                                Enregistrement en brouillon, puis validation depuis le résumé.
                            </template>
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                v-if="!editing"
                                type="button"
                                variant="outline"
                                class="border-border"
                                @click="effacerTout"
                            >
                                <Eraser class="size-4" />
                                Effacer
                            </Button>
                            <Button
                                type="submit"
                                class="bg-primary text-white hover:bg-primary/90 dark:bg-primary dark:hover:bg-primary/90"
                                :disabled="form.processing"
                            >
                                <Save class="size-4" />
                                {{
                                    form.processing
                                        ? 'Enregistrement…'
                                        : editing
                                          ? 'Enregistrer les modifications'
                                          : 'Enregistrer'
                                }}
                            </Button>
                        </div>
                    </div>
                </section>
            </form>
        </div>
    </AppLayout>
</template>
