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
    ChevronDown,
    ChevronUp,
    Download,
    Eye,
    Eraser,
    FileSpreadsheet,
    FileText,
    ListOrdered,
    Plus,
    Save,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { odFileTooLarge } from '@/lib/odUpload';

type OdLigne = {
    date_de_valeur: string;
    code_agence: string;
    no_compte: string;
    related_account: string;
    montant: string;
    sens: string;
    libelle_ecriture: string;
    code_operation: string;
};

type JustificatifLigne = {
    description: string;
    file: File | null;
};

type Agence = { id: number; code: string; nom: string };

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
    nom_classeur: string;
    lignes: OdLigne[];
    resume_url: string;
    pieces: EditPiece[];
};

const props = defineProps<{
    agences?: Agence[];
    codesOperation?: string[];
    comptableImportApiConfigured?: boolean;
    maxUploadMo?: number;
    editing?: boolean;
    classeur?: EditClasseur;
}>();

const breadcrumbs = [
    { title: 'Opérations diverses', href: '/operations-diverses/piece-comptable' },
    { title: 'Intégration', href: '/operations-diverses/integrations' },
    { title: 'Manuelle', href: '/operations-diverses/piece-comptable/manuelle' },
];

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string; warning?: string } | undefined);
const flashSuccess = computed(() => flash.value?.success);
const flashWarning = computed(() => flash.value?.warning);
const justificatifListKey = ref(0);
const maxUploadMo = computed(() => props.maxUploadMo ?? 25);

const fieldClass =
    'h-10 border-slate-300 bg-white text-slate-900 shadow-sm placeholder:text-slate-400 focus-visible:border-primary focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground dark:placeholder:text-slate-500';

const selectClass =
    'flex h-10 w-full rounded-md border border-slate-300 bg-white px-2 text-sm text-slate-900 shadow-sm focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';

const ligneVide = (): OdLigne => ({
    date_de_valeur: new Date().toISOString().slice(0, 10),
    code_agence: '',
    no_compte: '',
    related_account: '',
    montant: '',
    sens: '',
    libelle_ecriture: '',
    code_operation: '',
});

const form = useForm<{
    numero_batch: string;
    nom_classeur: string;
    lignes: OdLigne[];
    justificatifs: JustificatifLigne[];
}>({
    numero_batch: props.classeur?.numero_batch ?? '',
    nom_classeur: props.classeur?.nom_classeur ?? '',
    lignes: props.classeur?.lignes?.length ? props.classeur.lignes : [ligneVide()],
    justificatifs: props.editing ? [] : [{ description: 'Email', file: null }],
});

const stepIdentifiantsOk = computed(
    () => form.numero_batch.trim() !== '' && form.nom_classeur.trim() !== '',
);

const stepLignesOk = computed(() =>
    form.lignes.some(
        (l) =>
            l.date_de_valeur &&
            l.code_agence &&
            l.no_compte.trim() &&
            l.montant !== '' &&
            l.sens &&
            l.libelle_ecriture.trim() &&
            l.code_operation.trim(),
    ),
);

const stepPiecesOk = computed(() => {
    if (props.editing) {
        return (props.classeur?.pieces?.length ?? 0) > 0 || form.justificatifs.some((j) => j.file);
    }
    return form.justificatifs.some((j) => j.description.trim() && j.file);
});

const setupStep = computed(() => {
    if (!stepIdentifiantsOk.value) return 1;
    if (!stepLignesOk.value) return 2;
    return 3;
});

function ajouterLigne() {
    form.lignes.push(ligneVide());
}

function retirerLigne(index: number) {
    if (form.lignes.length <= 1) return;
    form.lignes.splice(index, 1);
}

function monterLigne(index: number) {
    if (index <= 0) return;
    const tmp = form.lignes[index - 1];
    form.lignes[index - 1] = form.lignes[index];
    form.lignes[index] = tmp;
}

function descendreLigne(index: number) {
    if (index >= form.lignes.length - 1) return;
    const tmp = form.lignes[index + 1];
    form.lignes[index + 1] = form.lignes[index];
    form.lignes[index] = tmp;
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
    form.justificatifs.push({ description: '', file: null });
}

function retirerJustificatif(index: number) {
    if (!props.editing && form.justificatifs.length <= 1) return;
    form.justificatifs.splice(index, 1);
}

function effacerTout() {
    form.reset();
    form.lignes = [ligneVide()];
    form.justificatifs = [{ description: 'Email', file: null }];
    justificatifListKey.value += 1;
}

function submit() {
    if (props.editing && props.classeur) {
        const hasNewJustificatifs = form.justificatifs.some((j) => j.file !== null);
        form
            .transform((data) => ({
                ...data,
                justificatifs: data.justificatifs.filter((j) => j.file !== null),
            }))
            .put(`/operations-diverses/piece-comptable/${props.classeur.id}/manuelle`, {
                forceFormData: hasNewJustificatifs,
                preserveScroll: true,
            });
        return;
    }

    form.post('/operations-diverses/piece-comptable/manuelle', {
        forceFormData: true,
        preserveScroll: true,
    });
}

function ligneError(index: number, field: string): string | undefined {
    return (
        (form.errors as Record<string, string>)[`lignes.${index}.${field}`] ??
        (form.errors as Record<string, string>).lignes
    );
}

function formatBytes(size: number): string {
    if (size < 1024) return `${size} o`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} Ko`;
    return `${(size / (1024 * 1024)).toFixed(1)} Mo`;
}
</script>

<template>
    <Head :title="editing ? 'Modifier l’intégration manuelle — OD' : 'Intégration manuelle — OD'" />
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

            <form id="form-integration-manuelle-od" class="space-y-6" @submit.prevent="submit">
                <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                    <div
                        class="border-b border-border/80 bg-gradient-to-r from-primary/5 via-card to-transparent px-5 py-5 sm:px-6 dark:from-primary/10"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm"
                                >
                                    <FileSpreadsheet class="size-5" />
                                </div>
                                <div>
                                    <h1 class="text-xl font-semibold tracking-tight text-foreground">
                                        {{
                                            editing
                                                ? 'Modifier l’intégration manuelle'
                                                : 'Intégration manuelle'
                                        }}
                                    </h1>
                                    <p class="mt-1 max-w-xl text-sm text-muted-foreground">
                                        <template v-if="editing">
                                            Corrigez les lignes OD. Les pièces déjà jointes sont
                                            conservées.
                                        </template>
                                        <template v-else>
                                            Identifiants, lignes d’écritures, puis pièces
                                            justificatives — en une seule soumission.
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
                                            ? 'bg-primary/10 text-primary'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    <span
                                        class="flex size-5 items-center justify-center rounded-full text-[11px] font-bold"
                                        :class="
                                            stepIdentifiantsOk
                                                ? 'bg-primary text-primary-foreground'
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
                                            ? 'bg-primary/10 text-primary'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    <span
                                        class="flex size-5 items-center justify-center rounded-full text-[11px] font-bold"
                                        :class="
                                            stepLignesOk
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-white text-primary dark:bg-primary/30 dark:text-red-100'
                                        "
                                    >
                                        <Check v-if="stepLignesOk" class="size-3" />
                                        <template v-else>2</template>
                                    </span>
                                    Lignes
                                </li>
                                <li class="hidden text-muted-foreground/50 sm:inline" aria-hidden="true">
                                    →
                                </li>
                                <li
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 transition"
                                    :class="
                                        setupStep >= 3
                                            ? 'bg-primary/10 text-primary'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    <span
                                        class="flex size-5 items-center justify-center rounded-full text-[11px] font-bold"
                                        :class="
                                            stepPiecesOk
                                                ? 'bg-primary text-primary-foreground'
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

                    <!-- Étape 1 : Identifiants -->
                    <div class="border-b border-border/80 p-5 sm:p-6">
                        <div class="mb-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-primary">
                                Étape 1
                            </p>
                            <h2 class="mt-0.5 text-sm font-semibold text-foreground">Identifiants</h2>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Batch et libellé du classeur.
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="numero_batch">
                                    Numéro batch <span class="text-red-600">*</span>
                                </Label>
                                <Input
                                    id="numero_batch"
                                    v-model="form.numero_batch"
                                    type="text"
                                    autocomplete="off"
                                    placeholder="xxxx"
                                    :class="[fieldClass, 'font-medium tracking-wide']"
                                />
                                <InputError :message="form.errors.numero_batch" />
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

                        <p
                            v-if="comptableImportApiConfigured === false"
                            class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
                        >
                            API non configurée : les lignes seront enregistrées en brouillon mais
                            non transmises à la plateforme.
                        </p>
                    </div>

                    <!-- Étape 2 : Lignes -->
                    <div class="p-5 sm:p-6">
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-primary">
                                    Étape 2
                                </p>
                                <h2 class="mt-0.5 text-sm font-semibold text-foreground">
                                    Lignes des OD
                                </h2>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ form.lignes.length }} ligne{{
                                        form.lignes.length > 1 ? 's' : ''
                                    }}
                                    — renseignez chaque écriture.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="border-primary/25 text-primary hover:bg-primary/5"
                                @click="ajouterLigne"
                            >
                                <Plus class="size-4" />
                                Ajouter une ligne
                            </Button>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="(ligne, index) in form.lignes"
                                :key="index"
                                class="relative rounded-xl border border-border bg-muted/20 p-4 transition hover:border-primary/30"
                            >
                                <div class="mb-3 flex items-center justify-between gap-2">
                                    <span
                                        class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                                    >
                                        <ListOrdered class="size-3.5 text-primary" />
                                        Ligne {{ index + 1 }}
                                    </span>
                                    <div class="flex items-center gap-0.5">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-7 text-muted-foreground"
                                            :disabled="index === 0"
                                            :aria-label="'Monter la ligne ' + (index + 1)"
                                            @click="monterLigne(index)"
                                        >
                                            <ChevronUp class="size-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-7 text-muted-foreground"
                                            :disabled="index === form.lignes.length - 1"
                                            :aria-label="'Descendre la ligne ' + (index + 1)"
                                            @click="descendreLigne(index)"
                                        >
                                            <ChevronDown class="size-4" />
                                        </Button>
                                        <Button
                                            v-if="form.lignes.length > 1"
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-7 text-muted-foreground hover:text-destructive"
                                            :aria-label="'Supprimer la ligne ' + (index + 1)"
                                            @click="retirerLigne(index)"
                                        >
                                            <X class="size-4" />
                                        </Button>
                                    </div>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
                                    <div class="space-y-1.5">
                                        <Label class="text-xs font-medium text-muted-foreground">
                                            Date <span class="text-red-600">*</span>
                                        </Label>
                                        <Input
                                            v-model="ligne.date_de_valeur"
                                            type="date"
                                            :class="[fieldClass, 'h-9']"
                                        />
                                        <InputError :message="ligneError(index, 'date_de_valeur')" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-xs font-medium text-muted-foreground">
                                            Agence <span class="text-red-600">*</span>
                                        </Label>
                                        <select v-model="ligne.code_agence" :class="selectClass">
                                            <option value="">Agence</option>
                                            <option
                                                v-for="a in agences"
                                                :key="a.id"
                                                :value="a.code"
                                            >
                                                {{ a.code }} — {{ a.nom }}
                                            </option>
                                        </select>
                                        <InputError :message="ligneError(index, 'code_agence')" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-xs font-medium text-muted-foreground">
                                            N° CPT <span class="text-red-600">*</span>
                                        </Label>
                                        <Input
                                            v-model="ligne.no_compte"
                                            placeholder="N° CPT"
                                            :class="[fieldClass, 'h-9']"
                                        />
                                        <InputError :message="ligneError(index, 'no_compte')" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-xs font-medium text-muted-foreground">
                                            Related Account
                                        </Label>
                                        <Input
                                            v-model="ligne.related_account"
                                            placeholder="Related Account"
                                            :class="[fieldClass, 'h-9']"
                                        />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-xs font-medium text-muted-foreground">
                                            Montant <span class="text-red-600">*</span>
                                        </Label>
                                        <Input
                                            v-model="ligne.montant"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            placeholder="Montant"
                                            :class="[fieldClass, 'h-9']"
                                        />
                                        <InputError :message="ligneError(index, 'montant')" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-xs font-medium text-muted-foreground">
                                            Sens <span class="text-red-600">*</span>
                                        </Label>
                                        <select v-model="ligne.sens" :class="selectClass">
                                            <option value="">Sens</option>
                                            <option value="D">D — Débit</option>
                                            <option value="C">C — Crédit</option>
                                        </select>
                                        <InputError :message="ligneError(index, 'sens')" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-xs font-medium text-muted-foreground">
                                            Libellé <span class="text-red-600">*</span>
                                        </Label>
                                        <Input
                                            v-model="ligne.libelle_ecriture"
                                            placeholder="Libellé"
                                            :class="[fieldClass, 'h-9']"
                                        />
                                        <InputError
                                            :message="ligneError(index, 'libelle_ecriture')"
                                        />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-xs font-medium text-muted-foreground">
                                            Code <span class="text-red-600">*</span>
                                        </Label>
                                        <Input
                                            v-model="ligne.code_operation"
                                            list="codes-operation"
                                            placeholder="Code"
                                            :class="[fieldClass, 'h-9']"
                                        />
                                        <datalist id="codes-operation">
                                            <option
                                                v-for="code in codesOperation"
                                                :key="code"
                                                :value="code"
                                            />
                                        </datalist>
                                        <InputError
                                            :message="ligneError(index, 'code_operation')"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Étape 3 : Pièces -->
                <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                    <div
                        class="flex flex-wrap items-start justify-between gap-3 border-b border-border/80 px-5 py-4 sm:px-6"
                    >
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-primary">
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
                                    Au moins une pièce avec libellé et fichier (max.
                                    {{ maxUploadMo }} Mo).
                                </template>
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="border-primary/25 text-primary hover:bg-primary/5"
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
                                            ? 'bg-primary/10 text-primary'
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
                                    class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
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
                            class="relative rounded-xl border border-border bg-muted/20 p-4 transition hover:border-primary/30"
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
                                class="bg-primary text-primary-foreground hover:bg-primary/90"
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
